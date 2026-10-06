<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\ConditionHistory;
use App\Models\Item;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function sarpras(): User
    {
        return User::factory()->sarpras()->create();
    }

    private function individualItem(array $attributes = []): Item
    {
        return Item::factory()->create(array_merge([
            'item_type' => 'individual',
            'stock' => 1,
            'current_status' => 'aktif',
        ], $attributes));
    }

    private function consumableItem(array $attributes = []): Item
    {
        return Item::factory()->create(array_merge([
            'item_type' => 'consumable',
            'stock' => 10,
            'current_status' => 'aktif',
        ], $attributes));
    }

    // ===== Halaman modul =====

    public function test_sarpras_can_open_loans_index(): void
    {
        Loan::factory()->count(3)->create();

        $this->actingAs($this->sarpras())
            ->get(route('sarpras.loans.index'))
            ->assertOk()
            ->assertSee('Daftar Peminjaman')
            ->assertSee('LN-');
    }

    public function test_sarpras_can_open_create_page(): void
    {
        $item = $this->individualItem(['name' => 'Laptop Lenovo ThinkPad']);

        $this->actingAs($this->sarpras())
            ->get(route('sarpras.loans.create'))
            ->assertOk()
            ->assertSee('Catat Peminjaman Barang')
            ->assertSee('Laptop Lenovo ThinkPad');
    }

    public function test_sarpras_can_open_loan_detail_page(): void
    {
        $loan = Loan::factory()->create(['borrower_name' => 'Budi Santoso']);

        $this->actingAs($this->sarpras())
            ->get(route('sarpras.loans.show', $loan))
            ->assertOk()
            ->assertSee($loan->loan_number)
            ->assertSee('Budi Santoso')
            ->assertSee('Riwayat Proses');
    }

    public function test_non_sarpras_roles_are_denied_loans_pages(): void
    {
        $loan = Loan::factory()->create();

        $this->actingAs(User::factory()->kajur()->create())
            ->get(route('sarpras.loans.index'))->assertForbidden();

        $this->actingAs(User::factory()->kepalaSekolah()->create())
            ->get(route('sarpras.loans.index'))->assertForbidden();

        $this->actingAs(User::factory()->kajur()->create())
            ->get(route('sarpras.loans.show', $loan))->assertForbidden();
    }

    public function test_loans_menu_is_visible_for_sarpras_only(): void
    {
        $this->actingAs($this->sarpras())
            ->get(route('sarpras.loans.index'))
            ->assertOk()
            ->assertSee('Peminjaman Barang')
            ->assertSee(route('sarpras.loans.index'), false);

        $this->actingAs(User::factory()->kajur()->create())
            ->get(route('kajur.inventory.index'))
            ->assertOk()
            ->assertDontSee('Peminjaman Barang');
    }

    // ===== Pembuatan peminjaman =====

    public function test_sarpras_can_create_loan_and_audit_log_is_recorded(): void
    {
        $item = $this->consumableItem(['stock' => 5]);

        $this->actingAs($this->sarpras())
            ->post(route('sarpras.loans.store'), [
                'borrower_name' => 'Siti Aminah',
                'borrower_department' => 'TKJ',
                'item_id' => $item->id,
                'quantity' => 2,
                'loan_date' => now()->toDateString(),
                'due_date' => now()->addDays(5)->toDateString(),
                'purpose' => 'Praktikum jaringan',
            ])
            ->assertRedirect(route('sarpras.loans.index'));

        $loan = Loan::firstOrFail();

        $this->assertSame('menunggu', $loan->status);
        $this->assertSame('Siti Aminah', $loan->borrower_name);
        $this->assertSame(2, $loan->quantity);
        $this->assertSame('baik', $loan->condition_on_loan);

        // Stok consumable baru berkurang setelah disetujui, bukan saat pembuatan
        $this->assertSame(5, $item->fresh()->stock);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'loan_created',
            'auditable_type' => Loan::class,
            'auditable_id' => $loan->id,
            'user_role' => 'sarpras',
        ]);
    }

    public function test_loan_quantity_cannot_exceed_stock(): void
    {
        $item = $this->consumableItem(['stock' => 3]);

        $this->actingAs($this->sarpras())
            ->post(route('sarpras.loans.store'), [
                'borrower_name' => 'Andi',
                'item_id' => $item->id,
                'quantity' => 99,
                'loan_date' => now()->toDateString(),
                'due_date' => now()->addDay()->toDateString(),
                'purpose' => 'Uji coba',
            ])
            ->assertSessionHasErrors('quantity');

        $this->assertSame(0, Loan::count());
    }

    public function test_loan_requires_due_date_not_before_loan_date(): void
    {
        $item = $this->consumableItem();

        $this->actingAs($this->sarpras())
            ->post(route('sarpras.loans.store'), [
                'borrower_name' => 'Andi',
                'item_id' => $item->id,
                'quantity' => 1,
                'loan_date' => now()->toDateString(),
                'due_date' => now()->subDays(3)->toDateString(),
                'purpose' => 'Uji coba',
            ])
            ->assertSessionHasErrors('due_date');

        $this->assertSame(0, Loan::count());
    }

    // ===== Persetujuan &_asset =====

    public function test_approving_individual_loan_marks_item_as_borrowed(): void
    {
        $loan = Loan::factory()->create(['item_id' => $this->individualItem()->id]);

        $this->actingAs($this->sarpras())
            ->post(route('sarpras.loans.approve', $loan))
            ->assertRedirect();

        $this->assertSame('dipinjam', $loan->fresh()->status);
        $this->assertSame('dipinjam', $loan->item->fresh()->current_status);
        $this->assertNotNull($loan->fresh()->approved_at);

        $this->assertDatabaseHas('activity_logs', ['action' => 'loan_approved']);
    }

    public function test_approving_consumable_loan_decrements_stock_and_never_goes_negative(): void
    {
        $item = $this->consumableItem(['stock' => 4]);
        $loan = Loan::factory()->create(['item_id' => $item->id, 'quantity' => 4]);

        $this->actingAs($this->sarpras())
            ->post(route('sarpras.loans.approve', $loan))
            ->assertRedirect();

        $this->assertSame(0, $item->fresh()->stock);
        $this->assertGreaterThanOrEqual(0, $item->fresh()->stock);
    }

    public function test_asset_already_on_loan_cannot_be_loaned_again(): void
    {
        $item = $this->individualItem();
        Loan::factory()->create(['item_id' => $item->id, 'status' => 'dipinjam', 'quantity' => 1]);

        $this->actingAs($this->sarpras())
            ->post(route('sarpras.loans.store'), [
                'borrower_name' => 'Rina',
                'item_id' => $item->id,
                'quantity' => 1,
                'loan_date' => now()->toDateString(),
                'due_date' => now()->addDay()->toDateString(),
                'purpose' => 'Peminjaman ganda',
            ])
            ->assertSessionHasErrors('item_id');

        $this->assertSame(1, Loan::count());
    }

    public function test_returned_loan_cannot_be_approved_again(): void
    {
        $loan = Loan::factory()->returned()->create();

        $this->actingAs($this->sarpras())
            ->post(route('sarpras.loans.approve', $loan));

        $this->assertSame('dikembalikan', $loan->fresh()->status);
    }

    // ===== Pengembalian =====

    public function test_returning_individual_loan_restores_asset_status(): void
    {
        $item = $this->individualItem(['current_status' => 'dipinjam']);
        $loan = Loan::factory()->onLoan()->create([
            'item_id' => $item->id,
            'condition_on_loan' => 'baik',
        ]);

        $this->actingAs($this->sarpras())
            ->post(route('sarpras.loans.return', $loan), [
                'condition_on_return' => 'baik',
                'notes' => 'Lengkap',
            ])
            ->assertRedirect();

        $loan->refresh();

        $this->assertSame('dikembalikan', $loan->status);
        $this->assertSame(now()->toDateString(), $loan->return_date->toDateString());
        $this->assertSame('aktif', $item->fresh()->current_status);
        $this->assertNotNull($loan->returned_at);
        $this->assertSame(0, ConditionHistory::count());

        $this->assertDatabaseHas('activity_logs', ['action' => 'loan_returned']);
    }

    public function test_returning_with_changed_condition_records_condition_history(): void
    {
        $item = $this->individualItem(['current_status' => 'dipinjam', 'current_condition' => 'baik']);
        $loan = Loan::factory()->onLoan()->create([
            'item_id' => $item->id,
            'condition_on_loan' => 'baik',
        ]);

        $this->actingAs($this->sarpras())
            ->post(route('sarpras.loans.return', $loan), [
                'condition_on_return' => 'rusak_ringan',
                'notes' => 'Keyboard ada yang rusak',
            ])
            ->assertRedirect();

        $this->assertSame('rusak_ringan', $item->fresh()->current_condition);

        $this->assertDatabaseHas('condition_histories', [
            'item_id' => $item->id,
            'from_condition' => 'baik',
            'to_condition' => 'rusak_ringan',
        ]);
    }

    public function test_returning_consumable_loan_restores_stock(): void
    {
        $item = $this->consumableItem(['stock' => 2]);
        $loan = Loan::factory()->onLoan()->create(['item_id' => $item->id, 'quantity' => 3]);

        $this->actingAs($this->sarpras())
            ->post(route('sarpras.loans.return', $loan), [
                'condition_on_return' => 'baik',
            ])
            ->assertRedirect();

        $this->assertSame(5, $item->fresh()->stock);
    }

    public function test_return_requires_valid_condition(): void
    {
        $loan = Loan::factory()->onLoan()->create();

        $this->actingAs($this->sarpras())
            ->post(route('sarpras.loans.return', $loan), [
                'condition_on_return' => 'hilang',
            ])
            ->assertSessionHasErrors('condition_on_return');

        $this->assertSame('dipinjam', $loan->fresh()->status);
    }

    public function test_pending_loan_cannot_be_returned(): void
    {
        $loan = Loan::factory()->create(['status' => 'menunggu']);

        $this->actingAs($this->sarpras())
            ->post(route('sarpras.loans.return', $loan), [
                'condition_on_return' => 'baik',
            ]);

        $this->assertSame('menunggu', $loan->fresh()->status);
    }

    // ===== Penolakan =====

    public function test_sarpras_can_reject_pending_loan_with_reason(): void
    {
        $loan = Loan::factory()->create(['status' => 'menunggu']);

        $this->actingAs($this->sarpras())
            ->post(route('sarpras.loans.reject', $loan), [
                'notes' => 'Barang sedang digunakan untuk kegiatan kelas.',
            ])
            ->assertRedirect();

        $loan->refresh();

        $this->assertSame('ditolak', $loan->status);
        $this->assertNotNull($loan->approved_at);

        $this->assertDatabaseHas('activity_logs', ['action' => 'loan_rejected']);
    }

    public function test_reject_requires_reason(): void
    {
        $loan = Loan::factory()->create(['status' => 'menunggu']);

        $this->actingAs($this->sarpras())
            ->post(route('sarpras.loans.reject', $loan), [])
            ->assertSessionHasErrors('notes');

        $this->assertSame('menunggu', $loan->fresh()->status);
    }

    public function test_borrowed_loan_cannot_be_rejected(): void
    {
        $loan = Loan::factory()->onLoan()->create();

        $this->actingAs($this->sarpras())
            ->post(route('sarpras.loans.reject', $loan), ['notes' => 'Alasan']);

        $this->assertSame('dipinjam', $loan->fresh()->status);
    }

    // ===== Filter & utilitas =====

    public function test_index_can_filter_by_status_and_search(): void
    {
        $item = $this->consumableItem(['name' => 'Kertas A4', 'code' => 'BRG-KERTAS']);
        Loan::factory()->create(['item_id' => $item->id, 'borrower_name' => 'Pencari Satu', 'status' => 'menunggu']);
        Loan::factory()->returned()->create(['borrower_name' => 'Pencari Dua']);

        $this->actingAs($this->sarpras())
            ->get(route('sarpras.loans.index', ['status' => 'menunggu']))
            ->assertOk()
            ->assertSee('Pencari Satu')
            ->assertDontSee('Pencari Dua');

        $this->actingAs($this->sarpras())
            ->get(route('sarpras.loans.index', ['search' => 'Kertas']))
            ->assertOk()
            ->assertSee('BRG-KERTAS');
    }

    public function test_overdue_loan_is_automatically_marked_late(): void
    {
        $loan = Loan::factory()->create([
            'status' => 'dipinjam',
            'due_date' => now()->subDays(3)->toDateString(),
        ]);

        $this->actingAs($this->sarpras())
            ->get(route('sarpras.loans.index'))
            ->assertOk();

        $this->assertSame('terlambat', $loan->fresh()->status);
        $this->assertSame(3, $loan->fresh()->overdueDays());
    }

    public function test_activity_log_records_actor_and_payload(): void
    {
        $this->sarpras();
        $loan = Loan::factory()->create();

        $this->actingAs(User::factory()->sarpras()->create())
            ->post(route('sarpras.loans.approve', $loan));

        $log = ActivityLog::where('action', 'loan_approved')->firstOrFail();

        $this->assertSame('sarpras', $log->user_role);
        $this->assertSame(Loan::class, $log->auditable_type);
        $this->assertSame($loan->id, $log->auditable_id);
        $this->assertSame($loan->loan_number, $log->auditable_label);
        $this->assertNotNull($log->logged_at);
    }

    public function test_sarpras_can_open_activity_logs_page(): void
    {
        ActivityLog::create([
            'action' => 'loan_created',
            'description' => 'Peminjaman baru dicatat',
            'user_name' => 'Budi',
            'user_role' => 'sarpras',
            'logged_at' => now(),
        ]);

        $this->actingAs($this->sarpras())
            ->get(route('sarpras.activity_logs.index'))
            ->assertOk()
            ->assertSee('Riwayat Aktivitas')
            ->assertSee('Peminjaman baru dicatat');
    }

    public function test_activity_logs_page_can_filter_by_action(): void
    {
        ActivityLog::create([
            'action' => 'loan_created',
            'description' => 'Peminjaman baru dicatat',
            'user_name' => 'Budi',
            'user_role' => 'sarpras',
            'logged_at' => now(),
        ]);
        ActivityLog::create([
            'action' => 'item_created',
            'description' => 'Barang didaftarkan',
            'user_name' => 'Budi',
            'user_role' => 'sarpras',
            'logged_at' => now(),
        ]);

        $this->actingAs($this->sarpras())
            ->get(route('sarpras.activity_logs.index', ['action' => 'item_created']))
            ->assertOk()
            ->assertSee('Barang didaftarkan')
            ->assertDontSee('Peminjaman baru dicatat');
    }

    public function test_activity_logs_page_is_restricted_to_sarpras(): void
    {
        $kajur = User::factory()->kajur()->create(['department' => 'TJKR']);

        $this->actingAs($kajur)
            ->get(route('sarpras.activity_logs.index'))
            ->assertForbidden();
    }
}
