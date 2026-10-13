<?php

namespace Tests\Feature;

use App\Models\AssetUnit;
use App\Models\Item;
use App\Models\User;
use App\Services\InventoryImportService;
use App\Support\AssetCondition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Invarian kondisi–status unit aset (N2) dan pencegahan status terlarang
 * lewat impor (N3).
 */
class AssetUnitInvariantTest extends TestCase
{
    use RefreshDatabase;

    private User $sarpras;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sarpras = User::factory()->create(['role' => 'sarpras']);
    }

    private const HEADERS = [
        'Kode Barang', 'Nama Barang', 'Kategori', 'Tipe', 'Satuan', 'Jurusan', 'Jurusan', 'Lokasi',
    ];

    private function csv(array $rows): string
    {
        $headers = ['Kode Barang', 'Nama Barang', 'Kategori', 'Tipe', 'Satuan', 'Jurusan', 'Lokasi', 'Merk', 'Model', 'Nomor Seri', 'Nomor Inventaris Unit', 'Tahun Perolehan', 'Harga Perolehan', 'Kondisi', 'Status', 'Stok Awal', 'Stok Minimum'];

        $path = tempnam(sys_get_temp_dir(), 'inv').'.csv';

        $handle = fopen($path, 'w');
        fputcsv($handle, $headers);

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);

        return $path;
    }

    private function analyze(array $rows): array
    {
        $path = $this->csv($rows);
        $analysis = app(InventoryImportService::class)->analyze($path);
        unlink($path);

        return $analysis;
    }

    public function test_reaktivasi_dari_perbaikan_menolak_kondisi_hasil_tidak_layak(): void
    {
        $item = Item::factory()->individual()->create();
        $unit = AssetUnit::factory()->for($item)->create([
            'current_status' => 'dalam_perbaikan',
            'current_condition' => AssetCondition::RUSAK_BERAT,
        ]);

        $this->expectException(ValidationException::class);

        try {
            $unit->changeStatus('aktif', 'Selesai perbaikan', User::factory()->create(), AssetCondition::HILANG);
        } finally {
            $this->assertSame('dalam_perbaikan', $unit->fresh()->current_status);
            $this->assertSame(AssetCondition::RUSAK_BERAT, $unit->fresh()->current_condition);
        }
    }

    public function test_reaktivasi_dari_perbaikan_menyetel_kondisi_hasil(): void
    {
        $item = Item::factory()->individual()->create();
        $unit = AssetUnit::factory()->for($item)->create([
            'current_status' => 'dalam_perbaikan',
            'current_condition' => AssetCondition::RUSAK_BERAT,
        ]);

        $unit->changeStatus('aktif', 'Selesai perbaikan', User::factory()->create(), AssetCondition::RUSAK_RINGAN);

        $unit->refresh();
        $this->assertSame('aktif', $unit->current_status);
        $this->assertSame(AssetCondition::RUSAK_RINGAN, $unit->current_condition);
    }

    public function test_unit_berkondisi_tidak_layak_tidak_boleh_diaktifkan(): void
    {
        $item = Item::factory()->individual()->create();
        $unit = AssetUnit::factory()->for($item)->create([
            'current_status' => 'tidak_aktif',
            'current_condition' => AssetCondition::TIDAK_LAYAK_PAKAI,
        ]);

        $this->expectException(ValidationException::class);

        $unit->changeStatus('aktif', 'Coba aktifkan', User::factory()->create());
    }

    public function test_unit_hilang_atau_tidak_layak_tidak_muncul_di_pilihan_peminjaman(): void
    {
        $item = Item::factory()->individual()->create();

        $baik = AssetUnit::factory()->for($item)->create(['current_status' => 'aktif', 'current_condition' => AssetCondition::BAIK]);
        $hilang = AssetUnit::factory()->for($item)->create(['current_status' => 'aktif', 'current_condition' => AssetCondition::HILANG]);
        $tidakLayak = AssetUnit::factory()->for($item)->create(['current_status' => 'aktif', 'current_condition' => AssetCondition::TIDAK_LAYAK_PAKAI]);

        $ids = AssetUnit::query()->loanable()->pluck('id')->all();

        $this->assertContains($baik->id, $ids);
        $this->assertNotContains($hilang->id, $ids);
        $this->assertNotContains($tidakLayak->id, $ids);
        $this->assertFalse($hilang->fresh()->isAvailableForLoan());
        $this->assertTrue($baik->fresh()->isAvailableForLoan());
    }

    public function test_impor_menolak_status_dipinjam_dan_dihapuskan(): void
    {
        $analysis = $this->analyze([
            ['A-1', 'Unit Dipinjam', 'Umum', 'individual', 'Unit', '', '', '', '', '', '', '', '', 'Baik', 'Dipinjam', '', ''],
            ['A-2', 'Unit Dihapus', 'Umum', 'individual', 'Unit', '', '', '', '', '', '', '', '', 'Baik', 'Dihapuskan', '', ''],
        ]);

        $statusErrors = array_values(array_filter(
            $analysis['errors'],
            fn ($error) => ($error['column'] ?? null) === 'Status'
        ));

        $this->assertCount(2, $statusErrors);
        $this->assertStringContainsString('tidak boleh diimpor', $statusErrors[0]['message']);
        $this->assertSame(0, $analysis['summary']['barang']);
    }

    public function test_impor_kondisi_hilang_dipaksa_tidak_aktif_dengan_peringatan(): void
    {
        $analysis = $this->analyze([
            ['H-1', 'Laptop Hilang', 'Elektronik', 'individual', 'Unit', '', '', '', '', 'SN-H', '', '', '', 'Hilang', 'Aktif', '', ''],
        ]);

        $this->assertSame(0, $analysis['summary']['errors']);
        $this->assertGreaterThanOrEqual(1, $analysis['summary']['warnings']);
        $this->assertStringContainsString('disimpan sebagai', $analysis['warnings'][0]['message']);

        $actor = User::factory()->create(['role' => 'sarpras']);
        app(InventoryImportService::class)->commit($analysis, $actor);

        $unit = AssetUnit::where('current_condition', AssetCondition::HILANG)->firstOrFail();

        $this->assertSame('tidak_aktif', $unit->current_status);
        $this->assertSame('tidak_aktif', $unit->item->current_status);
    }

    public function test_siils_check_mendeteksi_pelanggaran_invarian(): void
    {
        $item = Item::factory()->individual()->create(['stock' => 0]);
        AssetUnit::factory()->for($item)->create([
            'current_status' => 'aktif',
            'current_condition' => AssetCondition::HILANG,
        ]);

        $this->artisan('siils:check')
            ->expectsOutputToContain('Invarian data perlu ditinjau')
            ->assertExitCode(0);
    }
}
