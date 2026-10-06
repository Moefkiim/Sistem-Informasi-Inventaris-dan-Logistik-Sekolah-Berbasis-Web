<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Loan;
use App\Models\Location;
use App\Models\Submission;
use App\Models\SubmissionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoReadinessTest extends TestCase
{
    use RefreshDatabase;

    private User $kajur;

    private User $sarpras;

    private User $kepsek;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->kajur = User::where('username', 'kajur_rpl')->firstOrFail();
        $this->sarpras = User::where('username', 'sarpras')->firstOrFail();
        $this->kepsek = User::where('username', 'kepsek')->firstOrFail();

        $location = Location::factory()->create([
            'code' => 'LAB-RPL-01',
            'name' => 'Laboratorium RPL',
            'department' => 'Rekayasa Perangkat Lunak',
        ]);

        $item = Item::factory()->create([
            'code' => 'BRG-RPL-0001',
            'inventory_number' => 'INV-RPL-0001',
            'name' => 'Laptop Asus Vivobook Pro',
            'category' => 'Elektronik',
            'unit' => 'Unit',
            'stock' => 1,
            'item_type' => 'individual',
            'source' => 'pembelian',
            'department' => 'Rekayasa Perangkat Lunak',
            'location_id' => $location->id,
            'current_condition' => 'baik',
            'current_status' => 'dipinjam',
        ]);

        Loan::create([
            'loan_number' => 'LN-20261006-0001',
            'borrower_user_id' => $this->kajur->id,
            'borrower_name' => $this->kajur->name,
            'borrower_department' => 'Rekayasa Perangkat Lunak',
            'recorded_by' => $this->sarpras->id,
            'item_id' => $item->id,
            'quantity' => 1,
            'loan_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'purpose' => 'Praktik kerja siswa kelas XII',
            'status' => 'dipinjam',
            'condition_on_loan' => 'baik',
            'approved_by' => $this->sarpras->id,
            'approved_at' => now(),
        ]);

        $submission = Submission::create([
            'submission_number' => 'REQ-20261006-0001',
            'user_id' => $this->kajur->id,
            'department' => 'Rekayasa Perangkat Lunak',
            'title' => 'Pengadaan Laptop Praktik RPL',
            'purpose' => 'Menunjang kegiatan pembelajaran praktik.',
        ]);
        $submission->forceFill([
            'status' => 'reviewed_sarpras',
            'sarpras_user_id' => $this->sarpras->id,
            'sarpras_notes' => 'Stok tersedia. Diteruskan ke Kepala Sekolah.',
            'reviewed_at' => now(),
        ])->save();

        SubmissionItem::create([
            'submission_id' => $submission->id,
            'item_name' => 'Laptop Asus Vivobook Pro',
            'quantity' => 10,
            'unit' => 'Unit',
            'estimated_price' => 8500000,
        ]);

        $submission->recordStatusChange(null, 'draft', $this->kajur);
        $submission->recordStatusChange('draft', 'submitted', $this->kajur);
        $submission->recordStatusChange('submitted', 'reviewed_sarpras', $this->sarpras, 'Stok tersedia.');
    }

    public function test_demo_akun_terprovisi_dengan_role_dan_status_aktif(): void
    {
        $this->assertTrue($this->kajur->isKajur());
        $this->assertTrue($this->sarpras->isSarpras());
        $this->assertTrue($this->kepsek->isKepalaSekolah());
        $this->assertTrue($this->kajur->is_active);
        $this->assertTrue($this->sarpras->is_active);
        $this->assertTrue($this->kepsek->is_active);
    }

    public function test_akun_demo_nonaktif_tidak_bisa_login(): void
    {
        $this->post('/login', ['login' => 'user_nonaktif', 'password' => 'password123'])
            ->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_akun_demo_kajur_bisa_login_dan_mengakses_halaman(): void
    {
        $this->post('/login', ['login' => $this->kajur->username, 'password' => 'password123'])
            ->assertRedirect(route('home'));

        $this->get(route('home'))->assertOk();

        $submission = Submission::where('user_id', $this->kajur->id)->firstOrFail();
        $item = Item::firstOrFail();

        $this->get(route('kajur.submissions.index'))->assertOk();
        $this->get(route('kajur.submissions.show', $submission))->assertOk()->assertSee('Riwayat Proses Pengajuan');
        $this->get(route('kajur.inventory.index'))->assertOk();
        $this->get(route('kajur.inventory.show', $item))->assertOk()->assertSee('Peminjaman Aktif');
        $this->get(route('reports.index'))->assertOk();
        $this->get(route('reports.pdf', ['type' => 'inventory', 'department' => 'Rekayasa Perangkat Lunak']))->assertOk();
        $this->get(route('reports.excel', ['type' => 'submission', 'department' => 'Rekayasa Perangkat Lunak']))->assertOk();
    }

    public function test_akun_demo_sarpras_bisa_login_dan_mengakses_halaman(): void
    {
        $this->post('/login', ['login' => $this->sarpras->username, 'password' => 'password123'])
            ->assertRedirect(route('home'));

        $this->get(route('home'))->assertOk();

        $item = Item::firstOrFail();
        $loan = Loan::firstOrFail();
        $submission = Submission::firstOrFail();

        $this->get(route('sarpras.inventory.index'))->assertOk();
        $this->get(route('sarpras.inventory.show', $item))->assertOk()->assertSee('Peminjaman Aktif');
        $this->get(route('sarpras.loans.index'))->assertOk();
        $this->get(route('sarpras.loans.create'))->assertOk();
        $this->get(route('sarpras.loans.show', $loan))->assertOk();
        $this->get(route('sarpras.submissions.index'))->assertOk();
        $this->get(route('sarpras.submissions.show', $submission))->assertOk()->assertSee('Riwayat Proses Pengajuan');
        $this->get(route('sarpras.activity_logs.index'))->assertOk();
        $this->get(route('sarpras.locations.index'))->assertOk();
        $this->get(route('sarpras.logistics.incoming'))->assertOk();
        $this->get(route('sarpras.logistics.outgoing'))->assertOk();
        $this->get(route('sarpras.logistics.distributions'))->assertOk();
        $this->get(route('reports.index'))->assertOk();
        $this->get(route('reports.pdf', ['type' => 'inventory']))->assertOk();
        $this->get(route('reports.excel', ['type' => 'inventory']))->assertOk();
    }

    public function test_akun_demo_kepala_sekolah_bisa_login_dan_mengakses_halaman(): void
    {
        $this->post('/login', ['login' => $this->kepsek->username, 'password' => 'password123'])
            ->assertRedirect(route('home'));

        $this->get(route('home'))->assertOk();

        $submission = Submission::firstOrFail();

        $this->get(route('kepala_sekolah.approval.index'))->assertOk();
        $this->get(route('kepala_sekolah.approval.show', $submission))->assertOk()->assertSee('Riwayat Proses Pengajuan');
        $this->get(route('reports.index'))->assertOk();
        $this->get(route('reports.pdf', ['type' => 'submission']))->assertOk();
        $this->get(route('reports.excel', ['type' => 'incoming']))->assertOk();
    }
}
