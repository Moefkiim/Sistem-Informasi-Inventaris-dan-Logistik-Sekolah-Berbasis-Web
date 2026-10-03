<?php

namespace Database\Factories;

use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class LocationFactory extends Factory
{
    protected $model = Location::class;

    public function definition(): array
    {
        $code = strtoupper('LOC-' . Str::random(4));
        return [
            'code' => $code,
            'name' => $this->faker->word(),
            'building' => $this->faker->word(),
            'department' => null,
            'description' => null,
        ];
    }
}
