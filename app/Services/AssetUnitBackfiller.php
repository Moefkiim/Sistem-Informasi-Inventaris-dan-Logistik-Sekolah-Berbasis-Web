<?php

namespace App\Services;

use App\Models\AssetUnit;
use App\Models\Item;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Memindahkan data item individual (stock + identitas) menjadi baris asset_units
 * tanpa merusak data lama. Bagian dari migration backfill Section 2; selain itu
 * bisa dijalankan ulang (idempotent) untuk verifikasi count di atas data yang ada.
 */
class AssetUnitBackfiller
{
    /**
     * Jalankan backfill dalam satu transaksi.
     *
     * @return array{expected:int, created:int, backfilled_loans:int, item_units:array<int,array<int,int>>}
     */
    public function run(): array
    {
        return DB::transaction(function () {
            $items = Item::where('item_type', 'individual')
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->orderBy('id')
                ->get();

            $expected = [];
            $created = 0;
            $itemUnits = [];

            foreach ($items as $item) {
                $existing = array_map('intval', $item->assetUnits()->pluck('id')->all());

                if ($existing !== []) {
                    $expected[$item->id] = count($existing);
                    $itemUnits[$item->id] = $existing;

                    continue;
                }

                $unitCount = $this->unitCountFor($item);
                if ($unitCount <= 0) {
                    continue;
                }

                $expected[$item->id] = $unitCount;

                $units = [];
                for ($index = 1; $index <= $unitCount; $index++) {
                    $units[] = AssetUnit::create($this->unitPayload($item, $index));
                }

                $created += count($units);
                $itemUnits[$item->id] = array_map(fn ($unit) => (int) $unit->id, $units);

                // items.stock untuk individual = jumlah unit aktif yang terdaftar.
                $item->forceFill(['stock' => $unitCount])->saveQuietly();
            }

            $backfilledLoans = $this->backfillHistoriesAndLoans($itemUnits);

            $this->verify($expected);

            return [
                'expected' => array_sum($expected),
                'created' => $created,
                'backfilled_loans' => $backfilledLoans,
                'item_units' => $itemUnits,
            ];
        });
    }

    /**
     * Verifikasi count sebelum/sesudah: setiap item individual harus punya
     * persis jumlah unit yang diharapkan setelah backfill (termasuk saat
     * dijalankan ulang — idempotent).
     */
    private function verify(array $expected): void
    {
        foreach ($expected as $itemId => $count) {
            $actual = (int) AssetUnit::query()->where('item_id', $itemId)->count();

            if ($actual !== $count) {
                throw new RuntimeException(
                    "Backfill asset_units tidak cocok untuk item #{$itemId}: "
                    ."expected {$count}, created {$actual}. Transaksi dibatalkan."
                );
            }
        }
    }

    /**
     * Jumlah unit fisik untuk sebuah item lama.
     * D3: jumlah unit = nilai stock lama; fallback 1 unit bila stock 0 tapi ada
     * inventory_number; tanpa keduanya -> 0 unit (tidak ada yang ter-backfill).
     */
    private function unitCountFor(Item $item): int
    {
        $stock = (int) $item->stock;

        if ($stock > 0) {
            return $stock;
        }

        return ! empty($item->inventory_number) ? 1 : 0;
    }

    /**
     * Data payload satu unit hasil migrasi. Unit ke-1 membawa identitas asli item
     * (inventory_number, serial_number); unit berikutnya memakai nomor sintetis
     * {kode_barang}-{NNN} dan semua unit ditandai is_legacy_migrated = true.
     */
    private function unitPayload(Item $item, int $index): array
    {
        $number = $index === 1 && ! empty($item->inventory_number)
            ? $item->inventory_number
            : $this->syntheticNumber($item, $index);

        return [
            'item_id' => $item->id,
            'unit_inventory_number' => $number,
            'serial_number' => $index === 1 ? $item->serial_number : null,
            'current_condition' => $item->current_condition,
            'current_status' => $item->current_status,
            'location_id' => $item->location_id,
            'is_legacy_migrated' => true,
        ];
    }

    /**
     * Nomor sintetis per item dengan format {kode_barang}-{NNN}, unik antar unit.
     */
    private function syntheticNumber(Item $item, int $index): string
    {
        return $item->code.'-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT);
    }

    /**
     * D1: histori lama milik item individual yang punya unit dipetakan ke unit #1
     * (unit yang membawa identitas). Row consumable / ambigu tetap NULL.
     */
    private function backfillHistoriesAndLoans(array $itemUnits): int
    {
        $firstUnitByItem = [];
        foreach ($itemUnits as $itemId => $unitIds) {
            $firstUnitByItem[$itemId] = $unitIds[0];
        }

        if ($firstUnitByItem === []) {
            return 0;
        }

        $backfilledLoans = 0;
        foreach ($firstUnitByItem as $itemId => $unitId) {
            $loans = DB::table('loans')
                ->where('item_id', $itemId)
                ->whereNull('asset_unit_id')
                ->get();

            foreach ($loans as $loan) {
                DB::table('loans')->where('id', $loan->id)->update(['asset_unit_id' => $unitId]);
                $backfilledLoans++;
            }

            DB::table('condition_histories')
                ->where('item_id', $itemId)
                ->whereNull('asset_unit_id')
                ->update(['asset_unit_id' => $unitId]);

            DB::table('location_histories')
                ->where('item_id', $itemId)
                ->whereNull('asset_unit_id')
                ->update(['asset_unit_id' => $unitId]);
        }

        return $backfilledLoans;
    }
}
