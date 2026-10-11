<?php

namespace Tests\Feature;

use App\Models\IncomingItem;
use App\Models\Item;
use App\Models\Location;
use App\Models\Submission;
use App\Models\SubmissionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubmissionRealizationTest extends TestCase
{
    use RefreshDatabase;

    private function approvedSubmissionWithItems(User $kajur, array $items = []): Submission
    {
        $submission = Submission::create([
            'submission_number' => 'REQ-REL-'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT),
            'user_id' => $kajur->id,
            'department' => $kajur->department,
            'title' => 'Pengadaan untuk Realisasi',
            'purpose' => 'Kebutuhan praktik siswa',
        ]);
        $submission->status = 'approved'; // status dikecualikan dari $fillable
        $submission->save();

        $default = [['Laptop Praktik', 2, 'Unit'], ['Toner Printer', 3, 'Pcs']];
        foreach (($items ?: $default) as [$name, $qty, $unit]) {
            SubmissionItem::create([
                'submission_id' => $submission->id,
                'item_name' => $name,
                'quantity' => $qty,
                'unit' => $unit,
                'estimated_price' => 750000,
                'specification' => 'Standar',
            ]);
        }

        return $submission;
    }

    private function payloadFor(Submission $submission, array $itemIds, array $overrides = []): array
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
                'new_location_id' => $itemIds['location'] ?? null,
                'new_condition' => 'baik',
            ];
        }

        return array_merge([
            'source' => 'pembelian',
            'entry_date' => '2026-10-05',
            'realization_notes' => 'Barang telah diterima di gudang.',
            'lines' => $lines,
        ], $overrides);
    }

    public function test_sarpras_merealisasikan_barang_baru_individual_dan_habis_pakai(): void
    {
        $sarpras = User::factory()->sarpras()->create();
        $kajur = User::factory()->kajur()->create();
        $location = Location::factory()->create();
        $submission = $this->approvedSubmissionWithItems($kajur);

        $this->actingAs($sarpras)
            ->post(route('sarpras.submissions.realize', $submission), $this->payloadFor($submission, ['location' => $location->id]))
            ->assertRedirect(route('sarpras.submissions.show', $submission))
            ->assertSessionHas('success');

        $submission->refresh();

        $this->assertSame('realized', $submission->status);
        $this->assertSame($sarpras->id, $submission->realized_by);
        $this->assertNotNull($submission->realized_at);
        $this->assertSame('Barang telah diterima di gudang.', $submission->realization_notes);

        $incoming = IncomingItem::where('submission_id', $submission->id)->get();
        $this->assertCount(2, $incoming);
        foreach ($incoming as $transaction) {
            $this->assertSame($submission->id, $transaction->submission_id);
        }

        $laptop = $submission->items->first()->item;
        $this->assertTrue($laptop->isIndividual());
        $this->assertSame(2, $laptop->stock);
        $this->assertSame(2, $laptop->assetUnits()->count());

        $toner = $submission->items->last()->item;
        $this->assertTrue($toner->isConsumable());
        $this->assertSame(3, $toner->stock);
        $this->assertSame(0, $toner->assetUnits()->count());

        foreach ($submission->items as $submissionItem) {
            $this->assertNotNull($submissionItem->item_id);
            $this->assertSame($submissionItem->quantity, $submissionItem->received_quantity);
        }

        $this->assertDatabaseHas('submission_histories', [
            'submission_id' => $submission->id,
            'from_status' => 'approved',
            'to_status' => 'realized',
        ]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'submission_realized']);
    }

    public function test_sarpras_merealisasikan_dengan_barang_inventaris_yang_sudah_ada(): void
    {
        $sarpras = User::factory()->sarpras()->create();
        $kajur = User::factory()->kajur()->create();
        $submission = $this->approvedSubmissionWithItems($kajur, [['Laptop Lama', 1, 'Unit']]);

        $existing = Item::factory()->individual()->create(['stock' => 0]);

        $payload = $this->payloadFor($submission, []);
        $payload['lines'][0]['mode'] = 'existing';
        $payload['lines'][0]['item_id'] = $existing->id;
        $payload['lines'][0]['new_name'] = null;
        $payload['lines'][0]['new_category'] = null;
        $payload['lines'][0]['new_item_type'] = null;
        $payload['lines'][0]['new_unit'] = null;
        $payload['lines'][0]['new_location_id'] = null;
        $payload['lines'][0]['new_condition'] = null;

        $this->actingAs($sarpras)
            ->post(route('sarpras.submissions.realize', $submission), $payload)
            ->assertRedirect()
            ->assertSessionHas('success');

        $submission->refresh();
        $this->assertSame('realized', $submission->status);
        $this->assertSame($existing->id, $submission->items->first()->item_id);
        $this->assertSame(1, $existing->fresh()->stock);
        $this->assertSame(1, $existing->assetUnits()->count());
    }

    public function test_realisasi_ganda_ditolak(): void
    {
        $sarpras = User::factory()->sarpras()->create();
        $kajur = User::factory()->kajur()->create();
        $location = Location::factory()->create();
        $submission = $this->approvedSubmissionWithItems($kajur);
        $payload = $this->payloadFor($submission, ['location' => $location->id]);

        $this->actingAs($sarpras)->post(route('sarpras.submissions.realize', $submission), $payload)->assertRedirect();
        $submission->refresh();
        $this->assertSame('realized', $submission->status);

        $this->actingAs($sarpras)
            ->post(route('sarpras.submissions.realize', $submission), $payload)
            ->assertForbidden();
    }

    public function test_realisasi_pengajuan_belum_disetujui_ditolak(): void
    {
        $sarpras = User::factory()->sarpras()->create();
        $kajur = User::factory()->kajur()->create();
        $location = Location::factory()->create();

        $submission = Submission::create([
            'submission_number' => 'REQ-REL-'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT),
            'user_id' => $kajur->id,
            'department' => $kajur->department,
            'title' => 'Masih Draft',
        ]);

        $this->actingAs($sarpras)
            ->post(route('sarpras.submissions.realize', $submission), $this->payloadFor($submission, ['location' => $location->id]))
            ->assertForbidden();
    }

    public function test_kuantitas_melebihi_permintaan_ditolak(): void
    {
        $sarpras = User::factory()->sarpras()->create();
        $kajur = User::factory()->kajur()->create();
        $location = Location::factory()->create();
        $submission = $this->approvedSubmissionWithItems($kajur);

        $payload = $this->payloadFor($submission, ['location' => $location->id]);
        $payload['lines'][0]['received_quantity'] = 99;

        $this->actingAs($sarpras)
            ->post(route('sarpras.submissions.realize', $submission), $payload)
            ->assertSessionHasErrors('lines.0.received_quantity');

        $this->assertSame('approved', $submission->fresh()->status);
        $this->assertSame(0, Item::count());
    }

    public function test_baris_dari_pengajuan_lain_ditolak(): void
    {
        $sarpras = User::factory()->sarpras()->create();
        $kajur = User::factory()->kajur()->create();
        $location = Location::factory()->create();
        $submission = $this->approvedSubmissionWithItems($kajur);
        $other = $this->approvedSubmissionWithItems($kajur);

        $payload = $this->payloadFor($submission, ['location' => $location->id]);
        $payload['lines'][0]['submission_item_id'] = $other->items->first()->id;

        $this->actingAs($sarpras)
            ->post(route('sarpras.submissions.realize', $submission), $payload)
            ->assertSessionHasErrors('lines.0.submission_item_id');

        $this->assertSame('approved', $submission->fresh()->status);
    }

    public function test_role_selain_sarpras_tidak_bisa_merealisasikan(): void
    {
        $kajur = User::factory()->kajur()->create();
        $location = Location::factory()->create();
        $submission = $this->approvedSubmissionWithItems($kajur);

        $principal = User::factory()->kepalaSekolah()->create();
        $this->actingAs($principal)
            ->post(route('sarpras.submissions.realize', $submission), $this->payloadFor($submission, ['location' => $location->id]))
            ->assertForbidden();

        $this->actingAs($kajur)
            ->get(route('sarpras.submissions.realize_form', $submission))
            ->assertForbidden();
    }

    public function test_form_realisasi_hanya_terbuka_untuk_status_approved(): void
    {
        $sarpras = User::factory()->sarpras()->create();
        $kajur = User::factory()->kajur()->create();

        $submission = Submission::create([
            'submission_number' => 'REQ-REL-'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT),
            'user_id' => $kajur->id,
            'department' => $kajur->department,
            'title' => 'Sedang Diproses',
        ]);
        $submission->status = 'reviewed_sarpras';
        $submission->save();

        $this->actingAs($sarpras)
            ->get(route('sarpras.submissions.realize_form', $submission))
            ->assertForbidden();
    }

    public function test_riwayat_lokasi_dan_kondisi_dibuat_untuk_unit_baru(): void
    {
        $sarpras = User::factory()->sarpras()->create();
        $kajur = User::factory()->kajur()->create();
        $location = Location::factory()->create();
        $submission = $this->approvedSubmissionWithItems($kajur);

        $this->actingAs($sarpras)
            ->post(route('sarpras.submissions.realize', $submission), $this->payloadFor($submission, ['location' => $location->id]));

        $submission->refresh()->load('items.item');

        $laptop = $submission->items->first()->item;

        foreach ($laptop->assetUnits as $unit) {
            $this->assertSame($location->id, $unit->location_id);
            $this->assertSame('aktif', $unit->current_status);
            $this->assertSame('baik', $unit->current_condition);
            $this->assertDatabaseHas('condition_histories', ['asset_unit_id' => $unit->id]);
            $this->assertDatabaseHas('location_histories', [
                'asset_unit_id' => $unit->id,
                'to_location_id' => $location->id,
            ]);
        }
    }
}
