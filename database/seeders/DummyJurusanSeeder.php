<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\AssetUnit;
use App\Models\ConditionHistory;
use App\Models\Item;
use App\Models\Location;
use App\Models\LocationHistory;
use App\Models\User;
use Illuminate\Database\Seeder;

class DummyJurusanSeeder extends Seeder
{
    public function run(): void
    {
        $sarpras = User::where('role', 'sarpras')->first();
        if (! $sarpras) {
            $sarpras = User::create([
                'username' => 'sarpras',
                'name' => 'Staf Sarana dan Prasarana',
                'email' => 'sarpras@sekolah.sch.id',
                'password' => 'password123',
                'role' => 'sarpras',
                'is_active' => true,
            ]);
        }

        // 1. Buat Akun Kajur untuk seluruh jurusan agar tiap jurusan bisa login
        $kajurAccounts = [
            ['username' => 'kajur_tkro', 'name' => 'Bambang Supriyadi, S.T. (Kajur TKRO)', 'email' => 'kajur.tkro@sekolah.sch.id', 'department' => 'TKRO'],
            ['username' => 'kajur_tbsm', 'name' => 'Agus Setiawan, S.Pd. (Kajur TBSM)', 'email' => 'kajur.tbsm@sekolah.sch.id', 'department' => 'TBSM'],
            ['username' => 'kajur_akl', 'name' => 'Dra. Endang Wahyuni (Kajur AKL)', 'email' => 'kajur.akl@sekolah.sch.id', 'department' => 'AKL'],
            ['username' => 'kajur_otkp', 'name' => 'Rina Marlina, S.Pd. (Kajur OTKP)', 'email' => 'kajur.otkp@sekolah.sch.id', 'department' => 'OTKP'],
        ];

        foreach ($kajurAccounts as $acc) {
            $user = User::firstOrNew(['username' => $acc['username']]);
            $user->name = $acc['name'];
            $user->email = $acc['email'];
            $user->password = 'password123';
            $user->department = $acc['department'];
            $user->role = 'kajur';
            $user->is_active = true;
            $user->save();
        }

        // 2. Buat Lokasi Ruangan / Bengkel untuk setiap jurusan
        $locationsData = [
            ['code' => 'BENGKEL-TKRO-01', 'name' => 'Bengkel Otomotif TKRO', 'building' => 'Gedung Otomotif', 'department' => 'TKRO', 'description' => 'Bengkel praktik servis kendaraan ringan'],
            ['code' => 'BENGKEL-TBSM-01', 'name' => 'Bengkel Sepeda Motor TBSM', 'building' => 'Gedung Otomotif', 'department' => 'TBSM', 'description' => 'Bengkel praktik tune-up dan servis sepeda motor'],
            ['code' => 'LAB-AKL-01', 'name' => 'Laboratorium Akuntansi AKL', 'building' => 'Gedung B', 'department' => 'AKL', 'description' => 'Lab komputer dan praktik perbankan akuntansi'],
            ['code' => 'LAB-OTKP-01', 'name' => 'Laboratorium Perkantoran OTKP', 'building' => 'Gedung B', 'department' => 'OTKP', 'description' => 'Lab simulasi perkantoran dan tata kelola arsip'],
        ];

        $locations = [];
        foreach ($locationsData as $loc) {
            $locations[$loc['code']] = Location::firstOrCreate(
                ['code' => $loc['code']],
                [
                    'name' => $loc['name'],
                    'building' => $loc['building'],
                    'department' => $loc['department'],
                    'description' => $loc['description'],
                ]
            );
        }

        // Ambil lokasi lama jika sudah ada
        $labRpl = Location::where('code', 'LAB-RPL-01')->first() ?? $locations['BENGKEL-TKRO-01'];
        $labTkj = Location::where('code', 'LAB-TKJ-01')->first() ?? $locations['BENGKEL-TKRO-01'];
        $gudang = Location::where('code', 'GDG-SARPRAS')->first() ?? $locations['BENGKEL-TKRO-01'];
        $ruangGuru = Location::where('code', 'R-GURU')->first() ?? $locations['BENGKEL-TKRO-01'];

        // 3. Data 23 Item Baru untuk Melengkapi Total 30 Barang
        $newItems = [
            // --- RPL (Total 4) ---
            [
                'code' => 'BRG-RPL-0003',
                'name' => 'Laptop Lenovo ThinkPad T14',
                'inventory_number' => 'INV-RPL-0003',
                'brand' => 'Lenovo',
                'model' => 'ThinkPad T14 Gen 4',
                'category' => 'Elektronik',
                'item_type' => 'individual',
                'unit' => 'Unit',
                'source' => 'pembelian',
                'acquisition_year' => 2024,
                'acquisition_price' => 14500000,
                'department' => 'RPL',
                'location_id' => $labRpl->id,
                'current_condition' => 'baik',
                'units' => [
                    ['condition' => 'baik', 'status' => 'aktif'],
                    ['condition' => 'baik', 'status' => 'aktif'],
                    ['condition' => 'rusak_ringan', 'status' => 'aktif'],
                ],
            ],
            [
                'code' => 'BRG-RPL-0004',
                'name' => 'PC All-in-One ASUS ExpertCenter',
                'inventory_number' => 'INV-RPL-0004',
                'brand' => 'ASUS',
                'model' => 'AIO V241EA',
                'category' => 'Elektronik',
                'item_type' => 'individual',
                'unit' => 'Unit',
                'source' => 'bantuan',
                'acquisition_year' => 2023,
                'acquisition_price' => 9800000,
                'department' => 'RPL',
                'location_id' => $labRpl->id,
                'current_condition' => 'baik',
                'units' => [
                    ['condition' => 'baik', 'status' => 'aktif'],
                    ['condition' => 'baik', 'status' => 'aktif'],
                ],
            ],

            // --- TKJ (Total 4) ---
            [
                'code' => 'BRG-TKJ-0003',
                'name' => 'Switch Manageable Cisco 24 Port',
                'inventory_number' => 'INV-TKJ-0003',
                'brand' => 'Cisco',
                'model' => 'Catalyst 2960-X',
                'category' => 'Elektronik',
                'item_type' => 'individual',
                'unit' => 'Unit',
                'source' => 'pembelian',
                'acquisition_year' => 2023,
                'acquisition_price' => 6200000,
                'department' => 'TKJ',
                'location_id' => $labTkj->id,
                'current_condition' => 'baik',
                'units' => [
                    ['condition' => 'baik', 'status' => 'aktif'],
                    ['condition' => 'baik', 'status' => 'aktif'],
                ],
            ],
            [
                'code' => 'BRG-TKJ-0004',
                'name' => 'Server Rack Dell PowerEdge',
                'inventory_number' => 'INV-TKJ-0004',
                'brand' => 'Dell',
                'model' => 'PowerEdge R440',
                'category' => 'Elektronik',
                'item_type' => 'individual',
                'unit' => 'Unit',
                'source' => 'pembelian',
                'acquisition_year' => 2022,
                'acquisition_price' => 28000000,
                'department' => 'TKJ',
                'location_id' => $labTkj->id,
                'current_condition' => 'baik',
                'units' => [
                    ['condition' => 'baik', 'status' => 'aktif'],
                ],
            ],

            // --- TKRO (Total 4) ---
            [
                'code' => 'BRG-TKRO-0001',
                'name' => 'Engine Stand Toyota Avanza 1.3',
                'inventory_number' => 'INV-TKRO-0001',
                'brand' => 'Toyota',
                'model' => 'Trainer 1NR-VE',
                'category' => 'Alat Praktik',
                'item_type' => 'individual',
                'unit' => 'Unit',
                'source' => 'bantuan',
                'acquisition_year' => 2023,
                'acquisition_price' => 32000000,
                'department' => 'TKRO',
                'location_id' => $locations['BENGKEL-TKRO-01']->id,
                'current_condition' => 'baik',
                'units' => [
                    ['condition' => 'baik', 'status' => 'aktif'],
                    ['condition' => 'baik', 'status' => 'aktif'],
                ],
            ],
            [
                'code' => 'BRG-TKRO-0002',
                'name' => 'Hydraulic Two Post Car Lift 4 Ton',
                'inventory_number' => 'INV-TKRO-0002',
                'brand' => 'Launch',
                'model' => 'TLT240SC',
                'category' => 'Alat Praktik',
                'item_type' => 'individual',
                'unit' => 'Unit',
                'source' => 'pembelian',
                'acquisition_year' => 2022,
                'acquisition_price' => 45000000,
                'department' => 'TKRO',
                'location_id' => $locations['BENGKEL-TKRO-01']->id,
                'current_condition' => 'baik',
                'units' => [
                    ['condition' => 'baik', 'status' => 'aktif'],
                ],
            ],
            [
                'code' => 'BRG-TKRO-0003',
                'name' => 'Scanner Diagnostik Mobil Launch X431',
                'inventory_number' => 'INV-TKRO-0003',
                'brand' => 'Launch',
                'model' => 'X431 PRO V4.0',
                'category' => 'Elektronik',
                'item_type' => 'individual',
                'unit' => 'Unit',
                'source' => 'pembelian',
                'acquisition_year' => 2024,
                'acquisition_price' => 16500000,
                'department' => 'TKRO',
                'location_id' => $locations['BENGKEL-TKRO-01']->id,
                'current_condition' => 'baik',
                'units' => [
                    ['condition' => 'baik', 'status' => 'aktif'],
                    ['condition' => 'rusak_ringan', 'status' => 'dalam_perbaikan'],
                ],
            ],
            [
                'code' => 'BRG-TKRO-0004',
                'name' => 'Kunci Shock Set Tekiro 24 Pcs',
                'inventory_number' => 'INV-TKRO-0004',
                'brand' => 'Tekiro',
                'model' => 'Socket Set 1/2 DR 24 Pcs',
                'category' => 'Peralatan Bengkel',
                'item_type' => 'individual',
                'unit' => 'Set',
                'source' => 'pembelian',
                'acquisition_year' => 2023,
                'acquisition_price' => 1250000,
                'department' => 'TKRO',
                'location_id' => $locations['BENGKEL-TKRO-01']->id,
                'current_condition' => 'baik',
                'units' => [
                    ['condition' => 'baik', 'status' => 'aktif'],
                    ['condition' => 'baik', 'status' => 'aktif'],
                    ['condition' => 'baik', 'status' => 'aktif'],
                    ['condition' => 'rusak_ringan', 'status' => 'aktif'],
                ],
            ],

            // --- TBSM (Total 4) ---
            [
                'code' => 'BRG-TBSM-0001',
                'name' => 'Sepeda Motor Praktik Honda Vario 160',
                'inventory_number' => 'INV-TBSM-0001',
                'brand' => 'Honda',
                'model' => 'Vario 160 CBS',
                'category' => 'Alat Praktik',
                'item_type' => 'individual',
                'unit' => 'Unit',
                'source' => 'bantuan',
                'acquisition_year' => 2023,
                'acquisition_price' => 26500000,
                'department' => 'TBSM',
                'location_id' => $locations['BENGKEL-TBSM-01']->id,
                'current_condition' => 'baik',
                'units' => [
                    ['condition' => 'baik', 'status' => 'aktif'],
                    ['condition' => 'baik', 'status' => 'aktif'],
                ],
            ],
            [
                'code' => 'BRG-TBSM-0002',
                'name' => 'Motorcycle Lift Table Pneumatik',
                'inventory_number' => 'INV-TBSM-0002',
                'brand' => 'Grip-On',
                'model' => 'Bike Lift 500KG',
                'category' => 'Peralatan Bengkel',
                'item_type' => 'individual',
                'unit' => 'Unit',
                'source' => 'pembelian',
                'acquisition_year' => 2022,
                'acquisition_price' => 8500000,
                'department' => 'TBSM',
                'location_id' => $locations['BENGKEL-TBSM-01']->id,
                'current_condition' => 'baik',
                'units' => [
                    ['condition' => 'baik', 'status' => 'aktif'],
                    ['condition' => 'rusak_ringan', 'status' => 'aktif'],
                ],
            ],
            [
                'code' => 'BRG-TBSM-0003',
                'name' => 'Scanner Injeksi Motor Universal Hids',
                'inventory_number' => 'INV-TBSM-0003',
                'brand' => 'JDiag',
                'model' => 'M100 Pro',
                'category' => 'Elektronik',
                'item_type' => 'individual',
                'unit' => 'Unit',
                'source' => 'pembelian',
                'acquisition_year' => 2024,
                'acquisition_price' => 4200000,
                'department' => 'TBSM',
                'location_id' => $locations['BENGKEL-TBSM-01']->id,
                'current_condition' => 'baik',
                'units' => [
                    ['condition' => 'baik', 'status' => 'aktif'],
                    ['condition' => 'baik', 'status' => 'aktif'],
                ],
            ],
            [
                'code' => 'BRG-TBSM-0004',
                'name' => 'Tyre Changer Machine Sepeda Motor',
                'inventory_number' => 'INV-TBSM-0004',
                'brand' => 'Flyman',
                'model' => 'TC-Moto 22',
                'category' => 'Peralatan Bengkel',
                'item_type' => 'individual',
                'unit' => 'Unit',
                'source' => 'bantuan',
                'acquisition_year' => 2023,
                'acquisition_price' => 14000000,
                'department' => 'TBSM',
                'location_id' => $locations['BENGKEL-TBSM-01']->id,
                'current_condition' => 'baik',
                'units' => [
                    ['condition' => 'baik', 'status' => 'aktif'],
                ],
            ],

            // --- AKL (Total 4) ---
            [
                'code' => 'BRG-AKL-0001',
                'name' => 'PC Komputer Akuntansi HP ProDesk',
                'inventory_number' => 'INV-AKL-0001',
                'brand' => 'HP',
                'model' => 'ProDesk 400 G7',
                'category' => 'Elektronik',
                'item_type' => 'individual',
                'unit' => 'Unit',
                'source' => 'pembelian',
                'acquisition_year' => 2023,
                'acquisition_price' => 11000000,
                'department' => 'AKL',
                'location_id' => $locations['LAB-AKL-01']->id,
                'current_condition' => 'baik',
                'units' => [
                    ['condition' => 'baik', 'status' => 'aktif'],
                    ['condition' => 'baik', 'status' => 'aktif'],
                    ['condition' => 'baik', 'status' => 'aktif'],
                ],
            ],
            [
                'code' => 'BRG-AKL-0002',
                'name' => 'Mesin Hitung Uang & Detektor Palsu',
                'inventory_number' => 'INV-AKL-0002',
                'brand' => 'Toshio',
                'model' => 'TK-9900',
                'category' => 'Peralatan Kantor',
                'item_type' => 'individual',
                'unit' => 'Unit',
                'source' => 'pembelian',
                'acquisition_year' => 2023,
                'acquisition_price' => 3800000,
                'department' => 'AKL',
                'location_id' => $locations['LAB-AKL-01']->id,
                'current_condition' => 'baik',
                'units' => [
                    ['condition' => 'baik', 'status' => 'aktif'],
                    ['condition' => 'rusak_ringan', 'status' => 'aktif'],
                ],
            ],
            [
                'code' => 'BRG-AKL-0003',
                'name' => 'Kalkulator Finansial Citizen Karat',
                'inventory_number' => 'INV-AKL-0003',
                'brand' => 'Citizen',
                'model' => 'FC-100V',
                'category' => 'Elektronik',
                'item_type' => 'individual',
                'unit' => 'Unit',
                'source' => 'pembelian',
                'acquisition_year' => 2024,
                'acquisition_price' => 450000,
                'department' => 'AKL',
                'location_id' => $locations['LAB-AKL-01']->id,
                'current_condition' => 'baik',
                'units' => [
                    ['condition' => 'baik', 'status' => 'aktif'],
                    ['condition' => 'baik', 'status' => 'aktif'],
                    ['condition' => 'baik', 'status' => 'aktif'],
                    ['condition' => 'rusak_berat', 'status' => 'dalam_perbaikan'],
                ],
            ],
            [
                'code' => 'BRG-AKL-0004',
                'name' => 'Mesin Kasir POS Touchscreen',
                'inventory_number' => 'INV-AKL-0004',
                'brand' => 'Sunmi',
                'model' => 'D2s Plus',
                'category' => 'Elektronik',
                'item_type' => 'individual',
                'unit' => 'Unit',
                'source' => 'bantuan',
                'acquisition_year' => 2024,
                'acquisition_price' => 5200000,
                'department' => 'AKL',
                'location_id' => $locations['LAB-AKL-01']->id,
                'current_condition' => 'baik',
                'units' => [
                    ['condition' => 'baik', 'status' => 'aktif'],
                    ['condition' => 'baik', 'status' => 'aktif'],
                ],
            ],

            // --- OTKP (Total 4) ---
            [
                'code' => 'BRG-OTKP-0001',
                'name' => 'Mesin Penghancur Kertas Paper Shredder',
                'inventory_number' => 'INV-OTKP-0001',
                'brand' => 'GBC',
                'model' => 'ShredMaster X312',
                'category' => 'Peralatan Kantor',
                'item_type' => 'individual',
                'unit' => 'Unit',
                'source' => 'pembelian',
                'acquisition_year' => 2023,
                'acquisition_price' => 2600000,
                'department' => 'OTKP',
                'location_id' => $locations['LAB-OTKP-01']->id,
                'current_condition' => 'baik',
                'units' => [
                    ['condition' => 'baik', 'status' => 'aktif'],
                    ['condition' => 'baik', 'status' => 'aktif'],
                ],
            ],
            [
                'code' => 'BRG-OTKP-0002',
                'name' => 'Mesin Laminating Roll Promax',
                'inventory_number' => 'INV-OTKP-0002',
                'brand' => 'Promax',
                'model' => 'Lami-330 Pro',
                'category' => 'Peralatan Kantor',
                'item_type' => 'individual',
                'unit' => 'Unit',
                'source' => 'pembelian',
                'acquisition_year' => 2022,
                'acquisition_price' => 1750000,
                'department' => 'OTKP',
                'location_id' => $locations['LAB-OTKP-01']->id,
                'current_condition' => 'baik',
                'units' => [
                    ['condition' => 'baik', 'status' => 'aktif'],
                ],
            ],
            [
                'code' => 'BRG-OTKP-0003',
                'name' => 'Printer Multifungsi Canon PIXMA G3010',
                'inventory_number' => 'INV-OTKP-0003',
                'brand' => 'Canon',
                'model' => 'PIXMA G3010 Wi-Fi',
                'category' => 'Elektronik',
                'item_type' => 'individual',
                'unit' => 'Unit',
                'source' => 'pembelian',
                'acquisition_year' => 2023,
                'acquisition_price' => 2300000,
                'department' => 'OTKP',
                'location_id' => $locations['LAB-OTKP-01']->id,
                'current_condition' => 'baik',
                'units' => [
                    ['condition' => 'baik', 'status' => 'aktif'],
                    ['condition' => 'rusak_ringan', 'status' => 'aktif'],
                ],
            ],
            [
                'code' => 'BRG-OTKP-0004',
                'name' => 'Binding Machine Mesin Jilid Kawat',
                'inventory_number' => 'INV-OTKP-0004',
                'brand' => 'Origin',
                'model' => 'Or-Wire 21',
                'category' => 'Peralatan Kantor',
                'item_type' => 'individual',
                'unit' => 'Unit',
                'source' => 'pembelian',
                'acquisition_year' => 2024,
                'acquisition_price' => 1950000,
                'department' => 'OTKP',
                'location_id' => $locations['LAB-OTKP-01']->id,
                'current_condition' => 'baik',
                'units' => [
                    ['condition' => 'baik', 'status' => 'aktif'],
                    ['condition' => 'baik', 'status' => 'aktif'],
                ],
            ],

            // --- UMUM (Total 6) ---
            [
                'code' => 'BRG-UMM-0004',
                'name' => 'Sound System Portable Wireless Baretone',
                'inventory_number' => 'INV-UMM-0004',
                'brand' => 'Baretone',
                'model' => 'BT-3H1515BWR',
                'category' => 'Elektronik',
                'item_type' => 'individual',
                'unit' => 'Unit',
                'source' => 'pembelian',
                'acquisition_year' => 2023,
                'acquisition_price' => 4500000,
                'department' => null,
                'location_id' => $gudang->id,
                'current_condition' => 'baik',
                'units' => [
                    ['condition' => 'baik', 'status' => 'aktif'],
                    ['condition' => 'baik', 'status' => 'aktif'],
                ],
            ],
            [
                'code' => 'BRG-UMM-0005',
                'name' => 'Kursi Lipat Chitose Futura',
                'inventory_number' => 'INV-UMM-0005',
                'brand' => 'Chitose',
                'model' => 'Futura FTR-747',
                'category' => 'Mebel',
                'item_type' => 'individual',
                'unit' => 'Unit',
                'source' => 'pembelian',
                'acquisition_year' => 2022,
                'acquisition_price' => 380000,
                'department' => null,
                'location_id' => $gudang->id,
                'current_condition' => 'baik',
                'units' => [
                    ['condition' => 'baik', 'status' => 'aktif'],
                    ['condition' => 'baik', 'status' => 'aktif'],
                    ['condition' => 'baik', 'status' => 'aktif'],
                    ['condition' => 'baik', 'status' => 'aktif'],
                    ['condition' => 'rusak_ringan', 'status' => 'aktif'],
                ],
            ],
            [
                'code' => 'BRG-UMM-0006',
                'name' => 'Meja Rapat Guru Kayu Jati',
                'inventory_number' => 'INV-UMM-0006',
                'brand' => 'Jepara Mebel',
                'model' => 'Oval Conference Table 3M',
                'category' => 'Mebel',
                'item_type' => 'individual',
                'unit' => 'Unit',
                'source' => 'pembelian',
                'acquisition_year' => 2021,
                'acquisition_price' => 8500000,
                'department' => null,
                'location_id' => $ruangGuru->id,
                'current_condition' => 'baik',
                'units' => [
                    ['condition' => 'baik', 'status' => 'aktif'],
                ],
            ],
        ];

        foreach ($newItems as $data) {
            if (Item::where('code', $data['code'])->exists()) {
                continue;
            }

            $units = $data['units'];
            unset($data['units']);

            $item = new Item($data);
            $item->stock = count(array_filter($units, fn ($u) => ($u['status'] ?? 'aktif') !== 'disposed'));
            $item->save();

            LocationHistory::create([
                'item_id' => $item->id,
                'from_location_id' => null,
                'to_location_id' => $data['location_id'],
                'user_id' => $sarpras->id,
                'notes' => 'Pencatatan inventaris awal',
                'moved_at' => now()->subMonths(6),
            ]);

            foreach ($units as $index => $u) {
                $uNum = $item->inventory_number.'-U'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);

                $assetUnit = AssetUnit::create([
                    'item_id' => $item->id,
                    'unit_inventory_number' => $uNum,
                    'serial_number' => null,
                    'current_condition' => $u['condition'],
                    'current_status' => $u['status'],
                    'location_id' => $data['location_id'],
                    'is_legacy_migrated' => false,
                    'notes' => 'Unit demo inventaris',
                ]);

                ConditionHistory::create([
                    'item_id' => $item->id,
                    'asset_unit_id' => $assetUnit->id,
                    'from_condition' => $u['condition'],
                    'to_condition' => $u['condition'],
                    'user_id' => $sarpras->id,
                    'notes' => 'Kondisi fisik saat registrasi aset',
                    'recorded_at' => now()->subMonths(5),
                ]);
            }

            $item->current_condition = $item->recalculateAggregateCondition();
            $item->current_status = $item->recalculateAggregateStatus();
            $item->save();

            ActivityLog::log(
                'item_created',
                "Barang baru didaftarkan: {$item->name} ({$item->code})",
                $item,
                [],
                ['code' => $item->code, 'name' => $item->name, 'stock' => $item->stock],
                $item->code
            );
        }
    }
}

