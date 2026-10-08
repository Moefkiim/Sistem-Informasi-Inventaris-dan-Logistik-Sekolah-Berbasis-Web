<?php

namespace Tests\Feature;

use App\Models\AssetUnit;
use App\Models\Item;
use App\Models\Loan;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Stage 2A: AssetUnit availability guard tests.
 *
 * Verifikasi bahwa:
 * 1. Unit dengan status non-loanable (dalam_perbaikan, tidak_aktif, disposed)
 *    tidak muncul di dropdown peminjaman.
 * 2. Unit dengan status non-loanable ditolak di store() walaupun dikirim langsung.
 * 3. Unit aktif yang sudah dipinjam juga diblokir.
 * 4. Helper isAvailableForLoan() benar untuk semua kombinasi status/loan.
 */
class AssetUnitAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private User $sarpras;

    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sarpras = User::factory()->create(['role' => 'sarpras']);
        $this->item = Item::factory()->individual()->create(['stock' => 3]);
    }

    // =========================================================================
    // 1. Helper isAvailableForLoan()
    // =========================================================================

    public function test_unit_aktif_tanpa_loan_tersedia_untuk_pinjam(): void
    {
        $unit = AssetUnit::factory()->for($this->item)->create(['current_status' => 'aktif']);

        $this->assertTrue($unit->isAvailableForLoan());
    }

    public function test_unit_aktif_dengan_loan_dipinjam_tidak_tersedia(): void
    {
        $unit = AssetUnit::factory()->for($this->item)->create(['current_status' => 'dipinjam']);
        Loan::factory()->create([
            'item_id'      => $this->item->id,
            'asset_unit_id'=> $unit->id,
            'status'       => 'dipinjam',
        ]);

        $this->assertFalse($unit->isAvailableForLoan());
    }

    public function test_unit_dalam_perbaikan_tidak_tersedia(): void
    {
        $unit = AssetUnit::factory()->for($this->item)->create(['current_status' => 'dalam_perbaikan']);

        $this->assertFalse($unit->isAvailableForLoan());
    }

    public function test_unit_tidak_aktif_tidak_tersedia(): void
    {
        $unit = AssetUnit::factory()->for($this->item)->create(['current_status' => 'tidak_aktif']);

        $this->assertFalse($unit->isAvailableForLoan());
    }

    public function test_unit_disposed_tidak_tersedia(): void
    {
        $unit = AssetUnit::factory()->for($this->item)->create(['current_status' => 'disposed']);

        $this->assertFalse($unit->isAvailableForLoan());
    }

    // =========================================================================
    // 2. scopeLoanable() - hanya kembalikan unit yang bisa dipinjam
    // =========================================================================

    public function test_scope_loanable_hanya_kembalikan_unit_aktif_tanpa_loan_aktif(): void
    {
        $aktif = AssetUnit::factory()->for($this->item)->create(['current_status' => 'aktif']);
        AssetUnit::factory()->for($this->item)->create(['current_status' => 'dalam_perbaikan']);
        AssetUnit::factory()->for($this->item)->create(['current_status' => 'tidak_aktif']);
        AssetUnit::factory()->for($this->item)->create(['current_status' => 'disposed']);

        // Unit aktif tapi sudah dipinjam
        $unitPinjam = AssetUnit::factory()->for($this->item)->create(['current_status' => 'dipinjam']);
        Loan::factory()->create([
            'item_id'      => $this->item->id,
            'asset_unit_id'=> $unitPinjam->id,
            'status'       => 'dipinjam',
        ]);

        $loanable = AssetUnit::loanable()->pluck('id');

        $this->assertCount(1, $loanable);
        $this->assertTrue($loanable->contains($aktif->id));
    }

    // =========================================================================
    // 3. store() - blokir unit non-loanable via POST langsung
    // =========================================================================

    private function postLoan(AssetUnit $unit): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->sarpras)->post(route('sarpras.loans.store'), [
            'borrower_name'  => 'Test Peminjam',
            'item_id'        => $this->item->id,
            'asset_unit_id'  => $unit->id,
            'quantity'       => 1,
            'loan_date'      => now()->toDateString(),
            'due_date'       => now()->addDays(7)->toDateString(),
            'purpose'        => 'Kebutuhan praktikum',
        ]);
    }

    public function test_store_menolak_unit_dalam_perbaikan(): void
    {
        $unit = AssetUnit::factory()->for($this->item)->create(['current_status' => 'dalam_perbaikan']);

        $this->postLoan($unit)->assertSessionHasErrors('asset_unit_id');
    }

    public function test_store_menolak_unit_tidak_aktif(): void
    {
        $unit = AssetUnit::factory()->for($this->item)->create(['current_status' => 'tidak_aktif']);

        $this->postLoan($unit)->assertSessionHasErrors('asset_unit_id');
    }

    public function test_store_menolak_unit_disposed(): void
    {
        $unit = AssetUnit::factory()->for($this->item)->create(['current_status' => 'disposed']);

        $this->postLoan($unit)->assertSessionHasErrors('asset_unit_id');
    }

    public function test_store_menolak_unit_yang_sedang_dipinjam(): void
    {
        $unit = AssetUnit::factory()->for($this->item)->create(['current_status' => 'dipinjam']);
        Loan::factory()->create([
            'item_id'      => $this->item->id,
            'asset_unit_id'=> $unit->id,
            'status'       => 'dipinjam',
        ]);

        $this->postLoan($unit)->assertSessionHasErrors('asset_unit_id');
    }

    // =========================================================================
    // 4. Konstanta dan label
    // =========================================================================

    public function test_asset_unit_memiliki_loanable_statuses_constant(): void
    {
        $this->assertNotEmpty(AssetUnit::LOANABLE_STATUSES);
        $this->assertContains('aktif', AssetUnit::LOANABLE_STATUSES);
    }

    public function test_semua_status_enum_memiliki_label_dan_warna(): void
    {
        $statuses = ['aktif', 'dipinjam', 'dalam_perbaikan', 'tidak_aktif', 'disposed'];

        foreach ($statuses as $status) {
            $this->assertArrayHasKey($status, AssetUnit::STATUS_LABELS, "Missing label for: $status");
            $this->assertArrayHasKey($status, AssetUnit::STATUS_BADGE_COLORS, "Missing badge color for: $status");
        }
    }

    // =========================================================================
    // 5. Stage 2A V2: Pending Loan Reservation & Double Booking Prevention
    // =========================================================================

    public function test_unit_aktif_dengan_pending_loan_tidak_tersedia_untuk_pinjam(): void
    {
        $unit = AssetUnit::factory()->for($this->item)->create(['current_status' => 'aktif']);

        Loan::factory()->create([
            'item_id'       => $this->item->id,
            'asset_unit_id' => $unit->id,
            'status'        => 'menunggu',
        ]);

        $this->assertFalse($unit->isAvailableForLoan());
    }

    public function test_scope_loanable_mengecualikan_unit_dengan_pending_loan(): void
    {
        $freeUnit = AssetUnit::factory()->for($this->item)->create(['current_status' => 'aktif']);
        $reservedUnit = AssetUnit::factory()->for($this->item)->create(['current_status' => 'aktif']);

        Loan::factory()->create([
            'item_id'       => $this->item->id,
            'asset_unit_id' => $reservedUnit->id,
            'status'        => 'menunggu',
        ]);

        $loanable = AssetUnit::loanable()->pluck('id');

        $this->assertTrue($loanable->contains($freeUnit->id));
        $this->assertFalse($loanable->contains($reservedUnit->id));
    }

    public function test_store_menolak_unit_yang_sedang_memiliki_pending_loan(): void
    {
        $unit = AssetUnit::factory()->for($this->item)->create(['current_status' => 'aktif']);

        Loan::factory()->create([
            'item_id'       => $this->item->id,
            'asset_unit_id' => $unit->id,
            'status'        => 'menunggu',
        ]);

        $this->postLoan($unit)->assertSessionHasErrors('asset_unit_id');
    }

    // =========================================================================
    // 6. Stage 2A V2: Approval Availability Re-Check & Concurrency Prevention
    // =========================================================================

    public function test_approval_menolak_jika_unit_sudah_dipinjam_oleh_loan_lain(): void
    {
        $unit = AssetUnit::factory()->for($this->item)->create(['current_status' => 'aktif']);

        $loanA = Loan::factory()->create([
            'item_id'       => $this->item->id,
            'asset_unit_id' => $unit->id,
            'status'        => 'menunggu',
        ]);

        $loanB = Loan::factory()->create([
            'item_id'       => $this->item->id,
            'asset_unit_id' => $unit->id,
            'status'        => 'menunggu',
        ]);

        // Sarpras approve loanA pertama kali
        $this->actingAs($this->sarpras)->post(route('sarpras.loans.approve', $loanA))->assertRedirect();
        $this->assertSame('dipinjam', $loanA->fresh()->status);
        $this->assertSame('dipinjam', $unit->fresh()->current_status);

        // Sarpras mencoba approve loanB untuk unit yang sama -> WAJIB ditolak
        $response = $this->actingAs($this->sarpras)->post(route('sarpras.loans.approve', $loanB));
        $response->assertSessionHasErrors('msg');

        // Status loanB tetap menunggu, unit tetap dipinjam oleh loanA
        $this->assertSame('menunggu', $loanB->fresh()->status);
        $this->assertSame('dipinjam', $unit->fresh()->current_status);
    }

    public function test_approval_menolak_jika_unit_berstatus_dalam_perbaikan(): void
    {
        $unit = AssetUnit::factory()->for($this->item)->create(['current_status' => 'dalam_perbaikan']);

        $loan = Loan::factory()->create([
            'item_id'       => $this->item->id,
            'asset_unit_id' => $unit->id,
            'status'        => 'menunggu',
        ]);

        $this->actingAs($this->sarpras)
            ->post(route('sarpras.loans.approve', $loan))
            ->assertSessionHasErrors('msg');

        $this->assertSame('menunggu', $loan->fresh()->status);
        $this->assertSame('dalam_perbaikan', $unit->fresh()->current_status);
    }

    // =========================================================================
    // 7. Stage 2A V2: Condition-Based Return Transitions
    // =========================================================================

    public function test_return_rusak_berat_mengubah_unit_menjadi_dalam_perbaikan(): void
    {
        $unit = AssetUnit::factory()->for($this->item)->create([
            'current_status'    => 'dipinjam',
            'current_condition' => 'baik',
        ]);

        $loan = Loan::factory()->onLoan()->create([
            'item_id'           => $this->item->id,
            'asset_unit_id'     => $unit->id,
            'condition_on_loan' => 'baik',
        ]);

        $this->actingAs($this->sarpras)
            ->post(route('sarpras.loans.return', $loan), [
                'condition_on_return' => 'rusak_berat',
                'notes'               => 'Komponen utama terbakar',
            ])
            ->assertRedirect();

        $this->assertSame('dikembalikan', $loan->fresh()->status);
        $this->assertSame('rusak_berat', $unit->fresh()->current_condition);
        $this->assertSame('dalam_perbaikan', $unit->fresh()->current_status);

        $this->assertDatabaseHas('condition_histories', [
            'item_id'        => $this->item->id,
            'asset_unit_id'  => $unit->id,
            'from_condition' => 'baik',
            'to_condition'   => 'rusak_berat',
        ]);
    }

    public function test_item_aggregate_status_dan_kondisi_terkalkulasi_dengan_benar(): void
    {
        $item = Item::factory()->individual()->create(['stock' => 2]);
        $unit1 = AssetUnit::factory()->for($item)->create([
            'current_status'    => 'aktif',
            'current_condition' => 'baik',
        ]);
        $unit2 = AssetUnit::factory()->for($item)->create([
            'current_status'    => 'dalam_perbaikan',
            'current_condition' => 'rusak_berat',
        ]);

        // Karena masih ada unit1 yang aktif, status agregat tetap aktif (masih tersedia)
        $this->assertSame('aktif', $item->recalculateAggregateStatus());
        // Kondisi agregat mengikuti kondisi terburuk antar unit
        $this->assertSame('rusak_berat', $item->recalculateAggregateCondition());
    }
}
