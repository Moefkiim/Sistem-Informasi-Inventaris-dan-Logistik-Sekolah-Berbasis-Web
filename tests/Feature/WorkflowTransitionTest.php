<?php

namespace Tests\Feature;

use App\Models\Submission;
use App\Models\SubmissionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowTransitionTest extends TestCase
{
    use RefreshDatabase;

    protected function createDraftSubmission(): array
    {
        $kajur = User::factory()->kajur()->create();
        $submission = Submission::create([
            'submission_number' => 'REQ-TEST-001',
            'user_id' => $kajur->id,
            'title' => 'Test',
            'purpose' => 'Test',
            'department' => $kajur->department,
        ]);
        SubmissionItem::create([
            'submission_id' => $submission->id,
            'item_name' => 'PC',
            'quantity' => 1,
            'unit' => 'Unit',
            'estimated_price' => 1000000,
        ]);

        $this->assertSame('draft', $submission->status);

        return [$kajur, $submission];
    }

    protected function advanceToSubmitted(Submission $submission, User $kajur): void
    {
        $this->actingAs($kajur)
            ->post(route('kajur.submissions.submitDraft', $submission))
            ->assertRedirect();

        $submission->refresh();
        $this->assertSame('submitted', $submission->status);
    }

    protected function advanceToReviewed(Submission $submission): User
    {
        $sarpras = User::factory()->sarpras()->create();

        $this->actingAs($sarpras)
            ->post(route('sarpras.submissions.process', $submission), [
                'action' => 'review',
                'sarpras_notes' => 'Sudah diverifikasi',
            ])
            ->assertRedirect();

        $submission->refresh();
        $this->assertSame('reviewed_sarpras', $submission->status);

        return $sarpras;
    }

    public function test_submission_follows_realistic_transition_chain(): void
    {
        [$kajur, $submission] = $this->createDraftSubmission();

        $this->advanceToSubmitted($submission, $kajur);
        $this->advanceToReviewed($submission);

        $kepsek = User::factory()->state(['role' => 'kepala_sekolah', 'department' => null])->create();

        $this->actingAs($kepsek)
            ->post(route('kepala_sekolah.approval.decide', $submission), [
                'action' => 'approve',
                'principal_notes' => 'Disetujui',
            ])
            ->assertRedirect();

        $submission->refresh();
        $this->assertSame('approved', $submission->status);
        $this->assertNotNull($submission->reviewed_at);
        $this->assertNotNull($submission->decided_at);
    }

    public function test_submission_cannot_be_approved_twice(): void
    {
        [$kajur, $submission] = $this->createDraftSubmission();

        $this->advanceToSubmitted($submission, $kajur);
        $this->advanceToReviewed($submission);

        $kepsek = User::factory()->state(['role' => 'kepala_sekolah', 'department' => null])->create();

        $this->actingAs($kepsek)
            ->post(route('kepala_sekolah.approval.decide', $submission), [
                'action' => 'approve',
                'principal_notes' => 'OK',
            ])
            ->assertRedirect();

        $submission->refresh();
        $this->assertEquals('approved', $submission->status);

        $this->actingAs($kepsek)
            ->post(route('kepala_sekolah.approval.decide', $submission), [
                'action' => 'approve',
                'principal_notes' => 'Ulang',
            ])
            ->assertSessionHasErrors();

        $submission->refresh();
        $this->assertSame('approved', $submission->status);
    }

    public function test_submission_cannot_skip_sarpras_review(): void
    {
        [$kajur, $submission] = $this->createDraftSubmission();

        $this->advanceToSubmitted($submission, $kajur);

        $kepsek = User::factory()->state(['role' => 'kepala_sekolah', 'department' => null])->create();

        $this->actingAs($kepsek)
            ->post(route('kepala_sekolah.approval.decide', $submission), [
                'action' => 'approve',
                'principal_notes' => 'Langsung approve',
            ])
            ->assertSessionHasErrors();

        $submission->refresh();
        $this->assertSame('submitted', $submission->status);
    }

    public function test_sensitive_fields_are_not_mass_assignable(): void
    {
        $kajur = User::factory()->kajur()->create();

        $submission = Submission::create([
            'submission_number' => 'REQ-TEST-002',
            'user_id' => $kajur->id,
            'title' => 'Test',
            'purpose' => 'Test',
            'department' => $kajur->department,
            'status' => 'approved',
            'principal_notes' => 'Langsung diisi',
        ]);

        $submission->refresh();
        $this->assertSame('draft', $submission->status);
        $this->assertNull($submission->principal_notes);

        $user = User::factory()->create(['role' => 'kajur']);
        $user->update(['role' => 'kepala_sekolah']);
        $this->assertSame('kajur', $user->fresh()->role);
    }
}