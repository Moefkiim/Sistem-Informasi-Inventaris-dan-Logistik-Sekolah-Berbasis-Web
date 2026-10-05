<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\Loan;
use Illuminate\Database\Eloquent\Factories\Factory;

class LoanFactory extends Factory
{
    protected $model = Loan::class;

    public function definition(): array
    {
        $loanDate = now()->subDays(fake()->numberBetween(0, 5));

        return [
            'loan_number'         => 'LN-' . $loanDate->format('Ymd') . '-' . fake()->unique()->numerify('####'),
            'item_id'             => Item::factory(),
            'quantity'            => 1,
            'borrower_name'       => fake()->name(),
            'borrower_department' => fake()->randomElement(['RPL', 'TKJ', 'TBS', 'Umum']),
            'loan_date'           => $loanDate->toDateString(),
            'due_date'            => $loanDate->copy()->addDays(7)->toDateString(),
            'return_date'         => null,
            'purpose'             => fake()->sentence(),
            'status'              => Loan::STATUS_ACTIVE[0],
            'condition_on_loan'   => 'baik',
            'condition_on_return' => null,
            'notes'               => null,
        ];
    }

    public function onLoan(): static
    {
        return $this->state(fn () => [
            'status'      => 'dipinjam',
            'approved_at' => now(),
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn () => [
            'status'   => 'terlambat',
            'loan_date' => now()->subDays(20)->toDateString(),
            'due_date'  => now()->subDays(5)->toDateString(),
        ]);
    }

    public function returned(string $condition = 'baik'): static
    {
        return $this->state(fn () => [
            'status'              => 'dikembalikan',
            'condition_on_return' => $condition,
            'return_date'         => now()->toDateString(),
            'returned_at'         => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => 'ditolak',
            'notes'  => 'Barang sedang digunakan untuk kegiatan lain.',
        ]);
    }
}
