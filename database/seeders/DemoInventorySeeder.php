<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\AssetUnit;
use App\Models\ConditionHistory;
use App\Models\Item;
use App\Models\Location;
use App\Models\LocationHistory;
use App\Models\User;
use Database\Seeders\Concerns\GuardsDemoEnvironment;
use Illuminate\Database\Seeder;

/**
 * Barang master demo: aset individual (berbasis unit fisik) dan barang habis
 * pakai. Invarian yang dijaga: jumlah unit individual = items.stock, nomor unit
 * unik, dan setiap unit punya riwayat lokasi + kondisi awal.
 */
class DemoInventorySeeder extends Seeder
{
    use GuardsDemoEnvironment;

    private User $sarpras;

    public function run(): void
    {
        if (! $this->demoDataAllowed()) {
            $this->command?->warn('DemoInventorySeeder dilewati: APP_ENV=production.');

            return;
        }

        $this->sarpras = User::where('username', 'sarpras')->firstOrFail();

        $this->seedIndividual([
            'code' => 'BRG-RPL-0001',
            'inventory_number' => 'INV-RPL-0001',
            'name' => 'Laptop Lenovo ThinkPad',
            'brand' => 'Lenovo',
            'model' => 'ThinkPad T14 Gen 4',
            'category' => 'Elektronik',
            'department' => 'RPL',
            'location_code' => 'LAB-RPL-01',
            'source' => 'pembelian',
            'acquisition_year' => 2023,
            'acquisition_price' => 12500000,
            'units' => [
                ['condition' => 'baik', 'status' => 'aktif'],
                ['condition' => 'baik', 'status' => 'aktif'],
                ['condition' => 'rusak_ringan', 'status' => 'aktif', 'history' => ['baik', 'rusak_ringan']],
                ['condition' => 'rusak_berat', 'status' => 'dalam_perbaikan', 'history' => ['baik', 'rusak_berat']],
            ],
        ]);

        $this->seedIndividual([
            'code' => 'BRG-RPL-0002',
            'inventory_number' => 'INV-RPL-0002',
            'name' => 'Proyektor Epson',
            'brand' => 'Epson',
            'model' => 'EB-X06',
            'category' => 'Elektronik',
            'department' => 'RPL',
            'location_code' => 'LAB-RPL-01',
            'source' => 'pembelian',
            'acquisition_year' => 2022,
            'acquisition_price' => 5500000,
            'units' => [
                ['condition' => 'baik', 'status' => 'aktif'],
                ['condition' => 'baik', 'status' => 'aktif'],
            ],
        ]);

        $this->seedIndividual([
            'code' => 'BRG-TKJ-0001',
            'inventory_number' => 'INV-TKJ-0001',
            'name' => 'Kamera Canon EOS',
            'brand' => 'Canon',
            'model' => 'EOS 250D',
            'category' => 'Elektronik',
            'department' => 'TKJ',
            'location_code' => 'LAB-TKJ-01',
            'source' => 'bantuan',
            'acquisition_year' => 2024,
            'acquisition_price' => 8200000,
            'units' => [
                ['condition' => 'baik', 'status' => 'aktif'],
                ['condition' => 'baik', 'status' => 'aktif'],
            ],
        ]);

        $this->seedIndividual([
            'code' => 'BRG-TKJ-0002',
            'inventory_number' => 'INV-TKJ-0002',
            'name' => 'Router Mikrotik',
            'brand' => 'Mikrotik',
            'model' => 'RB750Gr3',
            'category' => 'Elektronik',
            'department' => 'TKJ',
            'location_code' => 'LAB-TKJ-01',
            'source' => 'pembelian',
            'acquisition_year' => 2023,
            'acquisition_price' => 950000,
            'units' => [
                ['condition' => 'baik', 'status' => 'aktif'],
                ['condition' => 'baik', 'status' => 'aktif'],
                ['condition' => 'baik', 'status' => 'tidak_aktif'],
            ],
        ]);

        // Barang habis pakai: stok awal 0, diisi lewat transaksi logistik demo
        // agar angka stok konsisten dengan riwayat barang masuk/keluar.
        $this->seedConsumable([
            'code' => 'BRG-UMM-0001',
            'name' => 'Kertas A4 80gsm',
            'category' => 'Alat Tulis Kantor',
            'unit' => 'Rim',
            'minimum_stock' => 5,
            'location_code' => 'GDG-SARPRAS',
        ]);

        $this->seedConsumable([
            'code' => 'BRG-UMM-0002',
            'name' => 'Tinta Printer HP 802',
            'category' => 'Alat Tulis Kantor',
            'unit' => 'Botol',
            'minimum_stock' => 5,
            'location_code' => 'GDG-SARPRAS',
        ]);

        $this->seedConsumable([
            'code' => 'BRG-UMM-0003',
            'name' => 'Spidol Whiteboard',
            'category' => 'Alat Tulis Kantor',
            'unit' => 'Pcs',
            'minimum_stock' => 3,
            'location_code' => 'GDG-SARPRAS',
        ]);
    }

    /**
     * @param  array<string,mixed>  $data
     */
    private function seedIndividual(array $data): void
    {
        if (Item::where('code', $data['code'])->exists()) {
            $this->command?->info("Item {$data['code']} sudah ada, dilewati.");

            return;
        }

        $location = Location::where('code', $data['location_code'])->firstOrFail();
        $units = $data['units'];

        $item = new Item([
            'code' => $data['code'],
            'inventory_number' => $data['inventory_number'],
            'name' => $data['name'],
            'brand' => $data['brand'] ?? null,
            'model' => $data['model'] ?? null,
            'category' => $data['category'],
            'item_type' => 'individual',
            'unit' => 'Unit',
            'minimum_stock' => 0,
            'source' => $data['source'],
            'acquisition_year' => $data['acquisition_year'] ?? null,
            'acquisition_price' => $data['acquisition_price'] ?? null,
            'department' => $data['department'],
            'location_id' => $location->id,
            'current_condition' => 'baik',
            'current_status' => 'aktif',
            'description' => 'Aset individual demo '.$data['name'],
        ]);
        $item->stock = count($units);
        $item->save();

        LocationHistory::create([
            'item_id' => $item->id,
            'from_location_id' => null,
            'to_location_id' => $location->id,
            'user_id' => $this->sarpras->id,
            'notes' => 'Pencatatan inventaris awal',
            'moved_at' => now()->subMonths(10),
        ]);

        foreach ($units as $index => $unit) {
            $number = $item->inventory_number.'-U'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);

            $assetUnit = AssetUnit::create([
                'item_id' => $item->id,
                'unit_inventory_number' => $number,
                'serial_number' => null,
                'current_condition' => $unit['condition'],
                'current_status' => $unit['status'],
                'location_id' => $location->id,
                'is_legacy_migrated' => false,
                'notes' => 'Unit demo',
            ]);

            [$from, $to] = $unit['history'] ?? [$unit['condition'], $unit['condition']];

            ConditionHistory::create([
                'item_id' => $item->id,
                'asset_unit_id' => $assetUnit->id,
                'from_condition' => $from,
                'to_condition' => $to,
                'user_id' => $this->sarpras->id,
                'notes' => $from === $to ? 'Kondisi fisik saat registrasi aset' : 'Pembaruan kondisi unit aset',
                'recorded_at' => now()->subMonths(6),
            ]);
        }

        $item->current_condition = $item->recalculateAggregateCondition();
        $item->current_status = $item->recalculateAggregateStatus();
        $item->save();

        $this->runAsUser($this->sarpras, fn () => ActivityLog::log(
            'item_created',
            "Barang baru didaftarkan: {$item->name} ({$item->code})",
            $item,
            [],
            ['code' => $item->code, 'name' => $item->name, 'stock' => $item->stock],
            $item->code
        ));
    }

    /**
     * @param  array<string,mixed>  $data
     */
    private function seedConsumable(array $data): void
    {
        if (Item::where('code', $data['code'])->exists()) {
            $this->command?->info("Item {$data['code']} sudah ada, dilewati.");

            return;
        }

        $location = Location::where('code', $data['location_code'])->firstOrFail();

        $item = new Item([
            'code' => $data['code'],
            'name' => $data['name'],
            'category' => $data['category'],
            'item_type' => 'consumable',
            'unit' => $data['unit'],
            'minimum_stock' => $data['minimum_stock'],
            'source' => 'pembelian',
            'department' => null,
            'location_id' => $location->id,
            'current_condition' => 'baik',
            'current_status' => 'aktif',
            'description' => 'Barang habis pakai demo '.$data['name'],
        ]);
        $item->stock = 0;
        $item->save();

        LocationHistory::create([
            'item_id' => $item->id,
            'from_location_id' => null,
            'to_location_id' => $location->id,
            'user_id' => $this->sarpras->id,
            'notes' => 'Pencatatan inventaris awal',
            'moved_at' => now()->subMonths(9),
        ]);

        $this->runAsUser($this->sarpras, fn () => ActivityLog::log(
            'item_created',
            "Barang baru didaftarkan: {$item->name} ({$item->code})",
            $item,
            [],
            ['code' => $item->code, 'name' => $item->name, 'stock' => $item->stock],
            $item->code
        ));
    }
}
