<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * TASK 6: GUI tidak lagi bergantung pada Vite/Node. JS dimuat dari file statis
 * public/js/siils.js lewat tag <script> biasa.
 */
class StaticAssetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_file_js_statis_ada_dan_tanpa_sintaks_module(): void
    {
        $path = public_path('js/siils.js');
        $this->assertTrue(File::exists($path), 'File public/js/siils.js harus ada.');
        $this->assertTrue(File::isFile($path));

        $content = File::get($path);
        $this->assertStringNotContainsString('export function', $content, 'File statis tidak boleh memakai sintaks ES module.');
        $this->assertStringContainsString('initDynamicItemRows', $content);
        $this->assertStringContainsString('initDisableOnSubmit', $content);
    }

    public function test_layout_memuat_js_statis_dan_tidak_memakai_vite(): void
    {
        $this->seed();

        $user = User::where('username', 'kajur_rpl')->firstOrFail();

        $this->actingAs($user)
            ->get(route('kajur.submissions.create'))
            ->assertOk()
            ->assertSee('js/siils.js', false)
            ->assertDontSee('@vite')
            ->assertDontSee('/build/assets');
    }

    public function test_dokumen_layout_tidak_ada_referensi_vite(): void
    {
        $layout = File::get(resource_path('views/layouts/app.blade.php'));
        $this->assertStringNotContainsString('@vite', $layout);
    }
}
