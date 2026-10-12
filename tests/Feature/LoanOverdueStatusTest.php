<?php

namespace Tests\Feature;

use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LoanOverdueStatusTest extends TestCase
{
    use RefreshDatabase;

    private function sarpras(): User
    {
        return User::factory()->sarpras()->create();
    }

    public function test_scope_overdue_hanya_dipinjam_terlambat_lewat_tenggat(): void
    {
        $overdue = Loan::factory()->create([
            'status' => 'dipinjam',
            'due_date' => now()->subDays(2)->toDateString(),
        ]);
        $stored = Loan::factory()->overdue()->create();
        $future = Loan::factory()->create([
            'status' => 'dipinjam',
            'due_date' => now()->addDays(5)->toDateString(),
        ]);
        $returned = Loan::factory()->returned()->create();

        $this->assertEqualsCanonicalizing(
            [$overdue->id, $stored->id],
            Loan::overdue()->pluck('id')->all()
        );
        $this->assertFalse($future->isOverdue());
        $this->assertFalse($returned->isOverdue());
    }

    public function test_batas_hari_jatuh_tempo_hari_ini_belum_terlambat(): void
    {
        $this->travelTo(Carbon::parse('2026-10-12 10:00:00', 'Asia/Jakarta'));

        $dueToday = Loan::factory()->create([
            'status' => 'dipinjam',
            'loan_date' => '2026-10-01',
            'due_date' => '2026-10-12',
        ]);
        $dueYesterday = Loan::factory()->create([
            'status' => 'dipinjam',
            'loan_date' => '2026-10-01',
            'due_date' => '2026-10-11',
        ]);

        $this->assertFalse($dueToday->isOverdue());
        $this->assertSame('dipinjam', $dueToday->effective_status);

        $this->assertTrue($dueYesterday->isOverdue());
        $this->assertSame('terlambat', $dueYesterday->effective_status);

        $this->assertSame(
            [$dueYesterday->id],
            Loan::overdue()->pluck('id')->all()
        );
    }

    public function test_daftar_menampilkan_terlambat_efektif_tanpa_menulis_status(): void
    {
        $loan = Loan::factory()->create([
            'status' => 'dipinjam',
            'due_date' => now()->subDays(3)->toDateString(),
        ]);

        // Melapor lebih dulu (tanpa membuka daftar peminjaman) tetap konsisten.
        $this->actingAs($this->sarpras())
            ->get(route('reports.index', ['type' => 'loan']))
            ->assertOk()
            ->assertSee('Terlambat');

        $this->actingAs($this->sarpras())
            ->get(route('sarpras.loans.index'))
            ->assertOk()
            ->assertSee($loan->loan_number)
            ->assertSee('Terlambat');

        $this->assertSame('dipinjam', $loan->fresh()->status);
        $this->assertSame('terlambat', $loan->fresh()->effective_status);
    }

    public function test_get_daftar_peminjaman_tidak_menulis_ke_tabel_loans(): void
    {
        Loan::factory()->create([
            'status' => 'dipinjam',
            'due_date' => now()->subDays(3)->toDateString(),
        ]);

        $updates = 0;
        DB::listen(function ($query) use (&$updates) {
            if (str_contains(strtolower($query->sql), 'update') && str_contains(strtolower($query->sql), 'loans')) {
                $updates++;
            }
        });

        $this->actingAs($this->sarpras())
            ->get(route('sarpras.loans.index'))
            ->assertOk();

        $this->assertSame(0, $updates, 'GET daftar peminjaman tidak boleh menulis ke tabel loans.');
    }

    public function test_laporan_filter_status_terlambat_memakai_scope(): void
    {
        $overdue = Loan::factory()->create([
            'status' => 'dipinjam',
            'due_date' => now()->subDays(2)->toDateString(),
        ]);
        $stored = Loan::factory()->overdue()->create();
        $future = Loan::factory()->create([
            'status' => 'dipinjam',
            'due_date' => now()->addDays(5)->toDateString(),
        ]);

        $this->actingAs($this->sarpras())
            ->get(route('reports.index', ['type' => 'loan', 'status' => 'terlambat']))
            ->assertOk()
            ->assertSee($overdue->loan_number)
            ->assertSee($stored->loan_number)
            ->assertDontSee($future->loan_number);
    }

    public function test_ringkasan_laporan_menghitung_terlambat_efektif(): void
    {
        Loan::factory()->create([
            'status' => 'dipinjam',
            'due_date' => now()->subDays(2)->toDateString(),
        ]);
        Loan::factory()->create([
            'status' => 'dipinjam',
            'due_date' => now()->addDays(5)->toDateString(),
        ]);
        Loan::factory()->overdue()->create();

        $this->actingAs($this->sarpras())
            ->get(route('reports.index', ['type' => 'loan']))
            ->assertOk()
            ->assertViewHas('summary', function (array $summary) {
                return $summary['statuses']['terlambat'] === 2
                    && $summary['statuses']['dipinjam'] === 1
                    && $summary['overdue'] === 2;
            });
    }

    public function test_pinjaman_terlambat_lama_tetap_valid_dan_bisa_dikembalikan(): void
    {
        $loan = Loan::factory()->overdue()->create();
        $this->assertTrue($loan->isOverdue());
        $this->assertTrue($loan->isReturnable());

        $this->actingAs($this->sarpras())
            ->post(route('sarpras.loans.return', $loan), [
                'condition_on_return' => 'baik',
            ])
            ->assertRedirect();

        $this->assertSame('dikembalikan', $loan->fresh()->status);
    }
}
