<?php

namespace Tests\Feature;

use App\Models\AssetUnit;
use App\Models\Document;
use App\Models\Item;
use App\Models\Loan;
use App\Models\Location;
use App\Models\Submission;
use App\Models\SubmissionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VisibleErrorMessagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_1_toggle_status_cannot_deactivate_self(): void
    {
        $sarpras = User::factory()->sarpras()->create();

        $this->actingAs($sarpras)
            ->from(route('sarpras.users.index'))
            ->followingRedirects()
            ->post(route('sarpras.users.toggle', $sarpras))
            ->assertSee('Anda tidak dapat menonaktifkan akun sendiri.');
    }

    public function test_2_toggle_status_cannot_toggle_principal_or_other_sarpras(): void
    {
        $sarpras = User::factory()->sarpras()->create();
        $principal = User::factory()->kepalaSekolah()->create();

        $this->actingAs($sarpras)
            ->from(route('sarpras.users.index'))
            ->followingRedirects()
            ->post(route('sarpras.users.toggle', $principal))
            ->assertSee('Anda tidak memiliki wewenang untuk mengubah status akun Kepala Sekolah atau Sarpras.');
    }

    public function test_3_sarpras_process_submission_not_in_ready_status(): void
    {
        $sarpras = User::factory()->sarpras()->create();
        $kajur = User::factory()->kajur()->create();
        $submission = Submission::create([
            'submission_number' => 'REQ-TEST-003',
            'user_id' => $kajur->id,
            'title' => 'Pengadaan',
            'purpose' => 'Ujian',
            'department' => $kajur->department,
        ]); // status draft

        $url = route('sarpras.submissions.show', $submission);

        $this->actingAs($sarpras)
            ->from($url)
            ->followingRedirects()
            ->post(route('sarpras.submissions.process', $submission), [
                'action' => 'review',
                'sarpras_notes' => 'Catatan',
            ])
            ->assertSee('Pengajuan ini tidak dalam status siap diproses.');
    }

    public function test_4_location_destroy_fails_when_still_has_items(): void
    {
        $sarpras = User::factory()->sarpras()->create();
        $loc = Location::create(['name' => 'Lab A', 'code' => 'LAB-A']);
        Item::create([
            'name' => 'Meja',
            'code' => 'BRG-MEJA',
            'category' => 'Mebel',
            'item_type' => 'individual',
            'unit' => 'Unit',
            'stock' => 1,
            'location_id' => $loc->id,
        ]);

        $this->actingAs($sarpras)
            ->from(route('sarpras.locations.index'))
            ->followingRedirects()
            ->delete(route('sarpras.locations.destroy', $loc))
            ->assertSee('Lokasi tidak dapat dihapus karena masih menampung barang inventaris atau unit aset.');
    }

    public function test_5_loan_approve_fails_when_not_in_approvable_status(): void
    {
        $sarpras = User::factory()->sarpras()->create();
        $item = Item::create([
            'name' => 'Proyektor',
            'code' => 'PRJ-01',
            'category' => 'Elektronik',
            'item_type' => 'individual',
            'unit' => 'Unit',
            'stock' => 1,
        ]);
        $loan = Loan::create([
            'loan_number' => 'PINJAM-001',
            'item_id' => $item->id,
            'borrower_name' => 'Budi',
            'purpose' => 'Presentasi',
            'quantity' => 1,
            'status' => 'dipinjam',
            'loan_date' => now()->toDateString(),
            'due_date' => now()->addDays(2)->toDateString(),
        ]);

        $url = route('sarpras.loans.show', $loan);

        $this->actingAs($sarpras)
            ->from($url)
            ->followingRedirects()
            ->post(route('sarpras.loans.approve', $loan))
            ->assertSee('Peminjaman tidak dalam status yang dapat disetujui.');
    }

    public function test_6_loan_return_fails_when_not_in_returnable_status(): void
    {
        $sarpras = User::factory()->sarpras()->create();
        $item = Item::create([
            'name' => 'Proyektor',
            'code' => 'PRJ-02',
            'category' => 'Elektronik',
            'item_type' => 'individual',
            'unit' => 'Unit',
            'stock' => 1,
        ]);
        $loan = Loan::create([
            'loan_number' => 'PINJAM-002',
            'item_id' => $item->id,
            'borrower_name' => 'Budi',
            'purpose' => 'Presentasi',
            'quantity' => 1,
            'status' => 'menunggu',
            'loan_date' => now()->toDateString(),
            'due_date' => now()->addDays(2)->toDateString(),
        ]);

        $url = route('sarpras.loans.show', $loan);

        $this->actingAs($sarpras)
            ->from($url)
            ->followingRedirects()
            ->post(route('sarpras.loans.return', $loan), ['condition_on_return' => 'baik'])
            ->assertSee('Peminjaman tidak dalam status yang dapat dikembalikan.');
    }

    public function test_7_loan_reject_fails_when_not_pending(): void
    {
        $sarpras = User::factory()->sarpras()->create();
        $item = Item::create([
            'name' => 'Proyektor',
            'code' => 'PRJ-03',
            'category' => 'Elektronik',
            'item_type' => 'individual',
            'unit' => 'Unit',
            'stock' => 1,
        ]);
        $loan = Loan::create([
            'loan_number' => 'PINJAM-003',
            'item_id' => $item->id,
            'borrower_name' => 'Budi',
            'purpose' => 'Presentasi',
            'quantity' => 1,
            'status' => 'dipinjam',
            'loan_date' => now()->toDateString(),
            'due_date' => now()->addDays(2)->toDateString(),
        ]);

        $url = route('sarpras.loans.show', $loan);

        $this->actingAs($sarpras)
            ->from($url)
            ->followingRedirects()
            ->post(route('sarpras.loans.reject', $loan), ['notes' => 'Alasan tolak'])
            ->assertSee('Hanya permohonan berstatus');
    }

    public function test_8_inventory_update_location_same_destination(): void
    {
        $sarpras = User::factory()->sarpras()->create();
        $loc = Location::create(['name' => 'Gudang Utama', 'code' => 'GDG-01']);
        $item = Item::create([
            'name' => 'Kabel UTP',
            'code' => 'KBL-01',
            'category' => 'Jaringan',
            'item_type' => 'consumable',
            'unit' => 'Meter',
            'stock' => 10,
            'location_id' => $loc->id,
        ]);

        $url = route('sarpras.inventory.show', $item);

        $this->actingAs($sarpras)
            ->from($url)
            ->followingRedirects()
            ->post(route('sarpras.inventory.updateLocation', $item), [
                'to_location_id' => $loc->id,
            ])
            ->assertSee('Lokasi tujuan sama dengan lokasi saat ini.');
    }

    public function test_9_inventory_update_unit_location_same_destination(): void
    {
        $sarpras = User::factory()->sarpras()->create();
        $loc = Location::create(['name' => 'Gudang 1', 'code' => 'GDG-02']);
        $item = Item::create([
            'name' => 'Server Rack',
            'code' => 'SRV-01',
            'category' => 'Server',
            'item_type' => 'individual',
            'unit' => 'Unit',
            'stock' => 1,
            'location_id' => $loc->id,
        ]);
        $unit = AssetUnit::create([
            'item_id' => $item->id,
            'unit_inventory_number' => 'SRV-01-001',
            'location_id' => $loc->id,
            'current_condition' => 'baik',
            'current_status' => 'aktif',
        ]);

        $url = route('sarpras.inventory.show', $item);

        $this->actingAs($sarpras)
            ->from($url)
            ->followingRedirects()
            ->post(route('sarpras.inventory.updateLocation', $item), [
                'asset_unit_id' => $unit->id,
                'to_location_id' => $loc->id,
            ])
            ->assertSee('Lokasi tujuan sama dengan lokasi unit saat ini.');
    }

    public function test_10_inventory_update_condition_same_condition(): void
    {
        $sarpras = User::factory()->sarpras()->create();
        $item = Item::create([
            'name' => 'Switch Hub',
            'code' => 'SW-01',
            'category' => 'Jaringan',
            'item_type' => 'consumable',
            'unit' => 'Unit',
            'stock' => 5,
            'current_condition' => 'baik',
        ]);

        $url = route('sarpras.inventory.show', $item);

        $this->actingAs($sarpras)
            ->from($url)
            ->followingRedirects()
            ->post(route('sarpras.inventory.updateCondition', $item), [
                'to_condition' => 'baik',
            ])
            ->assertSee('Status kondisi fisik sama dengan kondisi saat ini.');
    }

    public function test_11_inventory_update_unit_condition_same_condition(): void
    {
        $sarpras = User::factory()->sarpras()->create();
        $item = Item::create([
            'name' => 'Monitor LED',
            'code' => 'MON-01',
            'category' => 'Komputer',
            'item_type' => 'individual',
            'unit' => 'Unit',
            'stock' => 1,
            'current_condition' => 'baik',
        ]);
        $unit = AssetUnit::create([
            'item_id' => $item->id,
            'unit_inventory_number' => 'MON-01-001',
            'current_condition' => 'baik',
            'current_status' => 'aktif',
        ]);

        $url = route('sarpras.inventory.show', $item);

        $this->actingAs($sarpras)
            ->from($url)
            ->followingRedirects()
            ->post(route('sarpras.inventory.updateCondition', $item), [
                'asset_unit_id' => $unit->id,
                'to_condition' => 'baik',
            ])
            ->assertSee('Kondisi unit aset sama dengan kondisi saat ini.');
    }

    public function test_12_principal_decide_fails_when_not_reviewed(): void
    {
        $principal = User::factory()->kepalaSekolah()->create();
        $kajur = User::factory()->kajur()->create();
        $submission = Submission::create([
            'submission_number' => 'REQ-TEST-012',
            'user_id' => $kajur->id,
            'title' => 'Pengadaan',
            'purpose' => 'Ujian',
            'department' => $kajur->department,
        ]); // status draft

        $url = route('kepala_sekolah.approval.show', $submission);

        $this->actingAs($principal)
            ->from($url)
            ->followingRedirects()
            ->post(route('kepala_sekolah.approval.decide', $submission), [
                'action' => 'approve',
            ])
            ->assertSee('Pengajuan belum diverifikasi oleh Sarpras atau sudah diputuskan sebelumnya.');
    }

    public function test_13_kajur_edit_fails_when_not_draft(): void
    {
        $kajur = User::factory()->kajur()->create();
        $submission = Submission::create([
            'submission_number' => 'REQ-TEST-013',
            'user_id' => $kajur->id,
            'title' => 'Pengadaan',
            'purpose' => 'Ujian',
            'department' => $kajur->department,
        ]);
        $submission->status = 'submitted';
        $submission->save();

        $this->actingAs($kajur)
            ->followingRedirects()
            ->get(route('kajur.submissions.edit', $submission))
            ->assertSee('Hanya pengajuan berstatus draft yang dapat diubah.');
    }

    public function test_14_kajur_update_fails_when_not_draft(): void
    {
        $kajur = User::factory()->kajur()->create();
        $submission = Submission::create([
            'submission_number' => 'REQ-TEST-014',
            'user_id' => $kajur->id,
            'title' => 'Pengadaan',
            'purpose' => 'Ujian',
            'department' => $kajur->department,
        ]);
        $submission->status = 'submitted';
        $submission->save();

        $url = route('kajur.submissions.show', $submission);

        $this->actingAs($kajur)
            ->from($url)
            ->followingRedirects()
            ->put(route('kajur.submissions.update', $submission), [
                'title' => 'Judul Baru',
                'purpose' => 'Tujuan Baru',
                'items' => [
                    ['item_name' => 'Barang', 'quantity' => 1, 'unit' => 'Unit', 'estimated_price' => 1000],
                ],
            ])
            ->assertSee('Hanya pengajuan berstatus draft yang dapat diubah.');
    }

    public function test_15_kajur_cancel_fails_when_not_draft(): void
    {
        $kajur = User::factory()->kajur()->create();
        $submission = Submission::create([
            'submission_number' => 'REQ-TEST-015',
            'user_id' => $kajur->id,
            'title' => 'Pengadaan',
            'purpose' => 'Ujian',
            'department' => $kajur->department,
        ]);
        $submission->status = 'submitted';
        $submission->save();

        $url = route('kajur.submissions.show', $submission);

        $this->actingAs($kajur)
            ->from($url)
            ->followingRedirects()
            ->post(route('kajur.submissions.cancel', $submission))
            ->assertSee('Hanya pengajuan berstatus draft yang dapat dibatalkan.');
    }

    public function test_16_kajur_submit_fails_when_already_submitted(): void
    {
        $kajur = User::factory()->kajur()->create();
        $submission = Submission::create([
            'submission_number' => 'REQ-TEST-016',
            'user_id' => $kajur->id,
            'title' => 'Pengadaan',
            'purpose' => 'Ujian',
            'department' => $kajur->department,
        ]);
        $submission->status = 'submitted';
        $submission->save();

        $url = route('kajur.submissions.show', $submission);

        $this->actingAs($kajur)
            ->from($url)
            ->followingRedirects()
            ->post(route('kajur.submissions.submitDraft', $submission))
            ->assertSee('Pengajuan ini sudah pernah diajukan.');
    }

    public function test_17_document_download_file_not_found(): void
    {
        $sarpras = User::factory()->sarpras()->create();
        $doc = Document::create([
            'title' => 'BAST Rusak',
            'file_path' => 'documents/nonexistent_file_xyz123.pdf',
            'file_name' => 'bast_rusak.pdf',
            'file_size' => 1024,
            'category' => 'bast',
            'user_id' => $sarpras->id,
        ]);

        $this->actingAs($sarpras)
            ->from(route('documents.index'))
            ->followingRedirects()
            ->get(route('documents.download', $doc))
            ->assertSee('File dokumen tidak ditemukan di penyimpanan server.');
    }
}
