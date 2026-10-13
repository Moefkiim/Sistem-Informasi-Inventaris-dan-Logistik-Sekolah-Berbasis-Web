<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\AssetUnit;
use App\Models\ConditionHistory;
use App\Models\Item;
use App\Models\LocationHistory;
use App\Models\User;
use App\Support\AssetCondition;
use Illuminate\Support\Facades\DB;

/**
 * Layanan registrasi inventaris yang dipakai bersama oleh form master barang
 * dan fitur impor inventaris. Menangani pembuatan barang, unit aset, riwayat
 * awal (lokasi dan kondisi), nomor otomatis, serta sinkronisasi stok.
 */
class InventoryRegistrationService
{
    /**
     * Mendaftarkan satu barang master beserta unit dan riwayat awalnya.
     *
     * @param  array<string,mixed>  $data  atribut barang master (sudah divalidasi)
     * @param  array<int,array<string,mixed>>  $units  baris unit eksplisit (opsional, untuk barang individual)
     */
    public function register(array $data, User $actor, array $units = [], bool $logActivity = true): Item
    {
        return DB::transaction(function () use ($data, $actor, $units, $logActivity) {
            $itemType = ($data['item_type'] ?? 'consumable') === 'individual' ? 'individual' : 'consumable';
            $condition = $this->normalizeCondition($data['current_condition'] ?? null) ?? AssetCondition::BAIK;
            $locationId = $data['location_id'] ?? null;
            $initialStock = max(0, (int) ($data['stock'] ?? 0));

            unset($data['stock'], $data['units']);

            $data['item_type'] = $itemType;
            $data['category'] = $data['category'] ?? 'Umum';
            $data['unit'] = $data['unit'] ?? 'Unit';
            $data['source'] = $data['source'] ?? 'pembelian';
            $data['current_condition'] = $condition;
            $data['current_status'] = AssetUnit::normalizeInitialState($condition, $data['current_status'] ?? null)['status'];
            $data['code'] = ! empty($data['code']) ? $data['code'] : $this->generateItemCode($data['department'] ?? null);

            $item = new Item($data);
            $item->stock = $itemType === 'individual' ? 0 : $initialStock;
            $item->save();

            if ($locationId) {
                LocationHistory::create([
                    'item_id' => $item->id,
                    'from_location_id' => null,
                    'to_location_id' => $locationId,
                    'user_id' => $actor->id,
                    'notes' => 'Pencatatan inventaris awal',
                    'moved_at' => now(),
                ]);
            }

            if ($itemType === 'individual') {
                $unitRows = $units !== [] ? $units : array_fill(0, $initialStock, []);

                foreach ($unitRows as $unitData) {
                    $this->createUnit($item, (array) $unitData, $actor, $condition, $locationId);
                }

                $item->stock = $item->assetUnits()->where('current_status', '!=', 'disposed')->count();
                $item->save();
            } else {
                ConditionHistory::create([
                    'item_id' => $item->id,
                    'from_condition' => $condition,
                    'to_condition' => $condition,
                    'user_id' => $actor->id,
                    'notes' => 'Kondisi fisik saat registrasi aset',
                    'recorded_at' => now(),
                ]);
            }

            if ($logActivity) {
                ActivityLog::log(
                    'item_created',
                    "Barang baru didaftarkan: {$item->name} ({$item->code})",
                    $item,
                    [],
                    ['code' => $item->code, 'name' => $item->name, 'stock' => $item->stock],
                    $item->code
                );
            }

            return $item;
        });
    }

    /**
     * @param  array<string,mixed>  $unitData
     */
    private function createUnit(Item $item, array $unitData, User $actor, string $condition, ?int $locationId): AssetUnit
    {
        $unitCondition = $this->normalizeCondition($unitData['current_condition'] ?? null) ?? $condition;
        $state = AssetUnit::normalizeInitialState($unitCondition, $unitData['current_status'] ?? null);
        $unitCondition = $state['condition'];
        $status = $state['status'];
        $unitLocation = $unitData['location_id'] ?? $locationId;

        $unit = AssetUnit::create([
            'item_id' => $item->id,
            'unit_inventory_number' => $unitData['unit_inventory_number'] ?? $this->generateUnitNumber($item),
            'serial_number' => $unitData['serial_number'] ?? null,
            'current_condition' => $unitCondition,
            'current_status' => $status,
            'location_id' => $unitLocation,
            'is_legacy_migrated' => false,
        ]);

        ConditionHistory::create([
            'item_id' => $item->id,
            'asset_unit_id' => $unit->id,
            'from_condition' => $unitCondition,
            'to_condition' => $unitCondition,
            'user_id' => $actor->id,
            'notes' => 'Kondisi awal unit saat registrasi inventaris.',
            'recorded_at' => now(),
        ]);

        if ($unitLocation) {
            LocationHistory::create([
                'item_id' => $item->id,
                'asset_unit_id' => $unit->id,
                'from_location_id' => null,
                'to_location_id' => $unitLocation,
                'user_id' => $actor->id,
                'notes' => 'Registrasi unit saat pencatatan inventaris.',
                'moved_at' => now(),
            ]);
        }

        return $unit;
    }

    public function generateItemCode(?string $department): string
    {
        $prefix = 'BRG-'.($department ?: 'UMM').'-';

        $last = Item::withTrashed()
            ->where('code', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('code')
            ->value('code');

        $next = $last ? (int) substr((string) $last, -4) : 0;

        do {
            $next++;
            $candidate = $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
        } while (Item::withTrashed()->where('code', $candidate)->exists());

        return $candidate;
    }

    public function generateUnitNumber(Item $item): string
    {
        $sequence = AssetUnit::withTrashed()->where('item_id', $item->id)->count() + 1;

        do {
            $candidate = $item->code.'-'.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
            $sequence++;
        } while (AssetUnit::withTrashed()->where('unit_inventory_number', $candidate)->exists());

        return $candidate;
    }

    public function normalizeCondition(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $needle = mb_strtolower(trim($value));

        foreach (AssetCondition::LABELS as $canonical => $label) {
            if ($needle === $canonical || $needle === mb_strtolower($label)) {
                return $canonical;
            }
        }

        return null;
    }
}
