<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_download_document(): void
    {
        $sarpras = User::factory()->create(['role' => 'sarpras', 'is_active' => true]);
        $document = Document::create([
            'title' => 'Invoice Pembelian',
            'file_path' => 'documents/test.pdf',
            'file_name' => 'test.pdf',
            'file_type' => 'pdf',
            'file_size' => 1024,
            'category' => 'Nota',
            'user_id' => $sarpras->id,
        ]);

        $response = $this->get(route('documents.download', $document));
        $response->assertRedirect(route('login'));
    }

    public function test_kajur_cannot_download_other_department_document(): void
    {
        Storage::fake('local');

        $sarpras = User::factory()->create(['role' => 'sarpras', 'is_active' => true]);
        $kajurRPL = User::factory()->create(['role' => 'kajur', 'department' => 'RPL', 'is_active' => true]);

        $file = UploadedFile::fake()->create('bast_tkj.pdf', 100);
        $path = $file->store('documents', 'local');

        $documentTKJ = Document::create([
            'title' => 'BAST TKJ',
            'file_path' => $path,
            'file_name' => 'bast_tkj.pdf',
            'file_type' => 'pdf',
            'file_size' => 100,
            'category' => 'BAST',
            'department' => 'TKJ',
            'user_id' => $sarpras->id,
        ]);

        $response = $this->actingAs($kajurRPL)->get(route('documents.download', $documentTKJ));
        $response->assertStatus(403);
    }

    public function test_kajur_can_download_own_department_document(): void
    {
        Storage::fake('local');

        $kajurRPL = User::factory()->create(['role' => 'kajur', 'department' => 'RPL', 'is_active' => true]);

        $file = UploadedFile::fake()->create('nota_rpl.pdf', 100);
        $path = $file->store('documents', 'local');

        $documentRPL = Document::create([
            'title' => 'Nota RPL',
            'file_path' => $path,
            'file_name' => 'nota_rpl.pdf',
            'file_type' => 'pdf',
            'file_size' => 100,
            'category' => 'Nota',
            'department' => 'RPL',
            'user_id' => $kajurRPL->id,
        ]);

        $response = $this->actingAs($kajurRPL)->get(route('documents.download', $documentRPL));
        $response->assertStatus(200);
    }
}
