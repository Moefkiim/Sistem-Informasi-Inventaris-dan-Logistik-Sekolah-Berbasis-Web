<?php

namespace Tests\Feature;

use App\Models\AssetUnit;
use App\Models\ConditionHistory;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetUnitConditionTest extends TestCase
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

        for ($i = 1; $i <= $unitCount; $i++) {
            AssetUnit::create([
                'item_id' => $item->id,
                'unit_inventory_number' => $code.'-U'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'current_condition' => 'baik',
                'current_status' => 'aktif',
            ]);
        }

        return $item;
    }

    public function test_unit_condition_can_be_updated_with_history(): void
    {
        $item = $this->itemWithUnits(2, ['code' => 'BRG-LAP', 'name' => 'Laptop Ganggang']);
        $units = $item->assetUnits()->orderBy('id')->get();
        $units[1]->update(['current_condition' => 'rusak_ringan']);

        $this->actingAs($this->sarpras())
            ->post(route('sarpras.inventory.updateCondition', $item), [
                'asset_unit_id' => $units[0]->id,
                'to_condition' => 'rusak_ringan',
                'notes' => 'Keyboard macet',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('rusak_ringan', $units[0]->fresh()->current_condition);

        $this->assertDatabaseHas('condition_histories', [
            'item_id' => $item->id,
            'asset_unit_id' => $units[0]->id,
            'from_condition' => 'baik',
            'to_condition' => 'rusak_ringan',
        ]);

        // Kondisi agregat item = kondisi terburuk antar unit.
        $this->assertSame('rusak_ringan', $item->fresh()->current_condition);
    }

    public function test_other_unit_condition_is_not_affected(): void
    {
        $item = $this->itemWithUnits(2, ['code' => 'BRG-KAM']);
        $units = $item->assetUnits()->orderBy('id')->get();

        $this->actingAs($this->sarpras())
            ->post(route('sarpras.inventory.updateCondition', $item), [
                'asset_unit_id' => $units[0]->id,
                'to_condition' => 'rusak_berat',
            ])
            ->assertRedirect();

        $this->assertSame('rusak_berat', $units[0]->fresh()->current_condition);
        $this->assertSame('baik', $units[1]->fresh()->current_condition);
        $this->assertSame('rusak_berat', $item->fresh()->current_condition);
    }

    public function test_unit_selection_required_when_item_has_units(): void
    {
        $item = $this->itemWithUnits(1, ['code' => 'BRG-PRC']);

        $this->actingAs($this->sarpras())
            ->post(route('sarpras.inventory.updateCondition', $item), [
                'to_condition' => 'rusak_ringan',
            ])
            ->assertSessionHasErrors('asset_unit_id');

        $this->assertSame(0, ConditionHistory::count());
    }

    public function test_unit_selection_must_belong_to_item(): void
    {
        $item = $this->itemWithUnits(1, ['code' => 'BRG-LPT']);
        $otherItem = $this->itemWithUnits(1, ['code' => 'BRG-LTN']);

        $this->actingAs($this->sarpras())
            ->post(route('sarpras.inventory.updateCondition', $item), [
                'asset_unit_id' => $otherItem->assetUnits()->firstOrFail()->id,
                'to_condition' => 'rusak_ringan',
            ])
            ->assertSessionHasErrors('asset_unit_id');

        $this->assertSame(0, ConditionHistory::count());
    }

    public function test_same_condition_is_rejected(): void
    {
        $item = $this->itemWithUnits(1, ['code' => 'BRG-PRJ']);
        $unit = $item->assetUnits()->firstOrFail();

        $this->actingAs($this->sarpras())
            ->post(route('sarpras.inventory.updateCondition', $item), [
                'asset_unit_id' => $unit->id,
                'to_condition' => 'baik',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('msg');

        $this->assertSame(0, ConditionHistory::count());
    }

    public function test_legacy_item_without_units_keeps_item_level_update(): void
    {
        $item = Item::factory()->create([
            'item_type' => 'individual',
            'current_condition' => 'baik',
            'current_status' => 'aktif',
        ]);

        $this->actingAs($this->sarpras())
            ->post(route('sarpras.inventory.updateCondition', $item), [
                'to_condition' => 'rusak_berat',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('rusak_berat', $item->fresh()->current_condition);

        $this->assertDatabaseHas('condition_histories', [
            'item_id' => $item->id,
            'asset_unit_id' => null,
            'to_condition' => 'rusak_berat',
        ]);
    }

    public function test_inventory_show_displays_units_and_condition_history_units(): void
    {
        $item = $this->itemWithUnits(2, ['code' => 'BRG-ROU', 'name' => 'Router Lab']);
        $units = $item->assetUnits()->orderBy('id')->get();

        ConditionHistory::create([
            'item_id' => $item->id,
            'asset_unit_id' => $units[0]->id,
            'from_condition' => 'baik',
            'to_condition' => 'rusak_ringan',
            'user_id' => $this->sarpras()->id,
            'recorded_at' => now(),
        ]);

        $this->actingAs($this->sarpras())
            ->get(route('sarpras.inventory.show', $item))
            ->assertOk()
            ->assertSee('Daftar Unit Aset')
            ->assertSee('BRG-ROU-U01')
            ->assertSee('BRG-ROU-U02')
            ->assertSee('Unit');
    }
}
