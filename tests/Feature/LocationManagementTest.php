<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Location;
use App\Models\LocationHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_location_crud_accessible_by_sarpras_only(): void
    {
        $sarpras = User::factory()->sarpras()->create();
        $kajur = User::factory()->kajur()->create();
        $kepsek = User::factory()->state(['role' => 'kepala_sekolah', 'department' => null])->create();

        $this->actingAs($kajur)->get(route('sarpras.locations.index'))->assertForbidden();
        $this->actingAs($kepsek)->get(route('sarpras.locations.index'))->assertForbidden();
        $this->actingAs($sarpras)->get(route('sarpras.locations.index'))->assertOk();
    }

    public function test_location_with_items_cannot_be_deleted(): void
    {
        $sarpras = User::factory()->sarpras()->create();
        $location = Location::factory()->create();
        $item = Item::factory()->create(['location_id' => $location->id]);

        $this->actingAs($sarpras)
            ->delete(route('sarpras.locations.destroy', $location))
            ->assertSessionHasErrors();

        $this->assertDatabaseHas('locations', [
            'id' => $location->id,
            'deleted_at' => null,
        ]);
    }

    public function test_location_with_asset_units_cannot_be_deleted(): void
    {
        $sarpras = User::factory()->sarpras()->create();
        $location = Location::factory()->create();
        $otherLocation = Location::factory()->create();

        $item = Item::factory()->create(['location_id' => $otherLocation->id]);
        \App\Models\AssetUnit::create([
            'item_id' => $item->id,
            'unit_inventory_number' => 'UNIT-LOC-01',
            'current_condition' => 'baik',
            'current_status' => 'aktif',
            'location_id' => $location->id,
        ]);

        $this->actingAs($sarpras)
            ->delete(route('sarpras.locations.destroy', $location))
            ->assertSessionHasErrors();

        $this->assertDatabaseHas('locations', [
            'id' => $location->id,
            'deleted_at' => null,
        ]);
    }

    public function test_soft_deleted_location_not_allowed_as_target_in_inventory_move(): void
    {
        $sarpras = User::factory()->sarpras()->create();
        $location = Location::factory()->create();
        $deleted = Location::factory()->create();
        $deleted->delete();

        $item = Item::factory()->create(['location_id' => $location->id]);

        $this->actingAs($sarpras)
            ->post(route('sarpras.inventory.updateLocation', $item), [
                'to_location_id' => $deleted->id,
                'notes' => 'test',
            ])
            ->assertSessionHasErrors('to_location_id');
    }

    public function test_location_history_remains_after_location_soft_deleted(): void
    {
        $sarpras = User::factory()->sarpras()->create();
        $from = Location::factory()->create(['name' => 'Ruang A']);
        $to = Location::factory()->create(['name' => 'Ruang B']);
        $item = Item::factory()->create(['location_id' => $from->id]);

        $this->actingAs($sarpras)
            ->post(route('sarpras.inventory.updateLocation', $item), [
                'to_location_id' => $to->id,
                'notes' => 'Pindah ruang',
            ])
            ->assertRedirect();

        $history = LocationHistory::first();
        $this->assertNotNull($history);

        $to->delete();

        $this->assertSoftDeleted('locations', ['id' => $to->id]);
        $this->assertDatabaseHas('location_histories', [
            'id' => $history->id,
            'to_location_id' => $to->id,
        ]);
    }
}
