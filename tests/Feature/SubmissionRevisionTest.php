<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Submission;
use App\Models\SubmissionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubmissionRevisionTest extends TestCase
{
    use RefreshDatabase;

    private function makeSubmission(User $kajur, array $items = [['Meja', 2, 'Unit']]): Submission
    {
        $submission = Submission::create([
            'submission_number' => 'REQ-REV-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
            'user_id' => $kajur->id,
            'title' => 'Pengadaan Alat Praktik',
            'purpose' => 'Praktikum siswa',
            'department' => $kajur->department,
        ]);

        foreach ($items as [$name, $qty, $unit]) {
            SubmissionItem::create([
                'submission_id' => $submission->id,
                'item_name' => $name,
                'quantity' => $qty,
                'unit' => $unit,
                'estimated_price' => 500000,
                'specification' => 'Standar',
            ]);
        }

        return $submission;
    }

    private function rejectedByPrincipal(User $kajur, string $reason = 'Anggaran belum tersedia tahun ini.'): Submission
    {
        $submission = $this->makeSubmission($kajur);
        $submission->status = 'reviewed_sarpras';
        $submission->save();

        $principal = User::factory()->kepalaSekolah()->create();

        $this->actingAs($principal)
            ->post(route('kepala_sekolah.approval.decide', $submission), [
                'action' => 'reject',
                'principal_notes' => $reason,
            ])
            ->assertRedirect();

        return $submission->refresh();
    }

    public function test_kepala_sekolah_tidak_bisa_menolak_tanpa_alasan(): void
    {
        $kajur = User::factory()->kajur()->create();
        $submission = $this->makeSubmission($kajur);
        $submission->status = 'reviewed_sarpras';
        $submission->save();

        $principal = User::factory()->kepalaSekolah()->create();

        $this->actingAs($principal)
            ->post(route('kepala_sekolah.approval.decide', $submission), ['action' => 'reject'])
            ->assertSessionHasErrors('principal_notes');

        $this->assertSame('reviewed_sarpras', $submission->fresh()->status);
    }

    public function test_alasan_penolakan_kepala_sekolah_minimal_10_karakter(): void
    {
        $kajur = User::factory()->kajur()->create();
        $submission = $this->makeSubmission($kajur);
        $submission->status = 'reviewed_sarpras';
        $submission->save();

        $principal = User::factory()->kepalaSekolah()->create();

        $this->actingAs($principal)
            ->post(route('kepala_sekolah.approval.decide', $submission), [
                'action' => 'reject',
                'principal_notes' => 'Tidak',
            ])
            ->assertSessionHasErrors('principal_notes');

        $this->assertSame('reviewed_sarpras', $submission->fresh()->status);
    }

    public function test_menyetujui_tanpa_catatan_tetap_diperbolehkan(): void
    {
        $kajur = User::factory()->kajur()->create();
        $submission = $this->makeSubmission($kajur);
        $submission->status = 'reviewed_sarpras';
        $submission->save();

        $principal = User::factory()->kepalaSekolah()->create();

        $this->actingAs($principal)
            ->post(route('kepala_sekolah.approval.decide', $submission), ['action' => 'approve'])
            ->assertSessionHasNoErrors();

        $this->assertSame('approved', $submission->fresh()->status);
    }

    public function test_sarpras_tidak_bisa_menolak_tanpa_alasan(): void
    {
        $kajur = User::factory()->kajur()->create();
        $submission = $this->makeSubmission($kajur);
        $submission->status = 'submitted';
        $submission->save();

        $sarpras = User::factory()->sarpras()->create();

        $this->actingAs($sarpras)
            ->post(route('sarpras.submissions.process', $submission), ['action' => 'reject'])
            ->assertSessionHasErrors('sarpras_notes');

        $this->assertSame('submitted', $submission->fresh()->status);
    }

    public function test_ajukan_ulang_membuat_draf_salinan_dan_pengajuan_lama_tetap_ditolak(): void
    {
        $kajur = User::factory()->kajur()->create();
        $rejected = $this->rejectedByPrincipal($kajur);

        $this->actingAs($kajur)
            ->post(route('kajur.submissions.revise', $rejected))
            ->assertRedirect();

        $this->assertSame('rejected', $rejected->fresh()->status);
        $this->assertCount(1, $rejected->fresh()->items);

        $draft = Submission::where('revision_of_id', $rejected->id)->firstOrFail();
        $this->assertSame('draft', $draft->status);
        $this->assertSame($kajur->id, $draft->user_id);
        $this->assertCount(1, $draft->items);
        $this->assertSame('Meja', $draft->items->first()->item_name);
        $this->assertSame(2, $draft->items->first()->quantity);
        $this->assertTrue(ActivityLog::where('action', 'submission_revised')->exists());
    }

    public function test_ajukan_ulang_tidak_menggandakan_draf_yang_sudah_ada(): void
    {
        $kajur = User::factory()->kajur()->create();
        $rejected = $this->rejectedByPrincipal($kajur);

        $this->actingAs($kajur)->post(route('kajur.submissions.revise', $rejected));
        $first = Submission::where('revision_of_id', $rejected->id)->firstOrFail();

        $this->actingAs($kajur)
            ->post(route('kajur.submissions.revise', $rejected))
            ->assertRedirect(route('kajur.submissions.edit', $first));

        $this->assertSame(1, Submission::where('revision_of_id', $rejected->id)->count());
    }

    public function test_kajur_lain_tidak_dapat_mengajukan_ulang(): void
    {
        $kajur = User::factory()->kajur()->create();
        $lain = User::factory()->kajur()->create(['department' => 'TKJ']);
        $rejected = $this->rejectedByPrincipal($kajur);

        $this->actingAs($lain)
            ->post(route('kajur.submissions.revise', $rejected))
            ->assertForbidden();

        $this->assertSame(0, Submission::where('revision_of_id', $rejected->id)->count());
    }

    public function test_hanya_pengajuan_ditolak_yang_dapat_diajukan_ulang(): void
    {
        $kajur = User::factory()->kajur()->create();
        $draft = $this->makeSubmission($kajur);

        $this->actingAs($kajur)
            ->from(route('kajur.submissions.show', $draft))
            ->post(route('kajur.submissions.revise', $draft))
            ->assertSessionHasErrors();

        $this->assertSame(0, Submission::where('revision_of_id', $draft->id)->count());
    }

    public function test_draf_revisi_menampilkan_alasan_penolakan_sebelumnya(): void
    {
        $kajur = User::factory()->kajur()->create();
        $rejected = $this->rejectedByPrincipal($kajur, 'Stok gudang masih mencukupi untuk tahun ini.');

        $this->actingAs($kajur)->post(route('kajur.submissions.revise', $rejected));
        $draft = Submission::where('revision_of_id', $rejected->id)->firstOrFail();

        $this->actingAs($kajur)
            ->get(route('kajur.submissions.show', $draft))
            ->assertOk()
            ->assertSee('Draf Revisi')
            ->assertSee($rejected->submission_number)
            ->assertSee('Stok gudang masih mencukupi untuk tahun ini.');
    }
}
