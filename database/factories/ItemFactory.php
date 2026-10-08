<?php

namespace Database\Factories;

use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ItemFactory extends Factory
{
    protected $model = Item::class;

    public function definition(): array
    {
        $code = strtoupper('BRG-' . Str::random(6));
        return [
            'code'             => $code,
            'name'             => $this->faker->words(2, true),
            'category'         => 'Alat',
            'unit'             => 'Unit',
            'stock'            => 10,
            'source'           => 'pembelian',
            'department'       => 'Umum',
            'location_id'      => null,
            'current_condition' => 'baik',
            'description'      => null,
        ];
    }

    /** Barang individual (tracked per unit fisik). */
    public function individual(): static
    {
        return $this->state(fn (array $attrs) => ['item_type' => 'individual']);
    }

    /** Barang habis pakai/consumable. */
    public function consumable(): static
    {
        return $this->state(fn (array $attrs) => ['item_type' => 'consumable']);
    }
}
