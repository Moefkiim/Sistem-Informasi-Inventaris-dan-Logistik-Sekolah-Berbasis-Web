<?php

namespace Tests\Feature;

use App\Models\IncomingItem;
use App\Models\Location;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EndToEndWorkflowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Alur lengkap (happy path): Kajur membuat & mengajukan → Sarpras meninjau →
     * Kepala Sekolah menyetujui → Sarpras merealisasikan → Kajur melihat status
     * Terealisasi dan notifikasi. Berjalan di SQLite dan MySQL.
     */
    public function test_alur_lengkap_kajur_sarpras_kepsek_hingga_terealisasi(): void
    {
        $kajur = User::factory()->kajur()->create();
        $sarpras = User::factory()->sarpras()->create();
        $kepsek = User::factory()->kepalaSekolah()->create();
        $location = Location::factory()->create();

        // 1. Kajur membuat pengajuan dan langsung mengajukan ke Sarpras.
        $this->actingAs($kajur)
            ->post(route('kajur.submissions.store'), [
                'title' => 'Pengadaan Komputer Praktik',
                'purpose' => 'Menunjang pembelajaran kejuruan',
                'action' => 'submit',
                'items' => [
                    ['item_name' => 'Komputer Praktik', 'quantity' => 2, 'unit' => 'Unit', 'estimated_price' => 5000000, 'specification' => 'Core i5'],
                    ['item_name' => 'Tinta Printer', 'quantity' => 4, 'unit' => 'Pcs', 'estimated_price' => 150000, 'specification' => 'Original'],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $submission = Submission::where('title', 'Pengadaan Komputer Praktik')->firstOrFail();
        $this->assertSame('submitted', $submission->status);

        // Notifikasi "diajukan" masuk ke Sarpras.
        $this->assertContains(
            'submitted',
            $sarpras->notifications()->get()->pluck('data.kind')->all()
        );

        // 2. Sarpras meninjau dan memvalidasi item.
        $this->actingAs($sarpras)
            ->post(route('sarpras.submissions.process', $submission), [
                'action' => 'review',
                'sarpras_notes' => 'Item tersedia, spesifikasi valid.',
            ])
            ->assertRedirect();

        $submission->refresh();
        $this->assertSame('reviewed_sarpras', $submission->status);
        $this->assertNotNull($submission->reviewed_at);

        // Notifikasi "ditinjau" masuk ke Kepala Sekolah.
        $this->assertContains(
            'reviewed',
            $kepsek->notifications()->get()->pluck('data.kind')->all()
        );

        // 3. Kepala Sekolah menyetujui.
        $this->actingAs($kepsek)
            ->post(route('kepala_sekolah.approval.decide', $submission), [
                'action' => 'approve',
                'principal_notes' => 'Disetujui untuk penguatan praktik.',
            ])
            ->assertRedirect();

        $submission->refresh();
        $this->assertSame('approved', $submission->status);
        $this->assertNotNull($submission->decided_at);

        // 4. Sarpras merealisasikan: stok/unit dan barang masuk tertaut pengajuan.
        $payload = $this->realizationPayload($submission, $location);
        $this->actingAs($sarpras)
            ->post(route('sarpras.submissions.realize', $submission), $payload)
            ->assertRedirect()
            ->assertSessionHas('success');

        $submission->refresh()->load('items.item');
        $this->assertSame('realized', $submission->status);

        $incoming = IncomingItem::where('submission_id', $submission->id)->get();
        $this->assertCount(2, $incoming);

        $laptop = $submission->items->first()->item;
        $this->assertTrue($laptop->isIndividual());
        $this->assertSame(2, $laptop->stock);
        $this->assertSame(2, $laptop->assetUnits()->count());

        $toner = $submission->items->last()->item;
        $this->assertTrue($toner->isConsumable());
        $this->assertSame(4, $toner->stock);

        // 5. Kajur melihat status Terealisasi dan notifikasi.
        $this->actingAs($kajur)
            ->get(route('kajur.submissions.show', $submission))
            ->assertOk()
            ->assertSee('Terealisasi');

        $this->assertContains(
            'realized',
            $kajur->notifications()->get()->pluck('data.kind')->all()
        );
    }

    /**
     * Jalur penolakan: Kepala Sekolah menolak dengan alasan → Kajur melihat alasan
     * dan notifikasi → "Ajukan Ulang" menghasilkan draf revisi.
     */
    public function test_alur_penolakan_beralasan_dan_pengajuan_ulang(): void
    {
        $kajur = User::factory()->kajur()->create();
        $sarpras = User::factory()->sarpras()->create();
        $kepsek = User::factory()->kepalaSekolah()->create();

        $this->actingAs($kajur)
            ->post(route('kajur.submissions.store'), [
                'title' => 'Pengajuan Biaya Praktik',
                'action' => 'submit',
                'items' => [
                    ['item_name' => 'Modul Praktik', 'quantity' => 10, 'unit' => 'Buah', 'estimated_price' => 100000],
                ],
            ])
            ->assertRedirect();

        $submission = Submission::where('title', 'Pengajuan Biaya Praktik')->firstOrFail();

        $this->actingAs($sarpras)
            ->post(route('sarpras.submissions.process', $submission), [
                'action' => 'review',
                'sarpras_notes' => 'Sudah diperiksa.',
            ])
            ->assertRedirect();

        $reason = 'Nominal melebihi pagu anggaran tahun ini.';

        $this->actingAs($kepsek)
            ->post(route('kepala_sekolah.approval.decide', $submission), [
                'action' => 'reject',
                'principal_notes' => $reason,
            ])
            ->assertRedirect();

        $submission->refresh();
        $this->assertSame('rejected', $submission->status);
        $this->assertSame($reason, $submission->principal_notes);

        // Kajur melihat alasan penolakan dan notifikasi penolakan.
        $this->actingAs($kajur)
            ->get(route('kajur.submissions.show', $submission))
            ->assertOk()
            ->assertSee($reason);

        $this->assertContains(
            'rejected',
            $kajur->notifications()->get()->pluck('data.kind')->all()
        );

        // Kajur mengajukan ulang → draf revisi (pengajuan baru bertaut revision_of_id).
        $this->actingAs($kajur)
            ->post(route('kajur.submissions.revise', $submission))
            ->assertRedirect();

        $draft = Submission::where('revision_of_id', $submission->id)->firstOrFail();
        $this->assertSame('draft', $draft->status);
        $this->assertSame($submission->title, $draft->title);
        $this->assertSame($submission->department, $draft->department);
        $this->assertSame(1, $draft->items()->count());
    }

    /**
     * @return array<string, mixed>
     */
    private function realizationPayload(Submission $submission, Location $location): array
    {
        $lines = [];

        foreach ($submission->items as $index => $submissionItem) {
            $lines[] = [
                'submission_item_id' => $submissionItem->id,
                'received_quantity' => $submissionItem->quantity,
                'mode' => 'new',
                'item_id' => '',
                'new_name' => $submissionItem->item_name,
                'new_category' => 'Elektronik Praktik',
                'new_item_type' => $index === 0 ? 'individual' : 'consumable',
                'new_unit' => $submissionItem->unit,
                'new_location_id' => $location->id,
                'new_condition' => 'baik',
            ];
        }

        return [
            'source' => 'pembelian',
            'entry_date' => '2026-10-05',
            'realization_notes' => 'Barang telah diterima di gudang.',
            'lines' => $lines,
        ];
    }
}
