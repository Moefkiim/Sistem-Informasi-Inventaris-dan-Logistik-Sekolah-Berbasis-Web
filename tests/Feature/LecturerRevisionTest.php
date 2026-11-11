<?php

namespace Tests\Feature;

use App\Models\Distribution;
use App\Models\IncomingItem;
use App\Models\Item;
use App\Models\Location;
use App\Models\OutgoingItem;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LecturerRevisionTest extends TestCase
{
    use RefreshDatabase;

    private User $sarprasUser;

    private User $kepsekUser;

    private User $kajurUser;

    private Item $itemA;

    private Item $itemB;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sarprasUser = User::factory()->create([
            'role' => 'sarpras',
            'department' => null,
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $this->kepsekUser = User::factory()->create([
            'role' => 'kepala_sekolah',
            'department' => null,
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $this->kajurUser = User::factory()->create([
            'role' => 'kajur',
            'department' => 'RPL',
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $this->location = Location::create([
            'code' => 'LAB-RPL-01',
            'name' => 'Lab Komputer RPL 1',
            'building' => 'Gedung B',
            'department' => 'RPL',
        ]);

        $this->itemA = Item::create([
            'code' => 'BRG-TEST-001',
            'name' => 'Laptop Asus ROG',
            'category' => 'Elektronik',
            'unit' => 'Unit',
            'item_type' => 'individual',
            'stock' => 10,
            'location_id' => $this->location->id,
            'condition' => 'baik',
            'department' => 'RPL',
        ]);

        $this->itemB = Item::create([
            'code' => 'BRG-TEST-002',
            'name' => 'Kabel UTP Cat6',
            'category' => 'Jaringan',
            'unit' => 'Meter',
            'item_type' => 'consumable',
            'stock' => 100,
            'location_id' => $this->location->id,
            'condition' => 'baik',
            'department' => 'TKJ',
        ]);
    }

    public function test_incoming_logistics_search_and_filter(): void
    {
        IncomingItem::create([
            'transaction_number' => 'IN-20261101-0001',
            'item_id' => $this->itemA->id,
            'quantity' => 5,
            'source' => 'pembelian',
            'source_origin' => 'Toko Komputer Mandiri',
            'entry_date' => '2026-11-01',
            'user_id' => $this->sarprasUser->id,
            'notes' => 'Faktur 123',
        ]);

        IncomingItem::create([
            'transaction_number' => 'IN-20261105-0002',
            'item_id' => $this->itemB->id,
            'quantity' => 50,
            'source' => 'bantuan',
            'source_origin' => 'PT Telkom Indonesia',
            'entry_date' => '2026-11-05',
            'user_id' => $this->sarprasUser->id,
            'notes' => 'Bantuan CSR',
        ]);

        // Filter search
        $response = $this->actingAs($this->sarprasUser)
            ->get(route('sarpras.logistics.incoming', ['search' => 'Mandiri']));
        $response->assertOk();
        $response->assertSee('IN-20261101-0001');
        $response->assertDontSee('IN-20261105-0002');

        // Filter source
        $responseSource = $this->actingAs($this->sarprasUser)
            ->get(route('sarpras.logistics.incoming', ['source' => 'bantuan']));
        $responseSource->assertOk();
        $responseSource->assertSee('IN-20261105-0002');
        $responseSource->assertDontSee('IN-20261101-0001');

        // Filter date range
        $responseDate = $this->actingAs($this->sarprasUser)
            ->get(route('sarpras.logistics.incoming', ['start_date' => '2026-11-03', 'end_date' => '2026-11-06']));
        $responseDate->assertOk();
        $responseDate->assertSee('IN-20261105-0002');
        $responseDate->assertDontSee('IN-20261101-0001');
    }

    public function test_outgoing_logistics_search_and_filter(): void
    {
        OutgoingItem::create([
            'transaction_number' => 'OUT-20261101-0001',
            'item_id' => $this->itemA->id,
            'quantity' => 1,
            'exit_date' => '2026-11-01',
            'reason' => 'Rusak Total',
            'user_id' => $this->sarprasUser->id,
            'notes' => 'Korsleting motherboard',
        ]);

        OutgoingItem::create([
            'transaction_number' => 'OUT-20261105-0002',
            'item_id' => $this->itemB->id,
            'quantity' => 10,
            'exit_date' => '2026-11-05',
            'reason' => 'Hibah / Penyaluran Luar',
            'user_id' => $this->sarprasUser->id,
            'notes' => 'Disalurkan ke SMP binaan',
        ]);

        // Filter reason
        $response = $this->actingAs($this->sarprasUser)
            ->get(route('sarpras.logistics.outgoing', ['reason' => 'Rusak Total']));
        $response->assertOk();
        $response->assertSee('OUT-20261101-0001');
        $response->assertDontSee('OUT-20261105-0002');

        // Filter date range
        $responseDate = $this->actingAs($this->sarprasUser)
            ->get(route('sarpras.logistics.outgoing', ['start_date' => '2026-11-03', 'end_date' => '2026-11-06']));
        $responseDate->assertOk();
        $responseDate->assertSee('OUT-20261105-0002');
        $responseDate->assertDontSee('OUT-20261101-0001');
    }

    public function test_distribution_logistics_search_and_filter(): void
    {
        Distribution::create([
            'distribution_number' => 'DST-20261101-0001',
            'item_id' => $this->itemA->id,
            'quantity' => 2,
            'to_location_id' => $this->location->id,
            'recipient_department' => 'RPL',
            'recipient_name' => 'Budi Santoso',
            'distribution_date' => '2026-11-01',
            'user_id' => $this->sarprasUser->id,
            'notes' => 'Untuk ruang guru',
        ]);

        Distribution::create([
            'distribution_number' => 'DST-20261105-0002',
            'item_id' => $this->itemB->id,
            'quantity' => 20,
            'to_location_id' => $this->location->id,
            'recipient_department' => 'TKJ',
            'recipient_name' => 'Ahmad Dani',
            'distribution_date' => '2026-11-05',
            'user_id' => $this->sarprasUser->id,
            'notes' => 'Praktik jaringan',
        ]);

        // Filter search recipient
        $response = $this->actingAs($this->sarprasUser)
            ->get(route('sarpras.logistics.distributions', ['search' => 'Budi']));
        $response->assertOk();
        $response->assertSee('DST-20261101-0001');
        $response->assertDontSee('DST-20261105-0002');

        // Filter department
        $responseDept = $this->actingAs($this->sarprasUser)
            ->get(route('sarpras.logistics.distributions', ['recipient_department' => 'TKJ']));
        $responseDept->assertOk();
        $responseDept->assertSee('DST-20261105-0002');
        $responseDept->assertDontSee('DST-20261101-0001');
    }

    public function test_sarpras_submission_review_filter(): void
    {
        $sub1 = new Submission([
            'submission_number' => 'SUB-20261101-0001',
            'user_id' => $this->kajurUser->id,
            'department' => 'RPL',
            'title' => 'Pengadaan PC Desktop Lab RPL',
            'purpose' => 'Kebutuhan praktik pemrograman',
        ]);
        $sub1->status = 'submitted';
        $sub1->created_at = now()->subDays(5);
        $sub1->save();

        $sub2 = new Submission([
            'submission_number' => 'SUB-20261105-0002',
            'user_id' => $this->kajurUser->id,
            'department' => 'TKJ',
            'title' => 'Pengadaan Router MikroTik',
            'purpose' => 'Kebutuhan ujian sertifikasi',
        ]);
        $sub2->status = 'approved';
        $sub2->created_at = now()->subDays(1);
        $sub2->save();

        // Filter status
        $response = $this->actingAs($this->sarprasUser)
            ->get(route('sarpras.submissions.index', ['status' => 'submitted']));
        $response->assertOk();
        $response->assertSee('SUB-20261101-0001');
        $response->assertDontSee('SUB-20261105-0002');

        // Filter department
        $responseDept = $this->actingAs($this->sarprasUser)
            ->get(route('sarpras.submissions.index', ['department' => 'TKJ']));
        $responseDept->assertOk();
        $responseDept->assertSee('SUB-20261105-0002');
        $responseDept->assertDontSee('SUB-20261101-0001');
    }

    public function test_kepala_sekolah_approval_shows_submission_date(): void
    {
        $sub = new Submission([
            'submission_number' => 'SUB-20261102-0001',
            'user_id' => $this->kajurUser->id,
            'department' => 'RPL',
            'title' => 'Pengadaan Switch 24 Port',
            'purpose' => 'Untuk peremajaan jaringan',
        ]);
        $sub->status = 'reviewed_sarpras';
        $sub->sarpras_user_id = $this->sarprasUser->id;
        $sub->sarpras_notes = 'Anggaran tersedia dan layak disetujui.';
        $sub->reviewed_at = now()->subDay();
        $sub->created_at = now()->subDays(3);
        $sub->save();

        // Test approval index table has submission date column & value
        $response = $this->actingAs($this->kepsekUser)
            ->get(route('kepala_sekolah.approval.index'));
        $response->assertOk();
        $response->assertSee('Tgl. Pengajuan');
        $response->assertSee($sub->created_at->format('d/m/Y H:i'));

        // Test dashboard has submission date in tasks
        $responseHome = $this->actingAs($this->kepsekUser)
            ->get(route('home'));
        $responseHome->assertOk();
        $responseHome->assertSee('Diajukan: '.$sub->created_at->format('d/m/Y'));
    }
}
