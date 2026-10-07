<?php

namespace Tests\Feature;

use App\Models\AssetUnit;
use App\Models\Item;
use App\Models\Location;
use App\Models\LocationHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetUnitLocationTest extends TestCase
{
    use RefreshDatabase;

    private function sarpras(): User
    {
        return User::factory()->sarpras()->create();
    }

    private function itemWithUnits(int $unitCount, array $attributes = []): Item
    {
        $code = $attributes['code'] ?? 'BRG-UNIT';

        $item = Item::factory()->create(array_merge([
            'item_type' => 'individual',
            'unit' => 'Unit',
            'current_status' => 'aktif',
            'current_condition' => 'baik',
        ], $attributes));

        $locations = Location::factory()->count(max(1, $unitCount))->create();

        for ($i = 1; $i <= $unitCount; $i++) {
            AssetUnit::create([
                'item_id' => $item->id,
                'unit_inventory_number' => $code.'-U'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'current_condition' => 'baik',
                'current_status' => 'aktif',
                'location_id' => $locations[$i - 1]->id,
            ]);
        }

        $item->update(['location_id' => $item->assetUnits()->orderBy('id')->value('location_id')]);

        return $item;
    }

    public function test_unit_location_can_be_moved_with_history(): void
    {
        $labA = Location::factory()->create(['name' => 'Lab A']);
        $labB = Location::factory()->create(['name' => 'Lab B']);

        $item = Item::factory()->create([
            'item_type' => 'individual',
            'code' => 'BRG-LAP',
            'unit' => 'Unit',
            'current_status' => 'aktif',
            'current_condition' => 'baik',
            'location_id' => $labA->id,
        ]);
        $unit = AssetUnit::create([
            'item_id' => $item->id,
            'unit_inventory_number' => 'BRG-LAP-U01',
            'current_condition' => 'baik',
            'current_status' => 'aktif',
            'location_id' => $labA->id,
        ]);
        $item->update(['location_id' => $unit->location_id]);

        $this->actingAs($this->sarpras())
            ->post(route('sarpras.inventory.updateLocation', $item), [
                'asset_unit_id' => $unit->id,
                'to_location_id' => $labB->id,
                'notes' => 'Pindah ruang praktik',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame($labB->id, $unit->fresh()->location_id);

        $this->assertDatabaseHas('location_histories', [
            'item_id' => $item->id,
            'asset_unit_id' => $unit->id,
            'from_location_id' => $labA->id,
            'to_location_id' => $labB->id,
        ]);
    }

    public function test_other_unit_location_is_not_affected(): void
    {
        $item = $this->itemWithUnits(2, ['code' => 'BRG-KAM']);
        $units = $item->assetUnits()->orderBy('id')->get();
        $target = Location::factory()->create(['name' => 'Gudang Sarana']);

        $this->actingAs($this->sarpras())
            ->post(route('sarpras.inventory.updateLocation', $item), [
                'asset_unit_id' => $units[1]->id,
                'to_location_id' => $target->id,
            ])
            ->assertRedirect();

        $this->assertNotSame($target->id, $units[0]->fresh()->location_id);
        $this->assertSame($target->id, $units[1]->fresh()->location_id);
    }

    public function test_unit_selection_required_when_item_has_units(): void
    {
        $item = $this->itemWithUnits(1, ['code' => 'BRG-PRC']);
        $target = Location::factory()->create(['name' => 'Ruang Server']);

        $this->actingAs($this->sarpras())
            ->post(route('sarpras.inventory.updateLocation', $item), [
                'to_location_id' => $target->id,
            ])
            ->assertSessionHasErrors('asset_unit_id');

        $this->assertSame(0, LocationHistory::count());
    }

    public function test_unit_selection_must_belong_to_item(): void
    {
        $itemA = $this->itemWithUnits(1, ['code' => 'BRG-LPT']);
        $itemB = $this->itemWithUnits(1, ['code' => 'BRG-LTN']);
        $target = Location::factory()->create(['name' => 'Ruang Guru']);

        $this->actingAs($this->sarpras())
            ->post(route('sarpras.inventory.updateLocation', $itemA), [
                'asset_unit_id' => $itemB->assetUnits()->firstOrFail()->id,
                'to_location_id' => $target->id,
            ])
            ->assertSessionHasErrors('asset_unit_id');

        $this->assertSame(0, LocationHistory::count());
    }

    public function test_same_unit_location_is_rejected(): void
    {
        $item = $this->itemWithUnits(1, ['code' => 'BRG-PRJ']);
        $unit = $item->assetUnits()->firstOrFail();

        $this->actingAs($this->sarpras())
            ->post(route('sarpras.inventory.updateLocation', $item), [
                'asset_unit_id' => $unit->id,
                'to_location_id' => $unit->location_id,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('msg');

        $this->assertSame(0, LocationHistory::count());
    }

    public function test_legacy_item_without_units_keeps_item_level_move(): void
    {
        $from = Location::factory()->create(['name' => 'Lab A']);
        $to = Location::factory()->create(['name' => 'Lab B']);
        $item = Item::factory()->create([
            'item_type' => 'individual',
            'location_id' => $from->id,
            'current_status' => 'aktif',
        ]);

        $this->actingAs($this->sarpras())
            ->post(route('sarpras.inventory.updateLocation', $item), [
                'to_location_id' => $to->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame($to->id, $item->fresh()->location_id);

        $this->assertDatabaseHas('location_histories', [
            'item_id' => $item->id,
            'asset_unit_id' => null,
            'from_location_id' => $from->id,
            'to_location_id' => $to->id,
        ]);
    }

    public function test_inventory_show_displays_unit_column_in_location_history(): void
    {
        $item = $this->itemWithUnits(1, ['code' => 'BRG-ROU', 'name' => 'Router Lab']);
        $unit = $item->assetUnits()->firstOrFail();
        $target = Location::factory()->create(['name' => 'Ruang Serbaguna']);

        LocationHistory::create([
            'item_id' => $item->id,
            'asset_unit_id' => $unit->id,
            'from_location_id' => $unit->location_id,
            'to_location_id' => $target->id,
            'user_id' => $this->sarpras()->id,
            'moved_at' => now(),
        ]);

        $this->actingAs($this->sarpras())
            ->get(route('sarpras.inventory.show', $item))
            ->assertOk()
            ->assertSee('BRG-ROU-U01');
    }
}
