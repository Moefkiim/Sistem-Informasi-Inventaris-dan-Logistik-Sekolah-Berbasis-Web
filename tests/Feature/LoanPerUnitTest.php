<?php

namespace Tests\Feature;

use App\Models\AssetUnit;
use App\Models\Item;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanPerUnitTest extends TestCase
{
    use RefreshDatabase;

    private function sarpras(): User
    {
        return User::factory()->sarpras()->create();
    }

    private function individualItemWithUnits(int $unitCount, array $attributes = []): Item
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

    public function test_create_page_lists_available_units_for_individual_item(): void
    {
        $item = $this->individualItemWithUnits(2, ['code' => 'BRG-ROU', 'name' => 'Router X']);

        $this->actingAs($this->sarpras())
            ->get(route('sarpras.loans.create'))
            ->assertOk()
            ->assertSee('BRG-ROU-U01')
            ->assertSee('BRG-ROU-U02');
    }

    public function test_sarpras_can_create_loan_for_specific_unit(): void
    {
        $item = $this->individualItemWithUnits(2, ['code' => 'BRG-LPT', 'name' => 'Laptop Test']);
        $secondUnit = $item->assetUnits()->orderBy('id')->get()[1];

        $this->actingAs($this->sarpras())
            ->post(route('sarpras.loans.store'), [
                'borrower_name' => 'Budi',
                'item_id' => $item->id,
                'asset_unit_id' => $secondUnit->id,
                'quantity' => 1,
                'loan_date' => now()->toDateString(),
                'due_date' => now()->addDay()->toDateString(),
                'purpose' => 'Praktikum jaringan',
            ])
            ->assertRedirect(route('sarpras.loans.index'));

        $loan = Loan::firstOrFail();

        $this->assertSame($secondUnit->id, $loan->asset_unit_id);
        $this->assertSame(1, $loan->quantity);
        $this->assertSame('menunggu', $loan->status);
    }

    public function test_unit_already_on_loan_cannot_be_selected(): void
    {
        $item = $this->individualItemWithUnits(1, ['code' => 'BRG-PRJ']);
        $unit = $item->assetUnits()->firstOrFail();

        Loan::factory()->create([
            'item_id' => $item->id,
            'asset_unit_id' => $unit->id,
            'status' => 'dipinjam',
            'quantity' => 1,
        ]);

        $this->actingAs($this->sarpras())
            ->post(route('sarpras.loans.store'), [
                'borrower_name' => 'Rina',
                'item_id' => $item->id,
                'asset_unit_id' => $unit->id,
                'quantity' => 1,
                'loan_date' => now()->toDateString(),
                'due_date' => now()->addDay()->toDateString(),
                'purpose' => 'Peminjaman ganda',
            ])
            ->assertSessionHasErrors('asset_unit_id');

        $this->assertSame(1, Loan::count());
    }

    public function test_unit_is_required_when_item_is_unit_based(): void
    {
        $item = $this->individualItemWithUnits(1, ['code' => 'BRG-KAM']);

        $this->actingAs($this->sarpras())
            ->post(route('sarpras.loans.store'), [
                'borrower_name' => 'Andi',
                'item_id' => $item->id,
                'quantity' => 1,
                'loan_date' => now()->toDateString(),
                'due_date' => now()->addDay()->toDateString(),
                'purpose' => 'Uji coba',
            ])
            ->assertSessionHasErrors('asset_unit_id');

        $this->assertSame(0, Loan::count());
    }

    public function test_approving_loan_marks_the_selected_unit_as_borrowed(): void
    {
        $item = $this->individualItemWithUnits(1, ['code' => 'BRG-LAP', 'name' => 'Laptop Unit']);
        $unit = $item->assetUnits()->firstOrFail();

        $loan = Loan::factory()->create([
            'item_id' => $item->id,
            'asset_unit_id' => $unit->id,
            'status' => 'menunggu',
        ]);

        $this->actingAs($this->sarpras())
            ->post(route('sarpras.loans.approve', $loan))
            ->assertRedirect();

        $this->assertSame('dipinjam', $loan->fresh()->status);
        $this->assertSame('dipinjam', $unit->fresh()->current_status);
        $this->assertSame('dipinjam', $item->fresh()->current_status);
    }

    public function test_other_unit_of_same_item_remains_available(): void
    {
        $item = $this->individualItemWithUnits(2, ['code' => 'BRG-LAP', 'name' => 'Laptop Double']);
        $units = $item->assetUnits()->orderBy('id')->get();

        Loan::factory()->create([
            'item_id' => $item->id,
            'asset_unit_id' => $units[0]->id,
            'status' => 'dipinjam',
            'quantity' => 1,
        ]);

        $this->actingAs($this->sarpras())
            ->post(route('sarpras.loans.store'), [
                'borrower_name' => 'Susi',
                'item_id' => $item->id,
                'asset_unit_id' => $units[1]->id,
                'quantity' => 1,
                'loan_date' => now()->toDateString(),
                'due_date' => now()->addDay()->toDateString(),
                'purpose' => 'Kegiatan lomba',
            ])
            ->assertRedirect(route('sarpras.loans.index'));
    }

    public function test_returning_loan_restores_unit_and_records_unit_condition_change(): void
    {
        $item = $this->individualItemWithUnits(1, ['code' => 'BRG-PRC', 'current_status' => 'dipinjam']);
        $unit = $item->assetUnits()->firstOrFail();
        $unit->update(['current_status' => 'dipinjam']);

        $loan = Loan::factory()->onLoan()->create([
            'item_id' => $item->id,
            'asset_unit_id' => $unit->id,
            'condition_on_loan' => 'baik',
        ]);

        $this->actingAs($this->sarpras())
            ->post(route('sarpras.loans.return', $loan), [
                'condition_on_return' => 'rusak_ringan',
                'notes' => 'Layar retak',
            ])
            ->assertRedirect();

        $this->assertSame('aktif', $unit->fresh()->current_status);
        $this->assertSame('rusak_ringan', $unit->fresh()->current_condition);
        $this->assertSame('rusak_ringan', $item->fresh()->current_condition);

        $this->assertDatabaseHas('condition_histories', [
            'item_id' => $item->id,
            'asset_unit_id' => $unit->id,
            'from_condition' => 'baik',
            'to_condition' => 'rusak_ringan',
        ]);
    }

    public function test_loan_show_page_displays_selected_unit(): void
    {
        $item = $this->individualItemWithUnits(1, ['code' => 'BRG-LPT', 'name' => 'Laptop Dipinjam']);
        $unit = $item->assetUnits()->firstOrFail();

        $loan = Loan::factory()->create([
            'item_id' => $item->id,
            'asset_unit_id' => $unit->id,
            'borrower_name' => 'Pak Guru',
        ]);

        $this->actingAs($this->sarpras())
            ->get(route('sarpras.loans.show', $loan))
            ->assertOk()
            ->assertSee($unit->unit_inventory_number);
    }
}
