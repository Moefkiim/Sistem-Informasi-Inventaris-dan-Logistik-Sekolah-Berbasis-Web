<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class LoginLogoAndKajurRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_login_tampil_fallback_nama_sekolah_tanpa_file_logo(): void
    {
        config([
            'siils.logo_path' => 'images/logo-tidak-ada.png',
            'siils.school_name' => 'SMKN 1 Sukatani',
        ]);

        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('SMKN 1 Sukatani');
        $response->assertDontSee('logo-tidak-ada.png');
    }

    public function test_halaman_login_tampil_img_logo_jika_file_tersedia(): void
    {
        $relativePath = 'images/test-logo-r1.png';
        $fullPath = public_path($relativePath);
        $dirExisted = is_dir(dirname($fullPath));

        File::ensureDirectoryExists(dirname($fullPath));
        file_put_contents($fullPath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='));

        try {
            config([
                'siils.logo_path' => $relativePath,
                'siils.school_name' => 'SMKN 1 Sukatani',
            ]);

            $response = $this->get(route('login'));

            $response->assertOk();
            $response->assertSee($relativePath);
            $response->assertSee('alt="Logo SMKN 1 Sukatani"', false);
        } finally {
            @unlink($fullPath);
            if (! $dirExisted && is_dir(dirname($fullPath)) && count(scandir(dirname($fullPath))) === 2) {
                @rmdir(dirname($fullPath));
            }
        }
    }

    public function test_kajur_dapat_kelola_pengajuan_lihat_inventaris_dan_ditolak_di_rute_sarpras(): void
    {
        $kajur = User::factory()->kajur()->create(['department' => 'RPL']);
        $itemRpl = Item::factory()->create(['department' => 'RPL', 'name' => 'Proyektor RPL']);
        $itemTkj = Item::factory()->create(['department' => 'TKJ', 'name' => 'Switch TKJ']);

        // 1. Kajur dapat membuat draft pengajuan.
        $this->actingAs($kajur)->post(route('kajur.submissions.store'), [
            'title' => 'Pengadaan Praktik RPL',
            'purpose' => 'Kebutuhan lab baru',
            'action' => 'draft',
            'items' => [
                [
                    'item_name' => 'Monitor 24 Inch',
                    'quantity' => 5,
                    'unit' => 'Unit',
                    'estimated_price' => 2000000,
                    'specification' => 'IPS Full HD',
                ],
            ],
        ])->assertRedirect();

        $submission = Submission::where('user_id', $kajur->id)->firstOrFail();
        $this->assertSame('draft', $submission->status);

        // 2. Kajur dapat mengedit draft dan mengirim pengajuan.
        $this->actingAs($kajur)->put(route('kajur.submissions.update', $submission), [
            'title' => 'Pengadaan Praktik RPL Revisi',
            'purpose' => 'Kebutuhan lab baru semester ini',
            'action' => 'submit',
            'items' => [
                [
                    'item_name' => 'Monitor 24 Inch',
                    'quantity' => 6,
                    'unit' => 'Unit',
                    'estimated_price' => 2000000,
                    'specification' => 'IPS Full HD',
                ],
            ],
        ])->assertRedirect();

        $submission->refresh();
        $this->assertSame('submitted', $submission->status);

        // 3. Kajur dapat melihat inventaris jurusannya sendiri dan tidak melihat jurusan lain.
        $invResponse = $this->actingAs($kajur)->get(route('kajur.inventory.index'));
        $invResponse->assertOk();
        $invResponse->assertSee($itemRpl->name);
        $invResponse->assertDontSee($itemTkj->name);

        // 4. Kajur tetap mendapat 403 pada route /sarpras/*.
        $this->actingAs($kajur)->get('/sarpras/inventory')->assertForbidden();
        $this->actingAs($kajur)->get('/sarpras/submissions')->assertForbidden();
        $this->actingAs($kajur)->get('/sarpras/realizations')->assertForbidden();
        $this->actingAs($kajur)->get('/sarpras/users')->assertForbidden();
        $this->actingAs($kajur)->get('/sarpras/locations')->assertForbidden();
    }
}
