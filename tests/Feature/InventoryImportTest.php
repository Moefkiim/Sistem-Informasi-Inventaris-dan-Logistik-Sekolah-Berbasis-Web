<?php

namespace Tests\Feature;

use App\Models\AssetUnit;
use App\Models\Item;
use App\Models\Location;
use App\Models\User;
use App\Services\InventoryImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class InventoryImportTest extends TestCase
{
    use RefreshDatabase;

    private User $sarpras;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sarpras = User::factory()->create(['role' => 'sarpras']);
        Storage::disk('local')->deleteDirectory('imports');
    }

    protected function tearDown(): void
    {
        Storage::disk('local')->deleteDirectory('imports');

        parent::tearDown();
    }

    private const HEADERS = [
        'Kode Barang', 'Nama Barang', 'Kategori', 'Tipe', 'Satuan', 'Jurusan', 'Lokasi', 'Merk',
        'Model', 'Nomor Seri', 'Nomor Inventaris Unit', 'Tahun Perolehan', 'Harga Perolehan',
        'Kondisi', 'Status', 'Stok Awal', 'Stok Minimum',
    ];

    private function csvFile(string $name, array $rows): UploadedFile
    {
        $content = implode(',', self::HEADERS)."\n";

        foreach ($rows as $row) {
            $content .= implode(',', array_map(fn ($cell) => str_contains((string) $cell, ',') ? '"'.$cell.'"' : (string) $cell, $row))."\n";
        }

        return UploadedFile::fake()->createWithContent($name, $content);
    }

    /**
     * @param  array<int,array<int,string>>  $rows
     */
    private function xlsxFile(string $name, array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(self::HEADERS, null, 'A1');

        foreach ($rows as $index => $row) {
            $sheet->fromArray($row, null, 'A'.($index + 2));
        }

        $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->setPreCalculateFormulas(false);
        $writer->save($path);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet, $writer);
        gc_collect_cycles();

        $content = file_get_contents($path);
        unlink($path);

        return UploadedFile::fake()->createWithContent($name, $content);
    }

    private function service(): InventoryImportService
    {
        return app(InventoryImportService::class);
    }

    public function test_impor_valid_membuat_barang_dan_unit(): void
    {
        Storage::disk('local')->makeDirectory('imports');
        Location::factory()->create(['name' => 'Lab RPL 1', 'code' => 'RPL-01']);
        $sarpras = User::factory()->create(['role' => 'sarpras']);

        $file = $this->csvFile('inventaris.csv', [
            ['LAP-001', 'Laptop', 'Elektronik', 'individual', 'Unit', 'RPL', 'Lab RPL 1', 'Lenovo', 'E15', 'SN-1', '', '2024', '8000000', 'Baik', 'Aktif', '', ''],
            ['LAP-001', 'Laptop', 'Elektronik', 'individual', 'Unit', 'RPL', 'Lab RPL 1', 'Lenovo', 'E15', 'SN-2', '', '2024', '8000000', 'Baik', 'Aktif', '', ''],
            ['ATK-001', 'Kertas A4', 'ATK', 'habis pakai', 'Rim', '', '', 'PaperOne', '', '', '', '', '', 'Baik', 'Aktif', '50', '10'],
        ]);

        $response = $this->actingAs($sarpras)->post(route('sarpras.inventory.import.preview'), ['file' => $file]);
        $response->assertOk()->assertViewHas('analysis');
        $analysis = $response->viewData('analysis');

        $this->assertSame(2, $analysis['summary']['barang']);
        $this->assertSame(2, $analysis['summary']['unit']);
        $this->assertSame(0, $analysis['summary']['errors']);

        $this->actingAs($sarpras)
            ->post(route('sarpras.inventory.import.confirm'), ['token' => $response->viewData('token')])
            ->assertRedirect(route('sarpras.inventory.index'));

        $this->assertSame(2, Item::count());
        $laptop = Item::where('code', 'LAP-001')->firstOrFail();
        $this->assertSame('individual', $laptop->item_type);
        $this->assertSame(2, $laptop->stock);
        $this->assertSame(2, AssetUnit::where('item_id', $laptop->id)->count());
        $this->assertSame(2, AssetUnit::where('item_id', $laptop->id)->whereNotNull('serial_number')->count());

        $kertas = Item::where('code', 'ATK-001')->firstOrFail();
        $this->assertSame('consumable', $kertas->item_type);
        $this->assertSame(50, $kertas->stock);

        $this->assertDatabaseHas('activity_logs', ['action' => 'inventory_imported']);
    }

    public function test_kode_otomatis_dibuat_saat_kosong(): void
    {
        Storage::disk('local')->makeDirectory('imports');
        $sarpras = User::factory()->create(['role' => 'sarpras']);

        $file = $this->csvFile('inventaris.csv', [
            ['', 'Proyektor', 'Elektronik', 'individual', 'Unit', 'RPL', '', 'Epson', '', '', '', '', '', '', '', '', ''],
        ]);

        $response = $this->actingAs($sarpras)->post(route('sarpras.inventory.import.preview'), ['file' => $file]);
        $this->actingAs($sarpras)->post(route('sarpras.inventory.import.confirm'), ['token' => $response->viewData('token')]);

        $item = Item::firstOrFail();
        $this->assertStringStartsWith('BRG-RPL-', $item->code);
        $this->assertSame(1, $item->assetUnits()->count());
    }

    public function test_kesalahan_dilaporkan_dengan_nomor_baris_dan_impor_dibatalkan(): void
    {
        Storage::disk('local')->makeDirectory('imports');
        $sarpras = User::factory()->create(['role' => 'sarpras']);
        AssetUnit::factory()->create(['serial_number' => 'SN-DUP']);
        $baselineItems = Item::count();

        $file = $this->csvFile('inventaris.csv', [
            ['A-1', 'Barang Baik', 'Umum', 'habis pakai', 'Pcs', '', '', '', '', '', '', '', '', 'Baik', 'Aktif', '5', '1'],
            ['A-2', 'Tipe Salah', 'Umum', 'barang aneh', 'Pcs', '', '', '', '', '', '', '', '', 'Baik', 'Aktif', '', ''],
            ['A-3', 'Seri Duplikat', 'Umum', 'individual', 'Unit', '', '', '', '', 'SN-DUP', '', '', '', 'Baik', 'Aktif', '', ''],
        ]);

        $response = $this->actingAs($sarpras)->post(route('sarpras.inventory.import.preview'), ['file' => $file]);
        $analysis = $response->viewData('analysis');

        $this->assertSame(2, $analysis['summary']['errors']);
        $rowNumbers = array_column($analysis['errors'], 'row');
        $this->assertContains(3, $rowNumbers);
        $this->assertContains(4, $rowNumbers);

        $this->actingAs($sarpras)
            ->post(route('sarpras.inventory.import.confirm'), ['token' => $response->viewData('token')])
            ->assertRedirect(route('sarpras.inventory.import.create'))
            ->assertSessionHasErrors('file');

        $this->assertSame($baselineItems, Item::count());
        $this->assertDatabaseMissing('items', ['code' => 'A-1']);
    }

    public function test_impor_bersifat_semua_atau_tidak_meski_sebagian_baris_valid(): void
    {
        Location::factory()->create(['name' => 'Gudang', 'code' => 'GD']);
        $sarpras = User::factory()->create(['role' => 'sarpras']);

        $file = $this->csvFile('inventaris.csv', [
            ['', 'Barang Valid', 'Umum', 'individual', 'Unit', '', 'Gudang', '', '', '', '', '', '', 'Baik', 'Aktif', '', ''],
            ['', '', 'Umum', 'individual', 'Unit', '', '', '', '', '', '', '', '', 'Baik', 'Aktif', '', ''],
        ]);

        $response = $this->actingAs($sarpras)->post(route('sarpras.inventory.import.preview'), ['file' => $file]);
        $this->actingAs($sarpras)->post(route('sarpras.inventory.import.confirm'), ['token' => $response->viewData('token')]);

        $this->assertSame(0, Item::count());
        $this->assertSame(0, AssetUnit::count());
    }

    public function test_duplikat_kode_habis_pakai_dan_seri_dalam_berkas_dilaporkan(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'imp').'.csv';
        $content = implode(',', self::HEADERS)."\n"
            ."X-1,Kertas,ATK,habis pakai,Rim,,,,,,,,,Baik,Aktif,10,1\n"
            ."X-1,Kertas Lagi,ATK,habis pakai,Rim,,,,,,,,,Baik,Aktif,5,1\n"
            ."X-2,Buku,ATK,habis pakai,Pcs,,,,,SERI-1,,,,,Baik,Aktif,3,1\n"
            ."X-3,Buku Lagi,ATK,habis pakai,Pcs,,,,,SERI-1,,,,,Baik,Aktif,3,1\n";
        file_put_contents($path, $content);

        $analysis = $this->service()->analyze($path);
        unlink($path);

        $columns = array_column($analysis['errors'], 'column');
        $this->assertContains('Kode Barang', $columns);
        $this->assertContains('Nomor Seri', $columns);
    }

    public function test_batas_ukuran_tipe_dan_jumlah_baris(): void
    {
        $sarpras = User::factory()->create(['role' => 'sarpras']);

        $this->actingAs($sarpras)
            ->post(route('sarpras.inventory.import.preview'), ['file' => UploadedFile::fake()->create('besar.csv', 3000)])
            ->assertSessionHasErrors('file');

        $this->actingAs($sarpras)
            ->post(route('sarpras.inventory.import.preview'), ['file' => UploadedFile::fake()->create('data.txt', 10)])
            ->assertSessionHasErrors('file');

        $path = tempnam(sys_get_temp_dir(), 'imp').'.csv';
        $handle = fopen($path, 'w');
        fputcsv($handle, self::HEADERS);
        for ($i = 0; $i < InventoryImportService::MAX_ROWS + 1; $i++) {
            fputcsv($handle, ['', 'Barang '.$i, 'Umum', 'habis pakai', 'Pcs', '', '', '', '', '', '', '', '', 'Baik', 'Aktif', '1', '1']);
        }
        fclose($handle);

        $analysis = $this->service()->analyze($path);
        unlink($path);

        $this->assertNotEmpty($analysis['errors']);
        $this->assertStringContainsString('melebihi batas', $analysis['errors'][0]['message']);
    }

    public function test_rumus_spreadsheet_tidak_dievaluasi(): void
    {
        $file = $this->xlsxFile('inventaris.xlsx', [
            ['', 'Laptop', 'Elektronik', '=CONCATENATE("indiv","idual")', 'Unit', '', '', '', '', '', '', '', '', 'Baik', 'Aktif', '', ''],
        ]);

        Storage::disk('local')->makeDirectory('imports');
        $sarpras = User::factory()->create(['role' => 'sarpras']);

        $response = $this->actingAs($sarpras)->post(route('sarpras.inventory.import.preview'), ['file' => $file]);
        $analysis = $response->viewData('analysis');

        $this->assertContains('Tipe', array_column($analysis['errors'], 'column'));
        $this->assertSame(0, $analysis['summary']['barang']);
    }

    public function test_hanya_sarpras_yang_dapat_mengakses_impor(): void
    {
        $kajur = User::factory()->create(['role' => 'kajur', 'department' => 'RPL']);
        $kepsek = User::factory()->create(['role' => 'kepala_sekolah']);

        $this->actingAs($kajur)->get(route('sarpras.inventory.import.create'))->assertForbidden();
        $this->actingAs($kepsek)->get(route('sarpras.inventory.import.create'))->assertForbidden();
    }

    public function test_template_dapat_diunduh(): void
    {
        $sarpras = User::factory()->create(['role' => 'sarpras']);

        $this->actingAs($sarpras)
            ->get(route('sarpras.inventory.import.template'))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
}
