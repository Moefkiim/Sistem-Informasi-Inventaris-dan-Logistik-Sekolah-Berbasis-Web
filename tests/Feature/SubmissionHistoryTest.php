<?php

namespace Tests\Feature;

use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubmissionHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function kajur(): User
    {
        return User::factory()->kajur()->create();
    }

    private function sarpras(): User
    {
        return User::factory()->sarpras()->create();
    }

    private function kepalaSekolah(): User
    {
        return User::factory()->kepalaSekolah()->create();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Pengadaan PC Lab',
            'purpose' => 'Menunjang praktik siswa',
            'action' => 'submit',
            'items' => [
                [
                    'item_name' => 'PC Core i5',
                    'quantity' => 4,
                    'unit' => 'Unit',
                    'estimated_price' => 7500000,
                    'specification' => 'RAM 16GB',
                ],
            ],
        ], $overrides);
    }

    private function submittedByKajur(): Submission
    {
        $kajur = $this->kajur();

        $this->actingAs($kajur)->post(route('kajur.submissions.store'), $this->payload());

        return Submission::firstOrFail();
    }

    public function test_store_records_history_null_to_submitted(): void
    {
        $kajur = $this->kajur();

        $this->actingAs($kajur)->post(route('kajur.submissions.store'), $this->payload());

        $history = Submission::firstOrFail()->histories()->latest('recorded_at')->firstOrFail();
        $this->assertNull($history->from_status);
        $this->assertSame('submitted', $history->to_status);
        $this->assertSame($kajur->id, $history->actor_user_id);
    }

    public function test_store_draft_records_history_null_to_draft(): void
    {
        $kajur = $this->kajur();

        $this->actingAs($kajur)->post(route('kajur.submissions.store'), $this->payload(['action' => 'draft']));

        $history = Submission::firstOrFail()->histories()->latest('recorded_at')->firstOrFail();
        $this->assertNull($history->from_status);
        $this->assertSame('draft', $history->to_status);
    }

    public function test_submit_draft_records_history_draft_to_submitted(): void
    {
        $kajur = $this->kajur();
        $submission = Submission::factory()->create([
            'submission_number' => 'REQ-TEST-0001',
            'user_id' => $kajur->id,
            'department' => $kajur->department,
            'title' => 'Draft pengajuan',
            'purpose' => null,
            'status' => 'draft',
        ]);

        $this->actingAs($kajur)->post(route('kajur.submissions.submitDraft', $submission));

        $history = $submission->histories()->latest('recorded_at')->firstOrFail();
        $this->assertSame('draft', $history->from_status);
        $this->assertSame('submitted', $history->to_status);
    }

    public function test_cancel_records_history_draft_to_cancelled(): void
    {
        $kajur = $this->kajur();
        $submission = Submission::factory()->create([
            'submission_number' => 'REQ-TEST-0002',
            'user_id' => $kajur->id,
            'department' => $kajur->department,
            'title' => 'Draft pengajuan',
            'purpose' => null,
            'status' => 'draft',
        ]);

        $this->actingAs($kajur)->post(route('kajur.submissions.cancel', $submission));

        $history = $submission->histories()->latest('recorded_at')->firstOrFail();
        $this->assertSame('draft', $history->from_status);
        $this->assertSame('cancelled', $history->to_status);
    }

    public function test_sarpras_review_records_history_with_notes_and_actor(): void
    {
        $sarpras = $this->sarpras();
        $submission = $this->submittedByKajur();

        $this->actingAs($sarpras)->post(route('sarpras.submissions.process', $submission), [
            'sarpras_notes' => 'Kebutuhan layak dan anggaran tersedia.',
            'action' => 'review',
        ]);

        $history = $submission->histories()->latest('recorded_at')->firstOrFail();
        $this->assertSame('submitted', $history->from_status);
        $this->assertSame('reviewed_sarpras', $history->to_status);
        $this->assertSame('Kebutuhan layak dan anggaran tersedia.', $history->notes);
        $this->assertSame($sarpras->id, $history->actor_user_id);
    }

    public function test_sarpras_reject_records_history_submitted_to_rejected(): void
    {
        $sarpras = $this->sarpras();
        $submission = $this->submittedByKajur();

        $this->actingAs($sarpras)->post(route('sarpras.submissions.process', $submission), [
            'sarpras_notes' => 'Sebaiknya dianggarkan tahun depan.',
            'action' => 'reject',
        ]);

        $history = $submission->histories()->latest('recorded_at')->firstOrFail();
        $this->assertSame('submitted', $history->from_status);
        $this->assertSame('rejected', $history->to_status);
    }

    public function test_principal_approve_records_history_with_notes_and_actor(): void
    {
        $principal = $this->kepalaSekolah();
        $submission = $this->submittedByKajur();

        $this->actingAs($this->sarpras())->post(route('sarpras.submissions.process', $submission), [
            'sarpras_notes' => 'OK',
            'action' => 'review',
        ]);

        $this->actingAs($principal)->post(route('kepala_sekolah.approval.decide', $submission), [
            'action' => 'approve',
            'principal_notes' => 'Disetujui, lanjutkan pengadaan.',
        ]);

        $history = $submission->histories()->latest('recorded_at')->firstOrFail();
        $this->assertSame('reviewed_sarpras', $history->from_status);
        $this->assertSame('approved', $history->to_status);
        $this->assertSame('Disetujui, lanjutkan pengadaan.', $history->notes);
        $this->assertSame($principal->id, $history->actor_user_id);
    }

    public function test_principal_reject_records_history_reviewed_to_rejected(): void
    {
        $principal = $this->kepalaSekolah();
        $submission = $this->submittedByKajur();

        $this->actingAs($this->sarpras())->post(route('sarpras.submissions.process', $submission), [
            'sarpras_notes' => 'OK',
            'action' => 'review',
        ]);

        $this->actingAs($principal)->post(route('kepala_sekolah.approval.decide', $submission), [
            'action' => 'reject',
        ]);

        $history = $submission->histories()->latest('recorded_at')->firstOrFail();
        $this->assertSame('reviewed_sarpras', $history->from_status);
        $this->assertSame('rejected', $history->to_status);
    }

    public function test_full_workflow_leaves_three_histories_in_order(): void
    {
        $sarpras = $this->sarpras();
        $principal = $this->kepalaSekolah();
        $submission = $this->submittedByKajur();

        $this->actingAs($sarpras)->post(route('sarpras.submissions.process', $submission), [
            'sarpras_notes' => 'Layak',
            'action' => 'review',
        ]);

        $this->actingAs($principal)->post(route('kepala_sekolah.approval.decide', $submission), [
            'action' => 'approve',
        ]);

        $statuses = $submission->histories()->orderBy('id')->pluck('to_status')->all();
        $this->assertSame(['submitted', 'reviewed_sarpras', 'approved'], $statuses);
    }

    public function test_sarpras_show_page_displays_history_trail(): void
    {
        $sarpras = $this->sarpras();
        $submission = $this->submittedByKajur();

        $this->actingAs($sarpras)->post(route('sarpras.submissions.process', $submission), [
            'sarpras_notes' => 'Rekomendasi disetujui.',
            'action' => 'review',
        ]);

        $this->actingAs($sarpras)
            ->get(route('sarpras.submissions.show', $submission))
            ->assertOk()
            ->assertSee('Riwayat Proses Pengajuan')
            ->assertSee('Diajukan')
            ->assertSee('Diverifikasi Sarpras')
            ->assertSee('Rekomendasi disetujui.');
    }
}
