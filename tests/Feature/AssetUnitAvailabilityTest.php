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

    /** @test */
    public function unit_aktif_tanpa_loan_tersedia_untuk_pinjam(): void
    {
        $unit = AssetUnit::factory()->for($this->item)->create(['current_status' => 'aktif']);

        $this->assertTrue($unit->isAvailableForLoan());
    }

    /** @test */
    public function unit_aktif_dengan_loan_dipinjam_tidak_tersedia(): void
    {
        $unit = AssetUnit::factory()->for($this->item)->create(['current_status' => 'dipinjam']);
        Loan::factory()->create([
            'item_id'      => $this->item->id,
            'asset_unit_id'=> $unit->id,
            'status'       => 'dipinjam',
        ]);

        $this->assertFalse($unit->isAvailableForLoan());
    }

    /** @test */
    public function unit_dalam_perbaikan_tidak_tersedia(): void
    {
        $unit = AssetUnit::factory()->for($this->item)->create(['current_status' => 'dalam_perbaikan']);

        $this->assertFalse($unit->isAvailableForLoan());
    }

    /** @test */
    public function unit_tidak_aktif_tidak_tersedia(): void
    {
        $unit = AssetUnit::factory()->for($this->item)->create(['current_status' => 'tidak_aktif']);

        $this->assertFalse($unit->isAvailableForLoan());
    }

    /** @test */
    public function unit_disposed_tidak_tersedia(): void
    {
        $unit = AssetUnit::factory()->for($this->item)->create(['current_status' => 'disposed']);

        $this->assertFalse($unit->isAvailableForLoan());
    }

    // =========================================================================
    // 2. scopeLoanable() - hanya kembalikan unit yang bisa dipinjam
    // =========================================================================

    /** @test */
    public function scope_loanable_hanya_kembalikan_unit_aktif_tanpa_loan_aktif(): void
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

    /** @test */
    public function store_menolak_unit_dalam_perbaikan(): void
    {
        $unit = AssetUnit::factory()->for($this->item)->create(['current_status' => 'dalam_perbaikan']);

        $this->postLoan($unit)->assertSessionHasErrors('asset_unit_id');
    }

    /** @test */
    public function store_menolak_unit_tidak_aktif(): void
    {
        $unit = AssetUnit::factory()->for($this->item)->create(['current_status' => 'tidak_aktif']);

        $this->postLoan($unit)->assertSessionHasErrors('asset_unit_id');
    }

    /** @test */
    public function store_menolak_unit_disposed(): void
    {
        $unit = AssetUnit::factory()->for($this->item)->create(['current_status' => 'disposed']);

        $this->postLoan($unit)->assertSessionHasErrors('asset_unit_id');
    }

    /** @test */
    public function store_menolak_unit_yang_sedang_dipinjam(): void
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

    /** @test */
    public function asset_unit_memiliki_loanable_statuses_constant(): void
    {
        $this->assertNotEmpty(AssetUnit::LOANABLE_STATUSES);
        $this->assertContains('aktif', AssetUnit::LOANABLE_STATUSES);
    }

    /** @test */
    public function semua_status_enum_memiliki_label_dan_warna(): void
    {
        $statuses = ['aktif', 'dipinjam', 'dalam_perbaikan', 'tidak_aktif', 'disposed'];

        foreach ($statuses as $status) {
            $this->assertArrayHasKey($status, AssetUnit::STATUS_LABELS, "Missing label for: $status");
            $this->assertArrayHasKey($status, AssetUnit::STATUS_BADGE_COLORS, "Missing badge color for: $status");
        }
    }
}
