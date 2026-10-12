<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportLoanTest extends TestCase
{
    use RefreshDatabase;

    private function sarpras(): User
    {
        return User::factory()->sarpras()->create();
    }

    private function loan(array $attributes = []): Loan
    {
        $loanDate = now()->subDays(20)->toDateString();

        return Loan::factory()->create(array_merge([
            'borrower_name' => 'Peminjam Uji',
            'borrower_department' => 'RPL',
            'loan_date' => $loanDate,
            'due_date' => now()->subDays(5)->toDateString(),
            'status' => 'dikembalikan',
            'return_date' => now()->toDateString(),
        ], $attributes));
    }

    public function test_hari_terlambat_dihitung_dari_rencana_ke_tanggal_kembali(): void
    {
        $late = $this->loan([
            'due_date' => '2026-10-01',
            'return_date' => '2026-10-06',
            'status' => 'dikembalikan',
        ]);

        $onTime = $this->loan([
            'due_date' => '2026-10-01',
            'return_date' => '2026-09-30',
            'status' => 'dikembalikan',
        ]);

        $stillBorrowed = $this->loan([
            'due_date' => '2026-10-01',
            'return_date' => null,
            'status' => 'dipinjam',
        ]);

        $pending = $this->loan([
            'due_date' => '2026-10-01',
            'return_date' => null,
            'status' => 'menunggu',
        ]);

        $this->assertSame(5, $late->lateDays());
        $this->assertSame(0, $onTime->lateDays());
        $this->assertSame(0, $pending->lateDays());
        $this->assertGreaterThan(0, $stillBorrowed->lateDays());
    }

    public function test_laporan_peminjaman_menampilkan_kolom_dan_ringkasan(): void
    {
        $loan = $this->loan();

        $response = $this->actingAs($this->sarpras())
            ->get(route('reports.index', ['type' => 'loan']));

        $response->assertOk()
            ->assertSee('Laporan Peminjaman')
            ->assertSee($loan->loan_number)
            ->assertSee('Peminjam Uji')
            ->assertSee('Rencana Kembali')
            ->assertSee('Hari Terlambat')
            ->assertSee('Terlambat')
            ->assertSee('5 hari');
    }

    public function test_kajur_hanya_melihat_peminjaman_jurusannya(): void
    {
        $kajur = User::factory()->kajur()->create(['department' => 'RPL']);
        $loanRpl = $this->loan(['borrower_department' => 'RPL']);
        $loanTkj = $this->loan(['borrower_department' => 'TKJ']);

        $response = $this->actingAs($kajur)
            ->get(route('reports.index', ['type' => 'loan']));

        $response->assertOk()
            ->assertSee($loanRpl->loan_number)
            ->assertDontSee($loanTkj->loan_number);
    }

    public function test_kepala_sekolah_hanya_membaca_laporan_peminjaman(): void
    {
        $loan = $this->loan();

        $this->actingAs(User::factory()->kepalaSekolah()->create())
            ->get(route('reports.index', ['type' => 'loan']))
            ->assertOk()
            ->assertSee($loan->loan_number);
    }

    public function test_filter_status_peminjaman(): void
    {
        $dipinjam = $this->loan(['status' => 'dipinjam', 'return_date' => null]);
        $dikembalikan = $this->loan(['status' => 'dikembalikan']);

        $response = $this->actingAs($this->sarpras())
            ->get(route('reports.index', ['type' => 'loan', 'status' => 'dipinjam']));

        $response->assertOk()
            ->assertSee($dipinjam->loan_number)
            ->assertDontSee($dikembalikan->loan_number);
    }

    public function test_filter_barang_atau_unit(): void
    {
        $cocok = $this->loan(['item_id' => Item::factory()->create(['name' => 'Proyektor Uji'])->id]);
        $lain = $this->loan(['item_id' => Item::factory()->create(['name' => 'Kursi Uji'])->id]);

        $response = $this->actingAs($this->sarpras())
            ->get(route('reports.index', ['type' => 'loan', 'item' => 'Proyektor']));

        $response->assertOk()
            ->assertSee($cocok->loan_number)
            ->assertDontSee($lain->loan_number);
    }

    public function test_filter_periode_tanggal_pinjam(): void
    {
        $september = $this->loan([
            'loan_date' => '2026-09-10',
            'due_date' => '2026-09-17',
            'return_date' => '2026-09-17',
        ]);
        $oktober = $this->loan([
            'loan_date' => '2026-10-10',
            'due_date' => '2026-10-17',
            'return_date' => '2026-10-17',
        ]);

        $response = $this->actingAs($this->sarpras())
            ->get(route('reports.index', [
                'type' => 'loan',
                'start_date' => '2026-09-01',
                'end_date' => '2026-09-30',
            ]));

        $response->assertOk()
            ->assertSee($september->loan_number)
            ->assertDontSee($oktober->loan_number);
    }

    public function test_ekspor_pdf_excel_dan_cetak_membawa_filter_yang_sama(): void
    {
        $this->loan();
        $sarpras = $this->sarpras();

        $index = $this->actingAs($sarpras)
            ->get(route('reports.index', ['type' => 'loan', 'status' => 'dikembalikan']));
        $index->assertOk();
        $html = $index->getContent();
        $this->assertStringContainsString('type=loan', $html);
        $this->assertStringContainsString('status=dikembalikan', $html);
        $this->assertStringContainsString('export=print', $html);

        $pdf = $this->actingAs($sarpras)
            ->get(route('reports.pdf', ['type' => 'loan', 'status' => 'dikembalikan']));
        $pdf->assertOk();
        $this->assertStringContainsString('application/pdf', $pdf->headers->get('content-type'));

        $excel = $this->actingAs($sarpras)
            ->get(route('reports.excel', ['type' => 'loan', 'status' => 'dikembalikan']));
        $excel->assertOk();
        $this->assertStringContainsString('laporan-loan-', $excel->headers->get('content-disposition'));

        $print = $this->actingAs($sarpras)
            ->get(route('reports.index', ['type' => 'loan', 'status' => 'dikembalikan', 'export' => 'print']));
        $print->assertOk()->assertSee('Hari Terlambat');
    }
}
