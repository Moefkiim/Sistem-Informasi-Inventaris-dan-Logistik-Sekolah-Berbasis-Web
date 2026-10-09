<?php

namespace Tests\Feature;

use App\Models\AssetUnit;
use App\Models\ConditionHistory;
use App\Models\Item;
use App\Models\Location;
use App\Models\LocationHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KajurTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_kajur_cannot_access_other_department_item(): void
    {
        $rpl = User::factory()->kajur()->create(['department' => 'RPL']);
        $tkj = User::factory()->kajur()->create(['department' => 'TKJ']);

        $itemRpl = Item::factory()->create(['department' => 'RPL']);
        $itemTkj = Item::factory()->create(['department' => 'TKJ']);

        $this->actingAs($rpl)
            ->get(route('kajur.inventory.show', $itemTkj))
            ->assertForbidden();

        $response = $this->actingAs($rpl)
            ->get(route('kajur.inventory.index'))
            ->assertOk();
        $response->assertDontSee($itemTkj->name);
        $response->assertSee($itemRpl->name);
    }

    public function test_kajur_with_null_department_gets_forbidden(): void
    {
        $kajur = User::factory()->kajur()->create(['department' => null]);
        $item = Item::factory()->create(['department' => 'RPL']);

        $this->actingAs($kajur)
            ->get(route('kajur.inventory.index'))
            ->assertForbidden();

        $this->actingAs($kajur)
            ->get(route('kajur.inventory.show', $item))
            ->assertForbidden();
    }

    public function test_kajur_inventory_index_expands_individual_item_into_units(): void
    {
        $rpl = User::factory()->kajur()->create(['department' => 'RPL']);
        $location = Location::factory()->create(['name' => 'Lab RPL']);

        $item = Item::factory()->create([
            'department' => 'RPL',
            'item_type' => 'individual',
            'unit' => 'Unit',
            'stock' => 2,
            'location_id' => $location->id,
            'current_condition' => 'baik',
            'current_status' => 'aktif',
        ]);

        $units = [
            AssetUnit::create(['item_id' => $item->id, 'unit_inventory_number' => 'RPLLPT-01', 'serial_number' => 'SN-01', 'current_condition' => 'baik', 'current_status' => 'aktif', 'location_id' => $location->id]),
            AssetUnit::create(['item_id' => $item->id, 'unit_inventory_number' => 'RPLLPT-02', 'serial_number' => 'SN-02', 'current_condition' => 'rusak_ringan', 'current_status' => 'dipinjam', 'location_id' => $location->id]),
        ];
        $item->update(['location_id' => $location->id]);

        $response = $this->actingAs($rpl)->get(route('kajur.inventory.index'))->assertOk();

        $response->assertSee('RPLLPT-01')->assertSee('RPLLPT-02');
        $response->assertSee('SN-01')->assertSee('SN-02');
        $response->assertSee('Rusak Ringan')->assertSee('Dipinjam');
        $response->assertSee('Lab RPL');
        $response->assertDontSee('TKJ');
    }

    public function test_kajur_inventory_index_shows_consumable_as_single_row(): void
    {
        $rpl = User::factory()->kajur()->create(['department' => 'RPL']);
        Item::factory()->create([
            'department' => 'RPL',
            'item_type' => 'consumable',
            'unit' => 'Rim',
            'stock' => 25,
            'name' => 'Kertas A4',
        ]);

        $response = $this->actingAs($rpl)->get(route('kajur.inventory.index'))->assertOk();
        $response->assertSee('25 Rim')->assertSee('Kertas A4');
    }

    public function test_kajur_inventory_show_displays_unit_list_and_unit_histories(): void
    {
        $rpl = User::factory()->kajur()->create(['department' => 'RPL']);
        $location = Location::factory()->create(['name' => 'Lab RPL']);

        $item = Item::factory()->create([
            'department' => 'RPL',
            'item_type' => 'individual',
            'unit' => 'Unit',
            'stock' => 1,
            'location_id' => $location->id,
        ]);
        $unit = AssetUnit::create([
            'item_id' => $item->id,
            'unit_inventory_number' => 'RPLLPT-01',
            'current_condition' => 'baik',
            'current_status' => 'aktif',
            'location_id' => $location->id,
        ]);

        LocationHistory::create([
            'item_id' => $item->id,
            'asset_unit_id' => $unit->id,
            'from_location_id' => null,
            'to_location_id' => $location->id,
            'moved_at' => now(),
            'user_id' => $rpl->id,
        ]);
        ConditionHistory::create([
            'item_id' => $item->id,
            'asset_unit_id' => $unit->id,
            'from_condition' => 'baik',
            'to_condition' => 'rusak_ringan',
            'notes' => null,
            'recorded_at' => now()->subDay(),
            'user_id' => $rpl->id,
        ]);

        $response = $this->actingAs($rpl)
            ->get(route('kajur.inventory.show', $item))
            ->assertOk();

        $response->assertSee('Daftar Unit Aset');
        $response->assertSee('RPLLPT-01');
        $response->assertSee('rusak ringan');
        $response->assertSee('Lab RPL');
    }
}
