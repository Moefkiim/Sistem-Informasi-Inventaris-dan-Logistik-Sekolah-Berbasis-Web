<?php

namespace Tests\Feature;

use App\Models\AssetUnit;
use App\Models\Item;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetUnitReportTest extends TestCase
{
    use RefreshDatabase;

    private function sarpras(): User
    {
        return User::factory()->sarpras()->create();
    }

    private function itemWithUnits(int $unitCount, array $attributes = []): Item
    {
        $code = $attributes['code'] ?? 'BRG-UNIT';
        $location = Location::factory()->create(['name' => 'Lab RPL']);

        $item = Item::factory()->create(array_merge([
            'item_type' => 'individual',
            'unit' => 'Unit',
            'category' => 'Elektronik',
            'current_status' => 'aktif',
            'current_condition' => 'baik',
            'location_id' => $location->id,
            'department' => 'RPL',
        ], $attributes));

        for ($i = 1; $i <= $unitCount; $i++) {
            AssetUnit::create([
                'item_id' => $item->id,
                'unit_inventory_number' => $code.'-U'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'current_condition' => 'baik',
                'current_status' => 'aktif',
                'location_id' => $location->id,
            ]);
        }

        $item->update(['location_id' => $location->id]);

        return $item;
    }

    public function test_dashboard_counts_individual_assets_per_unit(): void
    {
        $item = $this->itemWithUnits(3, ['code' => 'BRG-LAP', 'name' => 'Laptop Lab']);
        $units = $item->assetUnits()->orderBy('id')->get();
        $units[0]->update(['current_status' => 'dipinjam']);
        $units[1]->update(['current_status' => 'dalam_perbaikan']);

        $sarpras = $this->sarpras();

        $response = $this->actingAs($sarpras)->get(route('home'));

        // totalItems (individu dihitung per unit = 3)
        $this->assertSame(3, $response->viewData('totalItems'));

        $charts = $response->viewData('charts');
        $this->assertSame(['Aktif', 'Dipinjam', 'Dalam Perbaikan'], $charts['conditions']['labels']);
        $this->assertSame([1, 1, 1], $charts['conditions']['data']);
        $this->assertSame(['Lab RPL'], $charts['locations']['labels']);
        $this->assertSame([3], $charts['locations']['data']);
    }

    public function test_consumable_item_is_counted_once_in_dashboard(): void
    {
        Item::factory()->create([
            'item_type' => 'consumable',
            'stock' => 25,
            'name' => 'Tinta Printer',
            'department' => 'Umum',
        ]);

        $response = $this->actingAs($this->sarpras())->get(route('home'));

        $this->assertSame(1, $response->viewData('totalItems'));
        $charts = $response->viewData('charts');
        $this->assertSame([1], $charts['conditions']['data']);
    }

    public function test_inventory_report_expands_individual_item_into_unit_rows(): void
    {
        $item = $this->itemWithUnits(2, ['code' => 'BRG-KAM', 'name' => 'Kamera Lab']);
        $units = $item->assetUnits()->orderBy('id')->get();
        $units[1]->update(['serial_number' => 'SN-KAM-002', 'current_condition' => 'rusak_ringan']);

        $this->actingAs($this->sarpras())
            ->get(route('reports.index', ['type' => 'inventory', 'export' => 'print']))
            ->assertOk()
            ->assertSee('BRG-KAM-U01')
            ->assertSee('BRG-KAM-U02')
            ->assertSee('SN-KAM-002')
            ->assertSee('Rusak ringan')
            ->assertSee('Lab RPL');
    }

    public function test_report_print_view_renders_unit_expansion_for_kajur_scoping(): void
    {
        $kajur = User::factory()->kajur()->create(['department' => 'RPL']);
        $this->itemWithUnits(2, ['code' => 'BRG-LAP', 'department' => 'RPL']);
        $this->itemWithUnits(1, ['code' => 'BRG-PRC', 'department' => 'TKJ']);

        $response = $this->actingAs($kajur)
            ->get(route('reports.index', ['type' => 'inventory', 'export' => 'print']))
            ->assertOk()
            ->assertSee('BRG-LAP-U01')
            ->assertSee('BRG-LAP-U02')
            ->assertDontSee('BRG-PRC-U01');
    }
}
