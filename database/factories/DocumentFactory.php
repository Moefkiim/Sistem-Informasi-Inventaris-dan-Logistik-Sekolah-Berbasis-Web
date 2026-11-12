<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(3),
            'file_path' => 'documents/'.$this->faker->uuid().'.pdf',
            'file_name' => $this->faker->word().'.pdf',
            'file_type' => 'pdf',
            'file_size' => $this->faker->numberBetween(1024, 5242880),
            'category' => $this->faker->randomElement(['Nota', 'BAST', 'Surat Bantuan', 'Foto', 'Umum']),
            'department' => null,
            'user_id' => User::factory(),
        ];
    }
}
