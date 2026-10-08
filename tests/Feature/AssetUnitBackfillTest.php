<?php

namespace Tests\Feature;

use App\Models\AssetUnit;
use App\Models\ConditionHistory;
use App\Models\Item;
use App\Models\Loan;
use App\Models\Location;
use App\Models\LocationHistory;
use App\Models\User;
use App\Services\AssetUnitBackfiller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetUnitBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_database_backfill_is_noop(): void
    {
        $result = app(AssetUnitBackfiller::class)->run();

        $this->assertSame(0, $result['expected']);
        $this->assertSame(0, $result['created']);
        $this->assertSame(0, AssetUnit::count());
    }

    public function test_legacy_individual_items_are_backfilled_into_units(): void
    {
        $user = User::factory()->sarpras()->create();
        $lab = Location::factory()->create(['name' => 'Lab RPL']);

        $laptop = $this->individualItem([
            'code' => 'BRG-LAP',
            'name' => 'Laptop Lenovo',
            'inventory_number' => 'INV-RPL-LPT-001',
            'serial_number' => 'SN-LPT-1001',
            'stock' => 2,
            'department' => 'RPL',
            'location_id' => $lab->id,
            'current_status' => 'aktif',
        ]);

        $kamera = $this->individualItem([
            'code' => 'BRG-KAM',
            'name' => 'Kamera Canon',
            'stock' => 3,
            'department' => 'TKJ',
            'current_status' => 'aktif',
        ]);

        $consumable = Item::factory()->create(['stock' => 50]);

        Loan::factory()->create(['item_id' => $laptop->id, 'quantity' => 1, 'status' => 'dipinjam', 'recorded_by' => $user->id]);
        Loan::factory()->create(['item_id' => $consumable->id, 'quantity' => 5, 'status' => 'menunggu', 'recorded_by' => $user->id]);

        ConditionHistory::create([
            'item_id' => $laptop->id,
            'from_condition' => 'baik',
            'to_condition' => 'baik',
            'user_id' => $user->id,
            'recorded_at' => now(),
        ]);
        LocationHistory::create([
            'item_id' => $laptop->id,
            'from_location_id' => null,
            'to_location_id' => $lab->id,
            'user_id' => $user->id,
            'moved_at' => now(),
        ]);

        $result = app(AssetUnitBackfiller::class)->run();

        $this->assertSame(5, $result['expected']);
        $this->assertSame(5, $result['created']);

        $laptopUnits = AssetUnit::where('item_id', $laptop->id)->orderBy('id')->get();
        $this->assertCount(2, $laptopUnits);
        $this->assertSame('INV-RPL-LPT-001', $laptopUnits[0]->unit_inventory_number);
        $this->assertSame('SN-LPT-1001', $laptopUnits[0]->serial_number);
        $this->assertSame('BRG-LAP-002', $laptopUnits[1]->unit_inventory_number);
        $this->assertNull($laptopUnits[1]->serial_number);
        $this->assertTrue($laptopUnits[0]->is_legacy_migrated);
        $this->assertTrue($laptopUnits[1]->is_legacy_migrated);

        $kameraUnits = AssetUnit::where('item_id', $kamera->id)->orderBy('id')->get();
        $this->assertCount(3, $kameraUnits);
        $this->assertSame(['BRG-KAM-001', 'BRG-KAM-002', 'BRG-KAM-003'], $kameraUnits->pluck('unit_inventory_number')->all());

        $this->assertSame(2, $laptop->fresh()->stock);
        $this->assertSame(0, AssetUnit::where('item_id', $consumable->id)->count());
    }

    public function test_legacy_loans_and_histories_are_mapped_to_first_unit(): void
    {
        $user = User::factory()->sarpras()->create();
        $laptop = $this->individualItem(['code' => 'BRG-LAP', 'stock' => 2, 'inventory_number' => 'INV-RPL-LPT-001']);

        $loan = Loan::factory()->create(['item_id' => $laptop->id, 'quantity' => 1, 'status' => 'dipinjam', 'recorded_by' => $user->id]);
        $consumable = Item::factory()->create(['stock' => 5]);
        $consumableLoan = Loan::factory()->create(['item_id' => $consumable->id, 'quantity' => 2, 'status' => 'menunggu', 'recorded_by' => $user->id]);

        app(AssetUnitBackfiller::class)->run();

        $firstUnit = AssetUnit::where('item_id', $laptop->id)->orderBy('id')->first();
        $this->assertSame($firstUnit->id, Loan::whereKey($loan->id)->value('asset_unit_id'));
        $this->assertNull(Loan::whereKey($consumableLoan->id)->value('asset_unit_id'));
    }

    public function test_backfill_is_idempotent_when_re_run(): void
    {
        $this->individualItem(['code' => 'BRG-LAP', 'stock' => 2, 'inventory_number' => 'INV-RPL-LPT-001']);
        $this->individualItem(['code' => 'BRG-KAM', 'stock' => 3]);

        $first = app(AssetUnitBackfiller::class)->run();
        $second = app(AssetUnitBackfiller::class)->run();

        $this->assertSame(5, $first['created']);
        $this->assertSame(5, $second['expected']);
        $this->assertSame(0, $second['created']);
        $this->assertSame(5, AssetUnit::count());
    }

    public function test_stock_zero_without_inventory_number_creates_no_units(): void
    {
        $item = $this->individualItem(['code' => 'BRG-BTN', 'stock' => 0]);

        $result = app(AssetUnitBackfiller::class)->run();

        $this->assertSame(1, Item::where('code', 'BRG-BTN')->count());
        $this->assertSame(0, $result['expected']);
        $this->assertSame(0, $result['created']);
        $this->assertSame(0, AssetUnit::where('item_id', $item->id)->count());
    }

    public function test_individual_item_with_stock_greater_than_one_and_borrowed_status_only_marks_loaned_unit_as_borrowed(): void
    {
        $user = User::factory()->sarpras()->create();
        $item = $this->individualItem([
            'code' => 'BRG-LPT',
            'stock' => 3,
            'current_status' => 'dipinjam',
        ]);

        $loan = Loan::factory()->create([
            'item_id' => $item->id,
            'quantity' => 1,
            'status' => 'dipinjam',
            'recorded_by' => $user->id,
        ]);

        app(AssetUnitBackfiller::class)->run();

        $units = AssetUnit::where('item_id', $item->id)->orderBy('id')->get();
        $this->assertCount(3, $units);

        $borrowedUnits = $units->where('current_status', 'dipinjam');
        $activeUnits = $units->where('current_status', 'aktif');

        $this->assertCount(1, $borrowedUnits);
        $this->assertCount(2, $activeUnits);
        $this->assertSame($units[0]->id, $borrowedUnits->first()->id);
        $this->assertSame($units[0]->id, Loan::whereKey($loan->id)->value('asset_unit_id'));
    }

    public function test_individual_item_borrowed_without_active_loans_backfills_all_units_as_active(): void
    {
        $item = $this->individualItem([
            'code' => 'BRG-CAM',
            'stock' => 3,
            'current_status' => 'dipinjam',
        ]);

        app(AssetUnitBackfiller::class)->run();

        $units = AssetUnit::where('item_id', $item->id)->orderBy('id')->get();
        $this->assertCount(3, $units);
        $this->assertSame(['aktif', 'aktif', 'aktif'], $units->pluck('current_status')->all());
        $this->assertSame('aktif', $item->fresh()->current_status);
    }

    public function test_corrective_migration_fixes_legacy_migrated_units_without_active_loans(): void
    {
        $user = User::factory()->sarpras()->create();
        $item = $this->individualItem([
            'code' => 'BRG-TAB',
            'stock' => 3,
        ]);

        // Simulasikan state "salah" (3 unit 'dipinjam' dengan is_legacy_migrated = true)
        $unit1 = AssetUnit::create([
            'item_id' => $item->id,
            'unit_inventory_number' => 'BRG-TAB-001',
            'current_condition' => 'baik',
            'current_status' => 'dipinjam',
            'is_legacy_migrated' => true,
        ]);
        $unit2 = AssetUnit::create([
            'item_id' => $item->id,
            'unit_inventory_number' => 'BRG-TAB-002',
            'current_condition' => 'baik',
            'current_status' => 'dipinjam',
            'is_legacy_migrated' => true,
        ]);
        $unit3 = AssetUnit::create([
            'item_id' => $item->id,
            'unit_inventory_number' => 'BRG-TAB-003',
            'current_condition' => 'baik',
            'current_status' => 'dipinjam',
            'is_legacy_migrated' => true,
        ]);

        // 1 loan aktif terhubung ke unit 1
        Loan::factory()->create([
            'item_id' => $item->id,
            'asset_unit_id' => $unit1->id,
            'quantity' => 1,
            'status' => 'dipinjam',
            'recorded_by' => $user->id,
        ]);

        // Jalankan migration korektif pertama kali
        $migration = require database_path('migrations/2026_10_08_000001_fix_legacy_migrated_asset_units_loan_status.php');
        ob_start();
        $migration->up();
        ob_end_clean();

        $this->assertSame('dipinjam', $unit1->fresh()->current_status);
        $this->assertSame('aktif', $unit2->fresh()->current_status);
        $this->assertSame('aktif', $unit3->fresh()->current_status);

        // Jalankan migration korektif kedua kali (idempotent)
        ob_start();
        $migration->up();
        ob_end_clean();

        $this->assertSame('dipinjam', $unit1->fresh()->current_status);
        $this->assertSame('aktif', $unit2->fresh()->current_status);
        $this->assertSame('aktif', $unit3->fresh()->current_status);
    }

    public function test_corrective_migration_does_not_touch_manually_registered_units(): void
    {
        $item = $this->individualItem(['code' => 'BRG-MAN', 'stock' => 1]);

        $manualUnit = AssetUnit::create([
            'item_id' => $item->id,
            'unit_inventory_number' => 'BRG-MAN-001',
            'current_condition' => 'baik',
            'current_status' => 'dipinjam',
            'is_legacy_migrated' => false,
        ]);

        $migration = require database_path('migrations/2026_10_08_000001_fix_legacy_migrated_asset_units_loan_status.php');
        ob_start();
        $migration->up();
        ob_end_clean();

        $this->assertSame('dipinjam', $manualUnit->fresh()->current_status);
    }

    private function individualItem(array $attributes = []): Item
    {
        $stock = $attributes['stock'] ?? 10;
        unset($attributes['stock']);

        $item = Item::factory()->create(array_merge([
            'item_type' => 'individual',
            'unit' => 'Unit',
            'category' => 'Elektronik',
        ], $attributes));

        $item->forceFill(['stock' => $stock])->save();

        return $item;
    }
}
