<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_download_document(): void
    {
        $user = User::factory()->create();
        $doc = Document::create([
            'title' => 'Test',
            'file_path' => 'documents/test.pdf',
            'file_name' => 'test.pdf',
            'file_type' => 'pdf',
            'file_size' => 100,
            'category' => 'nota',
            'department' => null,
            'user_id' => $user->id,
        ]);

        $this->get(route('documents.download', $doc))
            ->assertRedirect(route('login'));
    }
}
