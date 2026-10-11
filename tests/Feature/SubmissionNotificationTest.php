<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Submission;
use App\Models\SubmissionItem;
use App\Models\User;
use App\Notifications\SubmissionNotification;
use App\Services\TaskOverview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SubmissionNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function makeSubmission(User $kajur, string $status): Submission
    {
        $submission = Submission::create([
            'submission_number' => 'REQ-NOTIF-'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT),
            'user_id' => $kajur->id,
            'department' => $kajur->department,
            'title' => 'Pengadaan Notifikasi',
            'purpose' => 'Kebutuhan praktik',
        ]);
        $submission->status = $status;
        $submission->save();

        SubmissionItem::create([
            'submission_id' => $submission->id,
            'item_name' => 'Laptop Praktik',
            'quantity' => 1,
            'unit' => 'Unit',
            'estimated_price' => 500000,
        ]);

        return $submission->fresh('items');
    }

    public function test_pengajuan_diajukan_mengirim_notifikasi_ke_semua_sarpras_aktif(): void
    {
        Notification::fake();

        $kajur = User::factory()->kajur()->create();
        $sarprasAktif = User::factory()->sarpras()->create();
        $sarprasNonaktif = User::factory()->sarpras()->create(['is_active' => false]);
        $kepalaSekolah = User::factory()->kepalaSekolah()->create();
        $submission = $this->makeSubmission($kajur, 'draft');

        $this->actingAs($kajur)
            ->post(route('kajur.submissions.submitDraft', $submission))
            ->assertRedirect();

        Notification::assertSentTo(
            $sarprasAktif,
            SubmissionNotification::class,
            fn (SubmissionNotification $n) => $n->kind === 'submitted' && $n->submissionId === $submission->id
        );
        Notification::assertNotSentTo($sarprasNonaktif, SubmissionNotification::class);
        Notification::assertNotSentTo($kepalaSekolah, SubmissionNotification::class);
    }

    public function test_verifikasi_sarpras_mengirim_notifikasi_ke_kepala_sekolah(): void
    {
        Notification::fake();

        $kajur = User::factory()->kajur()->create();
        $sarpras = User::factory()->sarpras()->create();
        $kepalaSekolah = User::factory()->kepalaSekolah()->create();
        $submission = $this->makeSubmission($kajur, 'submitted');

        $this->actingAs($sarpras)
            ->post(route('sarpras.submissions.process', $submission), [
                'action' => 'review',
                'sarpras_notes' => 'Sudah diverifikasi kelayakannya.',
            ])
            ->assertRedirect();

        Notification::assertSentTo(
            $kepalaSekolah,
            SubmissionNotification::class,
            fn (SubmissionNotification $n) => $n->kind === 'reviewed'
        );
        Notification::assertNotSentTo($kajur, SubmissionNotification::class);
    }

    public function test_persetujuan_mengirim_notifikasi_ke_pengaju_dan_sarpras(): void
    {
        Notification::fake();

        $kajur = User::factory()->kajur()->create();
        $sarpras = User::factory()->sarpras()->create();
        $kepalaSekolah = User::factory()->kepalaSekolah()->create();
        $submission = $this->makeSubmission($kajur, 'reviewed_sarpras');

        $this->actingAs($kepalaSekolah)
            ->post(route('kepala_sekolah.approval.decide', $submission), [
                'action' => 'approve',
            ])
            ->assertRedirect();

        Notification::assertSentTo($kajur, SubmissionNotification::class, fn (SubmissionNotification $n) => $n->kind === 'approved');
        Notification::assertSentTo($sarpras, SubmissionNotification::class, fn (SubmissionNotification $n) => $n->kind === 'approved');
    }

    public function test_penolakan_mengirim_notifikasi_ke_pengaju_dengan_alasan(): void
    {
        Notification::fake();

        $kajur = User::factory()->kajur()->create();
        $sarpras = User::factory()->sarpras()->create();
        $submission = $this->makeSubmission($kajur, 'submitted');

        $this->actingAs($sarpras)
            ->post(route('sarpras.submissions.process', $submission), [
                'action' => 'reject',
                'sarpras_notes' => 'Barang tidak sesuai kebutuhan jurusan.',
            ])
            ->assertRedirect();

        Notification::assertSentTo(
            $kajur,
            SubmissionNotification::class,
            fn (SubmissionNotification $n) => $n->kind === 'rejected'
                && str_contains($n->message, 'Barang tidak sesuai kebutuhan jurusan.')
        );
    }

    public function test_realisasi_mengirim_notifikasi_ke_pengaju(): void
    {
        Notification::fake();

        $kajur = User::factory()->kajur()->create();
        $sarpras = User::factory()->sarpras()->create();
        $location = Location::factory()->create();
        $submission = $this->makeSubmission($kajur, 'approved');

        $payload = [
            'source' => 'pembelian',
            'entry_date' => '2026-10-05',
            'realization_notes' => 'Barang diterima gudang.',
            'lines' => [[
                'submission_item_id' => $submission->items->first()->id,
                'received_quantity' => 1,
                'mode' => 'new',
                'item_id' => '',
                'new_name' => 'Barang Realisasi',
                'new_category' => 'Elektronik',
                'new_item_type' => 'consumable',
                'new_unit' => 'Unit',
                'new_location_id' => $location->id,
                'new_condition' => 'baik',
            ]],
        ];

        $this->actingAs($sarpras)
            ->post(route('sarpras.submissions.realize', $submission), $payload)
            ->assertRedirect();

        Notification::assertSentTo(
            $kajur,
            SubmissionNotification::class,
            fn (SubmissionNotification $n) => $n->kind === 'realized'
        );
    }

    public function test_pengguna_tidak_dapat_membuka_notifikasi_milik_orang_lain(): void
    {
        $kajurA = User::factory()->kajur()->create();
        $kajurB = User::factory()->kajur()->create();
        $submission = $this->makeSubmission($kajurA, 'submitted');

        $kajurA->notify(new SubmissionNotification($submission->id, $submission->submission_number, 'submitted', 'Judul', 'Pesan'));
        $notification = $kajurA->notifications()->firstOrFail();

        $this->actingAs($kajurB)
            ->get(route('notifications.open', $notification->id))
            ->assertNotFound();
    }

    public function test_membuka_notifikasi_menandai_dibaca_dan_mengarahkan(): void
    {
        $kajur = User::factory()->kajur()->create();
        $submission = $this->makeSubmission($kajur, 'submitted');

        $kajur->notify(new SubmissionNotification($submission->id, $submission->submission_number, 'submitted', 'Judul', 'Pesan'));
        $notification = $kajur->notifications()->firstOrFail();

        $this->actingAs($kajur)
            ->get(route('notifications.open', $notification->id))
            ->assertRedirect(route('kajur.submissions.show', $submission));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_tandai_semua_notifikasi_dibaca(): void
    {
        $kajur = User::factory()->kajur()->create();
        $submission = $this->makeSubmission($kajur, 'submitted');

        $kajur->notify(new SubmissionNotification($submission->id, $submission->submission_number, 'submitted', 'A', 'Pesan A'));
        $kajur->notify(new SubmissionNotification($submission->id, $submission->submission_number, 'submitted', 'B', 'Pesan B'));

        $this->assertSame(2, $kajur->unreadNotifications()->count());

        $this->actingAs($kajur)
            ->post(route('notifications.read_all'))
            ->assertRedirect();

        $this->assertSame(0, $kajur->fresh()->unreadNotifications()->count());
    }

    public function test_halaman_semua_notifikasi_hanya_menampilkan_milik_pengguna(): void
    {
        $kajurA = User::factory()->kajur()->create();
        $kajurB = User::factory()->kajur()->create();
        $submission = $this->makeSubmission($kajurA, 'submitted');

        $kajurA->notify(new SubmissionNotification($submission->id, $submission->submission_number, 'submitted', 'Untuk A', 'Pesan hanya A'));
        $kajurB->notify(new SubmissionNotification($submission->id, $submission->submission_number, 'submitted', 'Untuk B', 'Pesan hanya B'));

        $this->actingAs($kajurA)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Pesan hanya A')
            ->assertDontSee('Pesan hanya B');
    }

    public function test_badge_sidebar_sesuai_data_dan_terbatas_pada_jurusan_kajur(): void
    {
        $overview = app(TaskOverview::class);

        $sarpras = User::factory()->sarpras()->create();
        $kepalaSekolah = User::factory()->kepalaSekolah()->create();
        $kajurA = User::factory()->kajur()->create();
        $kajurB = User::factory()->kajur()->create();

        $this->makeSubmission(User::factory()->kajur()->create(), 'submitted');
        $this->makeSubmission($kajurA, 'submitted');
        $this->makeSubmission($kajurA, 'approved');
        $this->makeSubmission($kajurA, 'reviewed_sarpras');
        $this->makeSubmission($kajurA, 'draft');
        $this->makeSubmission($kajurA, 'draft');
        $this->makeSubmission($kajurB, 'draft');

        $this->assertSame(['review' => 2, 'realize' => 1], $overview->badges($sarpras));
        $this->assertSame(['approval' => 1], $overview->badges($kepalaSekolah));
        $this->assertSame(['draft' => 2], $overview->badges($kajurA));
        $this->assertSame(['draft' => 1], $overview->badges($kajurB));
    }

    public function test_badge_menggunakan_satu_query_agregat(): void
    {
        $overview = app(TaskOverview::class);
        $sarpras = User::factory()->sarpras()->create();

        $this->makeSubmission(User::factory()->kajur()->create(), 'submitted');
        $this->makeSubmission(User::factory()->kajur()->create(), 'approved');

        DB::flushQueryLog();
        DB::enableQueryLog();

        $overview->badges($sarpras);

        $submissionQueries = collect(DB::getQueryLog())
            ->pluck('query')
            ->filter(fn (string $query) => str_contains($query, 'submissions'));

        $this->assertCount(1, $submissionQueries);
    }
}
