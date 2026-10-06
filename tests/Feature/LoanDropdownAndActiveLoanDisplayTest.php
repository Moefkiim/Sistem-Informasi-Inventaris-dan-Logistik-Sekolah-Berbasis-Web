<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanDropdownAndActiveLoanDisplayTest extends TestCase
{
    use RefreshDatabase;

    private function sarpras(): User
    {
        return User::factory()->sarpras()->create();
    }

    public function test_borrowed_individual_item_is_hidden_from_create_dropdown(): void
    {
        $item = Item::factory()->create([
            'item_type' => 'individual',
            'stock' => 1,
            'name' => 'Proyektor Terpinjam',
            'current_status' => 'aktif',
        ]);

        Loan::factory()->onLoan()->create([
            'item_id' => $item->id,
            'quantity' => 1,
            'borrower_name' => 'Guru Rudi',
        ]);

        $this->actingAs($this->sarpras())
            ->get(route('sarpras.loans.create'))
            ->assertOk()
            ->assertDontSee('Proyektor Terpinjam');
    }

    public function test_zero_stock_consumable_is_hidden_from_create_dropdown(): void
    {
        Item::factory()->create([
            'item_type' => 'consumable',
            'stock' => 0,
            'name' => 'Kertas Habis',
            'current_status' => 'aktif',
        ]);

        $this->actingAs($this->sarpras())
            ->get(route('sarpras.loans.create'))
            ->assertOk()
            ->assertDontSee('Kertas Habis');
    }

    public function test_available_items_still_appear_in_create_dropdown(): void
    {
        $freeIndividual = Item::factory()->create([
            'item_type' => 'individual',
            'stock' => 1,
            'name' => 'Laptop Tersedia',
            'current_status' => 'aktif',
        ]);

        $stockedConsumable = Item::factory()->create([
            'item_type' => 'consumable',
            'stock' => 5,
            'name' => 'Tinta Penuh',
            'current_status' => 'aktif',
        ]);

        $this->actingAs($this->sarpras())
            ->get(route('sarpras.loans.create'))
            ->assertOk()
            ->assertSee('Laptop Tersedia')
            ->assertSee('Tinta Penuh');

        $this->assertTrue($freeIndividual->exists && $stockedConsumable->exists);
    }

    public function test_sarpras_item_detail_shows_active_loans(): void
    {
        $sarpras = $this->sarpras();
        $item = Item::factory()->create([
            'item_type' => 'individual',
            'stock' => 1,
            'name' => 'Ukuran Proyektor',
            'current_status' => 'aktif',
        ]);

        $loan = Loan::factory()->onLoan()->create([
            'item_id' => $item->id,
            'quantity' => 1,
            'borrower_name' => 'Guru Rudi',
            'loan_number' => 'LN-20261005-0899',
        ]);

        $this->actingAs($sarpras)
            ->get(route('sarpras.inventory.show', $item))
            ->assertOk()
            ->assertSee('Peminjaman Aktif')
            ->assertSee('Guru Rudi')
            ->assertSee('LN-20261005-0899')
            ->assertSee('Dipinjam');
    }

    public function test_kajur_item_detail_shows_active_loans_within_department(): void
    {
        $kajur = User::factory()->kajur()->create(['department' => 'RPL']);

        $item = Item::factory()->create([
            'item_type' => 'individual',
            'stock' => 1,
            'name' => 'Ukuran Router',
            'department' => 'RPL',
            'current_status' => 'aktif',
        ]);

        $loan = Loan::factory()->onLoan()->create([
            'item_id' => $item->id,
            'quantity' => 1,
            'borrower_name' => 'Guru Agus',
            'loan_number' => 'LN-20261005-0456',
        ]);

        $this->actingAs($kajur)
            ->get(route('kajur.inventory.show', $item))
            ->assertOk()
            ->assertSee('Peminjaman Aktif')
            ->assertSee('Guru Agus')
            ->assertSee('LN-20261005-0456');
    }

    public function test_item_without_active_loans_does_not_show_loan_block(): void
    {
        $item = Item::factory()->create([
            'item_type' => 'individual',
            'stock' => 1,
            'name' => 'Barang Idle',
            'current_status' => 'aktif',
        ]);

        $this->actingAs($this->sarpras())
            ->get(route('sarpras.inventory.show', $item))
            ->assertOk()
            ->assertDontSee('Peminjaman Aktif');
    }
}
