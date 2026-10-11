<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Loan;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoReadinessTest extends TestCase
{
    use RefreshDatabase;

    private User $kajur;

    private User $sarpras;

    private User $kepsek;

    private Item $item;

    private Loan $loan;

    private Submission $submission;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->kajur = User::where('username', 'kajur_rpl')->firstOrFail();
        $this->sarpras = User::where('username', 'sarpras')->firstOrFail();
        $this->kepsek = User::where('username', 'kepsek')->firstOrFail();

        $this->item = Item::where('code', 'BRG-RPL-0001')->firstOrFail();
        $this->loan = Loan::where('status', 'dipinjam')->firstOrFail();
        $this->submission = Submission::where('user_id', $this->kajur->id)
            ->where('status', 'reviewed_sarpras')
            ->firstOrFail();
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

        $this->get(route('home'))->assertOk()
            ->assertViewHas('totalItems', fn ($total) => $total >= 1);

        $this->get(route('kajur.submissions.index'))->assertOk();
        $this->get(route('kajur.submissions.show', $this->submission))->assertOk()->assertSee('Linimasa Proses Pengajuan');
        $this->get(route('kajur.inventory.index'))->assertOk();
        $this->get(route('kajur.inventory.show', $this->item))->assertOk()->assertSee('Peminjaman Aktif');
        $this->get(route('reports.index'))->assertOk()->assertSee('BRG-RPL-0001');
        $this->get(route('reports.pdf', ['type' => 'inventory', 'department' => 'RPL']))->assertOk();
        $this->get(route('reports.excel', ['type' => 'submission', 'department' => 'RPL']))->assertOk();
    }

    public function test_akun_demo_sarpras_bisa_login_dan_mengakses_halaman(): void
    {
        $this->post('/login', ['login' => $this->sarpras->username, 'password' => 'password123'])
            ->assertRedirect(route('home'));

        $this->get(route('home'))->assertOk();

        $this->get(route('sarpras.inventory.index'))->assertOk();
        $this->get(route('sarpras.inventory.show', $this->item))->assertOk()->assertSee('Peminjaman Aktif');
        $this->get(route('sarpras.loans.index'))->assertOk();
        $this->get(route('sarpras.loans.create'))->assertOk();
        $this->get(route('sarpras.loans.show', $this->loan))->assertOk();
        $this->get(route('sarpras.submissions.index'))->assertOk();
        $this->get(route('sarpras.submissions.show', $this->submission))->assertOk()->assertSee('Linimasa Proses Pengajuan');
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

        $this->get(route('kepala_sekolah.approval.index'))->assertOk();
        $this->get(route('kepala_sekolah.approval.show', $this->submission))->assertOk()->assertSee('Linimasa Proses Pengajuan');
        $this->get(route('reports.index'))->assertOk();
        $this->get(route('reports.pdf', ['type' => 'submission']))->assertOk();
        $this->get(route('reports.excel', ['type' => 'incoming']))->assertOk();
    }
}
