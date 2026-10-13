<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Higiene berkas pratinjau impor (N4): pembersihan otomatis sisa unggahan dan
 * batasan kepemilikan berkas.
 */
class ImportPreviewHygieneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::disk('local')->deleteDirectory('imports');
        Storage::disk('local')->makeDirectory('imports');
    }

    protected function tearDown(): void
    {
        Storage::disk('local')->deleteDirectory('imports');

        parent::tearDown();
    }

    private function validCsv(): UploadedFile
    {
        $headers = ['Kode Barang', 'Nama Barang', 'Kategori', 'Tipe', 'Satuan', 'Jurusan', 'Lokasi', 'Merk', 'Model', 'Nomor Seri', 'Nomor Inventaris Unit', 'Tahun Perolehan', 'Harga Perolehan', 'Kondisi', 'Status', 'Stok Awal', 'Stok Minimum'];
        $row = ['', 'Kertas A4', 'ATK', 'habis pakai', 'Rim', '', '', '', '', '', '', '', '', 'Baik', 'Aktif', '10', '1'];

        return UploadedFile::fake()->createWithContent('inventaris.csv', implode(',', $headers)."\n".implode(',', $row)."\n");
    }

    public function test_pratinjau_kedaluwarsa_dibersihkan_saat_unggah_baru(): void
    {
        Storage::disk('local')->put('imports/stale.csv', 'x');
        Storage::disk('local')->put('imports/stale.csv.meta.json', '{"user_id":1}');

        $stalePath = Storage::disk('local')->path('imports/stale.csv');
        touch($stalePath, time() - (25 * 3600));

        $sarpras = User::factory()->create(['role' => 'sarpras']);

        $this->actingAs($sarpras)
            ->post(route('sarpras.inventory.import.preview'), ['file' => $this->validCsv()])
            ->assertOk();

        $this->assertFalse(Storage::disk('local')->exists('imports/stale.csv'));
        $this->assertFalse(Storage::disk('local')->exists('imports/stale.csv.meta.json'));
    }

    public function test_pratinjau_terbaru_tidak_ikut_dihapus(): void
    {
        Storage::disk('local')->put('imports/fresh.csv', 'x');

        $sarpras = User::factory()->create(['role' => 'sarpras']);

        $this->actingAs($sarpras)
            ->post(route('sarpras.inventory.import.preview'), ['file' => $this->validCsv()])
            ->assertOk();

        $this->assertTrue(Storage::disk('local')->exists('imports/fresh.csv'));
    }

    public function test_konfirmasi_oleh_pengguna_lain_ditolak(): void
    {
        $uploader = User::factory()->create(['role' => 'sarpras']);
        $other = User::factory()->create(['role' => 'sarpras']);

        $response = $this->actingAs($uploader)
            ->post(route('sarpras.inventory.import.preview'), ['file' => $this->validCsv()])
            ->assertOk();

        $token = $response->viewData('token');

        $this->actingAs($other)
            ->post(route('sarpras.inventory.import.confirm'), ['token' => $token])
            ->assertNotFound();

        $this->assertDatabaseCount('items', 0);
    }

    public function test_unduh_kesalahan_oleh_pengguna_lain_ditolak(): void
    {
        $uploader = User::factory()->create(['role' => 'sarpras']);
        $other = User::factory()->create(['role' => 'sarpras']);

        $headers = ['Nama Barang', 'Tipe'];
        $file = UploadedFile::fake()->createWithContent('salah.csv', implode(',', $headers)."\n,Salah\n");

        $response = $this->actingAs($uploader)
            ->post(route('sarpras.inventory.import.preview'), ['file' => $file])
            ->assertOk();

        $token = $response->viewData('token');

        $this->actingAs($other)
            ->get(route('sarpras.inventory.import.errors', ['token' => $token]))
            ->assertNotFound();
    }
}
