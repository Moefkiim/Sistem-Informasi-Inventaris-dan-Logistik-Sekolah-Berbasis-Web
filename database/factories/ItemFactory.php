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
            'code' => $code,
            'name' => $this->faker->words(2, true),
            'category' => 'Alat',
            'unit' => 'Unit',
            'stock' => 10,
            'source' => 'pembelian',
            'department' => 'Umum',
            'location_id' => null,
            'current_condition' => 'baik',
            'description' => null,
        ];
    }
}
