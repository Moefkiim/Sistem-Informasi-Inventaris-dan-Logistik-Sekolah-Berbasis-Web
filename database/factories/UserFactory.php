<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => 'kajur',
            'department' => 'RPL',
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function kajur(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'kajur',
            'department' => 'Rekayasa Perangkat Lunak',
        ]);
    }

    public function sarpras(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'sarpras',
            'department' => null,
        ]);
    }

    public function kepalaSekolah(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'kepala_sekolah',
            'department' => null,
        ]);
    }
}
