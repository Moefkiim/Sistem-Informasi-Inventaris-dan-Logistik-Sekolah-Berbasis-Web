<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\User;
use App\Support\Departments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartmentNormalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_helper_memnormalisasi_alias_ke_kode_kanonik(): void
    {
        $this->assertSame('RPL', Departments::normalize('Rekayasa Perangkat Lunak'));
        $this->assertSame('RPL', Departments::normalize('  rpl  '));
        $this->assertSame('RPL', Departments::normalize('REKAYASA   PERANGKAT   LUNAK'));
        $this->assertSame('TKJ', Departments::normalize('Teknik Komputer Jaringan'));
        $this->assertSame('TKJ', Departments::normalize('Teknik Komputer dan Jaringan'));
        $this->assertNull(Departments::normalize('Jurusan Tak Dikenal'));
        $this->assertNull(Departments::normalize(''));
    }

    public function test_helper_mempertahankan_nilai_tak_dikenal(): void
    {
        $this->assertSame('Tata Usaha', Departments::normalizeOrKeep('Tata Usaha'));
        $this->assertSame('Lab IPA', Departments::normalizeOrKeep('  Lab IPA  '));
        $this->assertSame('RPL', Departments::normalizeOrKeep('Rekayasa Perangkat Lunak'));
        $this->assertNull(Departments::normalizeOrKeep('   '));
    }

    public function test_model_menyimpan_jurusan_sebagai_kode_kanonik(): void
    {
        $user = User::factory()->create(['department' => 'Rekayasa Perangkat Lunak']);
        $item = Item::factory()->create(['department' => 'Rekayasa Perangkat Lunak']);

        $this->assertSame('RPL', $user->fresh()->department);
        $this->assertSame('RPL', $item->fresh()->department);

        // Nilai tak dikenal tidak dirusak.
        $lain = Item::factory()->create(['department' => 'Lab IPA']);
        $this->assertSame('Lab IPA', $lain->fresh()->department);
    }

    public function test_kajur_dan_barang_dengan_penulisan_berbeda_dianggap_satu_jurusan(): void
    {
        $kajur = User::factory()->kajur()->create(['department' => 'Rekayasa Perangkat Lunak']);
        Item::factory()->create([
            'code' => 'BRG-NORM-0001',
            'name' => 'Laptop Uji Normalisasi',
            'department' => 'RPL',
        ]);

        $this->assertSame('RPL', $kajur->fresh()->department);

        $response = $this->actingAs($kajur)->get(route('reports.index'));

        $response->assertOk();
        $response->assertSee('Laptop Uji Normalisasi');
    }

    public function test_laporan_kajur_tidak_kosong_dan_dashboard_tidak_nol(): void
    {
        $kajur = User::factory()->kajur()->create(['department' => 'Rekayasa Perangkat Lunak']);
        Item::factory()->create(['department' => 'RPL', 'name' => 'Proyektor RPL']);

        $this->actingAs($kajur)
            ->get(route('home'))
            ->assertOk()
            ->assertViewHas('totalItems', fn ($total) => $total >= 1);
    }
}
