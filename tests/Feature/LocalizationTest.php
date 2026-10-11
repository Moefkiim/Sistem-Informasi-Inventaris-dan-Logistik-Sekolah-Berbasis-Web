<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_pesan_validasi_berbahasa_indonesia(): void
    {
        $this->from('/login')
            ->post('/login', [])
            ->assertInvalid([
                'login' => 'Kolom username atau surel wajib diisi.',
                'password' => 'Kolom kata sandi wajib diisi.',
            ]);
    }

    public function test_nama_bulan_berbahasa_indonesia_pada_dashboard(): void
    {
        $this->assertSame('id', app()->getLocale());

        $label = now()->translatedFormat('M y');
        $bulanIndonesia = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

        $this->assertContains(substr($label, 0, 3), $bulanIndonesia, 'Label bulan dashboard tidak berbahasa Indonesia.');

        $sarpras = User::factory()->sarpras()->create();

        $this->actingAs($sarpras)
            ->get(route('home'))
            ->assertOk()
            ->assertSee($label, false);
    }

    public function test_teks_paginasi_berbahasa_indonesia(): void
    {
        $sarpras = User::factory()->sarpras()->create();

        Item::factory()->count(30)->create();

        $this->actingAs($sarpras)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Berikutnya');
    }
}
