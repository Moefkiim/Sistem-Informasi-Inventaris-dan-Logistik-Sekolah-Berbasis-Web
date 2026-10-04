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

    protected function createSubmission(): array
    {
        $kajur = User::factory()->kajur()->create();
        $submission = Submission::create([
            'submission_number' => 'REQ-TEST-001',
            'user_id' => $kajur->id,
            'title' => 'Test',
            'purpose' => 'Test',
            'department' => $kajur->department,
        ]);
        $submission->status = 'reviewed_sarpras';
        $submission->sarpras_notes = 'OK';
        $submission->sarpras_user_id = User::factory()->sarpras()->create()->id;
        $submission->reviewed_at = now();
        $submission->save();
        SubmissionItem::create([
            'submission_id' => $submission->id,
            'item_name' => 'PC',
            'quantity' => 1,
            'unit' => 'Unit',
            'estimated_price' => 1000000,
        ]);
        return [$kajur, $submission];
    }

    public function test_submission_cannot_be_approved_twice(): void
    {
        [$kajur, $submission] = $this->createSubmission();
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
    }
}
