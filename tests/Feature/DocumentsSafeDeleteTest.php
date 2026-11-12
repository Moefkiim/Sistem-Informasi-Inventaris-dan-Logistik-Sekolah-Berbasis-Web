<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * PR-6: F7 — Soft-delete dokumen (file fisik tidak langsung dihapus) + command purge.
 */
class DocumentsSafeDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function sarpras(): User
    {
        return User::factory()->sarpras()->create();
    }

    private function kajur(): User
    {
        return User::factory()->kajur()->create();
    }

    public function test_hapus_dokumen_hanya_soft_delete_file_fisik_tetap_ada(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('documents/test-doc.pdf', 'isi file palsu');

        $sarpras = $this->sarpras();
        $doc = Document::create([
            'title' => 'Nota Pembelian Printer',
            'file_path' => 'documents/test-doc.pdf',
            'file_name' => 'nota-printer.pdf',
            'file_type' => 'pdf',
            'file_size' => 1024,
            'category' => 'Nota',
            'user_id' => $sarpras->id,
        ]);

        $this->actingAs($sarpras)
            ->delete(route('documents.destroy', $doc))
            ->assertRedirect(route('documents.index'))
            ->assertSessionHas('success');

        // Record database harus soft-deleted
        $this->assertSoftDeleted('documents', ['id' => $doc->id]);

        // File fisik TIDAK boleh dihapus
        Storage::disk('local')->assertExists('documents/test-doc.pdf');

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'document_deleted',
            'user_id' => $sarpras->id,
        ]);
    }

    public function test_kajur_tidak_dapat_menghapus_dokumen(): void
    {
        $kajur = $this->kajur();
        $doc = Document::factory()->create();

        $this->actingAs($kajur)
            ->delete(route('documents.destroy', $doc))
            ->assertForbidden();

        $this->assertDatabaseHas('documents', [
            'id' => $doc->id,
            'deleted_at' => null,
        ]);
    }

    public function test_command_purge_menghapus_file_dan_record_dokumen_lama(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('documents/old-doc.pdf', 'isi file lama');

        $sarpras = $this->sarpras();

        // Buat dokumen yang sudah di-soft-delete 31 hari yang lalu
        $doc = Document::create([
            'title' => 'Dokumen Lama',
            'file_path' => 'documents/old-doc.pdf',
            'file_name' => 'old-doc.pdf',
            'file_type' => 'pdf',
            'file_size' => 512,
            'category' => 'Umum',
            'user_id' => $sarpras->id,
        ]);
        $doc->delete();
        $doc->forceFill(['deleted_at' => now()->subDays(31)])->save();

        // Jalankan command purge dengan --days=30
        $exitCode = Artisan::call('siils:documents:purge', ['--days' => 30]);

        $this->assertSame(0, $exitCode);

        // Record harus benar-benar dihapus (force delete)
        $this->assertDatabaseMissing('documents', ['id' => $doc->id]);

        // File fisik harus dihapus oleh command
        Storage::disk('local')->assertMissing('documents/old-doc.pdf');
    }

    public function test_command_purge_tidak_hapus_dokumen_yang_belum_cukup_lama(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('documents/recent-doc.pdf', 'isi file baru');

        $sarpras = $this->sarpras();

        // Buat dokumen yang baru saja di-soft-delete (5 hari yang lalu)
        $doc = Document::create([
            'title' => 'Dokumen Baru Dihapus',
            'file_path' => 'documents/recent-doc.pdf',
            'file_name' => 'recent-doc.pdf',
            'file_type' => 'pdf',
            'file_size' => 512,
            'category' => 'Umum',
            'user_id' => $sarpras->id,
        ]);
        $doc->delete();
        $doc->forceFill(['deleted_at' => now()->subDays(5)])->save();

        Artisan::call('siils:documents:purge', ['--days' => 30]);

        // Record masih ada karena belum 30 hari
        $this->assertSoftDeleted('documents', ['id' => $doc->id]);
        Storage::disk('local')->assertExists('documents/recent-doc.pdf');
    }

    public function test_validasi_department_sarpras_upload_dokumen_dengan_department_tidak_valid(): void
    {
        $sarpras = $this->sarpras();

        $this->actingAs($sarpras)
            ->post(route('documents.store'), [
                'title' => 'Test Dokumen',
                'category' => 'Nota',
                'department' => 'JURUSAN_PALSU_XYZ',
            ])
            ->assertSessionHasErrors('department');
    }
}
