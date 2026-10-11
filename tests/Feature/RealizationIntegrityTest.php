<?php

namespace Tests\Feature;

use App\Models\IncomingItem;
use App\Models\Item;
use App\Models\Submission;
use App\Models\SubmissionItem;
use App\Models\User;
use App\Services\SubmissionRealizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RealizationIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function approvedSubmissionWithItems(User $kajur, array $items = []): Submission
    {
        $submission = Submission::create([
            'submission_number' => 'REQ-REL-'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT),
            'user_id' => $kajur->id,
            'department' => $kajur->department,
            'title' => 'Pengadaan untuk Integritas',
            'purpose' => 'Kebutuhan praktik siswa',
        ]);
        $submission->status = 'approved';
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

    private function payloadFor(Submission $submission, array $overrides = []): array
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
                'new_location_id' => null,
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

    public function test_kategori_null_pada_barang_baru_dinormalisasi_menjadi_umum(): void
    {
        $sarpras = User::factory()->sarpras()->create();
        $kajur = User::factory()->kajur()->create();
        $submission = $this->approvedSubmissionWithItems($kajur, [['Proyektor', 1, 'Unit']]);
        $payload = $this->payloadFor($submission);
        $payload['lines'][0]['new_category'] = null;

        $this->actingAs($sarpras)
            ->post(route('sarpras.submissions.realize', $submission), $payload)
            ->assertRedirect()
            ->assertSessionHas('success');

        $created = Item::orderByDesc('id')->first();
        $this->assertSame('Umum', $created->category);
    }

    public function test_kategori_string_kosong_pada_barang_baru_dinormalisasi_menjadi_umum(): void
    {
        $sarpras = User::factory()->sarpras()->create();
        $kajur = User::factory()->kajur()->create();
        $submission = $this->approvedSubmissionWithItems($kajur, [['Proyektor', 1, 'Unit']]);
        $payload = $this->payloadFor($submission);
        $payload['lines'][0]['new_category'] = '';

        $this->actingAs($sarpras)
            ->post(route('sarpras.submissions.realize', $submission), $payload)
            ->assertRedirect()
            ->assertSessionHas('success');

        $created = Item::orderByDesc('id')->first();
        $this->assertSame('Umum', $created->category);
    }

    public function test_nama_barang_baru_kosong_ditolak(): void
    {
        $sarpras = User::factory()->sarpras()->create();
        $kajur = User::factory()->kajur()->create();
        $submission = $this->approvedSubmissionWithItems($kajur, [['Proyektor', 1, 'Unit']]);
        $payload = $this->payloadFor($submission);
        $payload['lines'][0]['new_name'] = null;

        $this->actingAs($sarpras)
            ->post(route('sarpras.submissions.realize', $submission), $payload)
            ->assertSessionHasErrors('lines.0.new_name');

        $this->assertSame('approved', $submission->fresh()->status);
        $this->assertSame(0, Item::count());
    }

    public function test_submission_item_id_ganda_ditolak(): void
    {
        $sarpras = User::factory()->sarpras()->create();
        $kajur = User::factory()->kajur()->create();
        $submission = $this->approvedSubmissionWithItems($kajur);
        $payload = $this->payloadFor($submission);
        $site = $submission->items->first();
        $payload['lines'][1] = array_merge($payload['lines'][0], [
            'received_quantity' => $site->quantity - 1,
            'new_name' => 'Salinan '.$site->item_name,
            'new_category' => null,
        ]);

        $this->actingAs($sarpras)
            ->post(route('sarpras.submissions.realize', $submission), $payload)
            ->assertSessionHasErrors('lines.1.submission_item_id');

        $this->assertSame('approved', $submission->fresh()->status);
        $this->assertSame(0, IncomingItem::count());
        $this->assertNull($site->fresh()->item_id);
    }

    public function test_realisasi_sebagian_baris_pengajuan_ditolak(): void
    {
        $sarpras = User::factory()->sarpras()->create();
        $kajur = User::factory()->kajur()->create();
        $submission = $this->approvedSubmissionWithItems($kajur);
        $payload = $this->payloadFor($submission);
        $payload['lines'] = [$payload['lines'][0]];

        $this->actingAs($sarpras)
            ->post(route('sarpras.submissions.realize', $submission), $payload)
            ->assertSessionHasErrors('lines');

        $this->assertSame('approved', $submission->fresh()->status);
        $this->assertSame(0, IncomingItem::count());
    }

    public function test_entry_date_tahun_1900_ditolak(): void
    {
        $sarpras = User::factory()->sarpras()->create();
        $kajur = User::factory()->kajur()->create();
        $submission = $this->approvedSubmissionWithItems($kajur);
        $payload = $this->payloadFor($submission, ['entry_date' => '1900-01-01']);

        $this->actingAs($sarpras)
            ->post(route('sarpras.submissions.realize', $submission), $payload)
            ->assertSessionHasErrors('entry_date');
    }

    public function test_entry_date_masa_mendatang_ditolak(): void
    {
        $sarpras = User::factory()->sarpras()->create();
        $kajur = User::factory()->kajur()->create();
        $submission = $this->approvedSubmissionWithItems($kajur);
        $payload = $this->payloadFor($submission, ['entry_date' => '2999-01-01']);

        $this->actingAs($sarpras)
            ->post(route('sarpras.submissions.realize', $submission), $payload)
            ->assertSessionHasErrors('entry_date');
    }

    public function test_item_inventaris_yang_tidak_ada_ditolak(): void
    {
        $sarpras = User::factory()->sarpras()->create();
        $kajur = User::factory()->kajur()->create();
        $submission = $this->approvedSubmissionWithItems($kajur);
        $payload = $this->payloadFor($submission);
        $payload['lines'][0] = [
            'submission_item_id' => $submission->items->last()->id,
            'received_quantity' => 1,
            'mode' => 'existing',
            'item_id' => 999999,
            'new_name' => null,
            'new_category' => null,
            'new_item_type' => null,
            'new_unit' => null,
            'new_location_id' => null,
            'new_condition' => null,
        ];
        $payload['lines'][1]['submission_item_id'] = $submission->items->first()->id;

        $this->actingAs($sarpras)
            ->post(route('sarpras.submissions.realize', $submission), $payload)
            ->assertSessionHasErrors('lines.0.item_id');

        $this->assertSame('approved', $submission->fresh()->status);
    }

    public function test_kode_barang_baru_tidak_bentrok_dengan_kode_sudah_ada(): void
    {
        $sarpras = User::factory()->sarpras()->create();
        $kajur = User::factory()->kajur()->create();
        $submission = $this->approvedSubmissionWithItems($kajur, [['Proyektor', 1, 'Unit']]);

        Item::create([
            'code' => 'BRG-'.$kajur->department.'-0001',
            'name' => 'Barang Warisan Lama',
            'category' => 'Umum',
            'item_type' => 'individual',
            'unit' => 'Unit',
            'source' => 'bantuan',
            'department' => $kajur->department,
            'current_condition' => 'baik',
            'current_status' => 'aktif',
            'stock' => 1,
        ]);
        Item::create([
            'code' => 'BRG-'.$kajur->department.'-10000',
            'name' => 'Barang Dengan Nomor Panjang',
            'category' => 'Umum',
            'item_type' => 'consumable',
            'unit' => 'Pcs',
            'source' => 'bantuan',
            'department' => $kajur->department,
            'current_condition' => 'baik',
            'current_status' => 'aktif',
            'stock' => 1,
        ]);

        $payload = $this->payloadFor($submission);

        $this->actingAs($sarpras)
            ->post(route('sarpras.submissions.realize', $submission), $payload)
            ->assertRedirect()
            ->assertSessionHas('success');

        $submission->refresh();
        $created = $submission->items->first()->item;
        $this->assertNotSame('BRG-'.$kajur->department.'-0001', $created->code);
        $this->assertSame(1, Item::where('code', $created->code)->count());
    }

    public function test_layanan_menolak_himpunan_baris_yang_tidak_sama_persis(): void
    {
        $sarpras = User::factory()->sarpras()->create();
        $kajur = User::factory()->kajur()->create();
        $submission = $this->approvedSubmissionWithItems($kajur);
        $first = $submission->items->first();

        $lines = [[
            'submission_item_id' => $first->id,
            'received_quantity' => 1,
            'new' => ['name' => 'Hanya Satu Baris', 'category' => 'Umum', 'item_type' => 'individual', 'unit' => 'Unit', 'location_id' => null, 'condition' => 'baik'],
        ]];

        $this->expectException(ValidationException::class);

        app(SubmissionRealizationService::class)->realize($submission, $lines, [
            'source' => 'pembelian',
            'entry_date' => '2026-10-05',
            'realization_notes' => null,
        ], $sarpras);
    }

    public function test_layanan_menolak_nama_baru_kosong(): void
    {
        $sarpras = User::factory()->sarpras()->create();
        $kajur = User::factory()->kajur()->create();
        $submission = $this->approvedSubmissionWithItems($kajur, [['Proyektor', 1, 'Unit']]);
        $first = $submission->items->first();

        $lines = [[
            'submission_item_id' => $first->id,
            'received_quantity' => 1,
            'new' => ['name' => '', 'category' => '', 'item_type' => 'individual', 'unit' => 'Unit', 'location_id' => null, 'condition' => 'baik'],
        ]];

        $this->expectException(ValidationException::class);

        app(SubmissionRealizationService::class)->realize($submission, $lines, [
            'source' => 'pembelian',
            'entry_date' => '2026-10-05',
            'realization_notes' => null,
        ], $sarpras);
    }
}
