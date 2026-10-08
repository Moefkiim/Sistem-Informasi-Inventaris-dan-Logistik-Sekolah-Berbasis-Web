<?php

namespace Database\Factories;

use App\Models\AssetUnit;
use App\Models\Item;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AssetUnitFactory extends Factory
{
    protected $model = AssetUnit::class;

    public function definition(): array
    {
        return [
            'item_id'               => Item::factory()->individual(),
            'unit_inventory_number' => strtoupper('UNIT-' . Str::random(6)),
            'serial_number'         => null,
            'current_condition'     => 'baik',
            'current_status'        => 'aktif',
            'location_id'           => null,
            'is_legacy_migrated'    => false,
            'notes'                 => null,
        ];
    }

    /** Unit dalam kondisi rusak ringan. */
    public function rusak(): static
    {
        return $this->state(fn (array $attrs) => ['current_condition' => 'rusak_ringan']);
    }

    /** Unit dalam status tidak aktif. */
    public function tidakAktif(): static
    {
        return $this->state(fn (array $attrs) => ['current_status' => 'tidak_aktif']);
    }

    /** Unit dalam status dalam perbaikan. */
    public function dalamPerbaikan(): static
    {
        return $this->state(fn (array $attrs) => ['current_status' => 'dalam_perbaikan']);
    }

    /** Unit dalam status disposed/dihapuskan. */
    public function disposed(): static
    {
        return $this->state(fn (array $attrs) => ['current_status' => 'disposed']);
    }
}
