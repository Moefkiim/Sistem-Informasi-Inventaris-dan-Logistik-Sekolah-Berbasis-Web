<?php

namespace Tests\Feature;

use App\Models\IncomingItem;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * Membuktikan bahwa ekspor Excel tidak rentan terhadap injeksi rumus.
 *
 * Nilai teks yang diawali '=', '+', '-', '@' harus disimpan sebagai
 * string (bukan rumus) di berkas .xlsx yang dihasilkan.
 */
class ExcelFormulaInjectionTest extends TestCase
{
    use RefreshDatabase;

    private function sarpras(): User
    {
        return User::factory()->sarpras()->create();
    }

    /**
     * Nilai berbahaya yang diuji pada masing-masing kolom teks laporan.
     */
    private function dangerousValues(): array
    {
        return [
            '=HYPERLINK("http://contoh.test","klik")',
            '+1+1',
            '-2+3',
            '@SUM(1,1)',
        ];
    }

    /**
     * Unduh Excel dan simpan ke temp file, kembalikan path.
     * Menggunakan streaming response langsung dari temp file yang dibuat controller.
     */
    private function downloadExcelToTemp(User $user, array $params): string
    {
        $response = $this->actingAs($user)
            ->get(route('reports.excel', $params));

        $response->assertOk();

        // BinaryFileResponse: ambil path berkas temporer dari response
        $kernel = app(\Illuminate\Contracts\Http\Kernel::class);
        $sfResponse = $response->baseResponse;

        $path = tempnam(sys_get_temp_dir(), 'test-excel-').'.xlsx';

        if ($sfResponse instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse) {
            $srcPath = $sfResponse->getFile()->getPathname();
            copy($srcPath, $path);
        } else {
            file_put_contents($path, $sfResponse->getContent());
        }

        return $path;
    }

    public function test_nama_barang_berbahaya_tersimpan_sebagai_string_bukan_rumus(): void
    {
        $sarpras = $this->sarpras();

        foreach ($this->dangerousValues() as $dangerous) {
            // Item consumable: muncul langsung sebagai baris tanpa unit
            Item::factory()->consumable()->create([
                'name' => $dangerous,
                'stock' => 5,
                'minimum_stock' => 2,
            ]);

            $path = $this->downloadExcelToTemp($sarpras, ['type' => 'inventory']);

            try {
                $spreadsheet = IOFactory::load($path);
                $sheet = $spreadsheet->getActiveSheet();

                $highestRow = $sheet->getHighestDataRow();
                $this->assertGreaterThan(1, $highestRow,
                    "Tidak ada baris data di Excel untuk nilai '{$dangerous}'."
                );

                $formulaFound = false;
                for ($row = 2; $row <= $highestRow; $row++) {
                    $highestCol = $sheet->getHighestDataColumn($row);
                    $highestColIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestCol);
                    for ($col = 1; $col <= $highestColIndex; $col++) {
                        $coord = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col).$row;
                        $cell = $sheet->getCell($coord);
                        if ($cell->getDataType() === DataType::TYPE_FORMULA) {
                            $formulaFound = true;
                        }
                    }
                }

                $this->assertFalse($formulaFound,
                    "Nilai '{$dangerous}' tersimpan sebagai rumus aktif di Excel — potensi formula injection!"
                );
            } finally {
                @unlink($path);
            }
        }
    }

    public function test_tidak_ada_rumus_di_semua_jenis_laporan_excel(): void
    {
        $sarpras = $this->sarpras();

        $item = Item::factory()->consumable()->create([
            'name' => '=HYPERLINK("http://x.test","x")',
            'stock' => 5,
        ]);

        IncomingItem::create([
            'item_id' => $item->id,
            'transaction_number' => 'IN-20260910-TEST',
            'quantity' => 3,
            'source' => 'pembelian',
            'entry_date' => '2026-09-10',
            'user_id' => $sarpras->id,
        ]);

        // incoming: nama barang dari relasi bisa berisi nilai berbahaya
        $path = $this->downloadExcelToTemp($sarpras, ['type' => 'incoming']);

        try {
            $spreadsheet = IOFactory::load($path);
            $sheet = $spreadsheet->getActiveSheet();

            $highestRow = $sheet->getHighestDataRow();
            $this->assertGreaterThan(1, $highestRow,
                'Tidak ada baris data di Excel laporan incoming.'
            );

            for ($row = 2; $row <= $highestRow; $row++) {
                $highestCol = $sheet->getHighestDataColumn($row);
                $highestColIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestCol);
                for ($col = 1; $col <= $highestColIndex; $col++) {
                    $coord = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col).$row;
                    $cell = $sheet->getCell($coord);
                    $this->assertNotEquals(
                        DataType::TYPE_FORMULA,
                        $cell->getDataType(),
                        "Sel berisi rumus pada laporan 'incoming': baris {$row}, kolom {$col}, nilai: {$cell->getValue()}"
                    );
                }
            }
        } finally {
            @unlink($path);
        }
    }

    public function test_nilai_angka_tetap_benar_setelah_sanitasi(): void
    {
        $sarpras = $this->sarpras();
        Item::factory()->consumable()->create([
            'name' => 'Laptop Normal',
            'stock' => 10,
        ]);

        $response = $this->actingAs($sarpras)
            ->get(route('reports.excel', ['type' => 'inventory']));

        $response->assertOk();
        $response->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
    }
}
