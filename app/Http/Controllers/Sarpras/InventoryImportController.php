<?php

namespace App\Http\Controllers\Sarpras;

use App\Http\Controllers\Controller;
use App\Models\AssetUnit;
use App\Services\InventoryImportService;
use App\Support\AssetCondition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Impor inventaris awal dari berkas Excel/CSV (khusus Sarpras).
 */
class InventoryImportController extends Controller
{
    private const DISK = 'local';

    private const IMPORT_DIRECTORY = 'imports';

    public function __construct(private readonly InventoryImportService $import) {}

    public function create(): View
    {
        return view('sarpras.inventory.import');
    }

    public function template(): BinaryFileResponse
    {
        $spreadsheet = new Spreadsheet;
        $data = $spreadsheet->getActiveSheet();
        $data->setTitle('Data');

        $headers = [
            'Kode Barang', 'Nama Barang', 'Kategori', 'Tipe', 'Satuan', 'Jurusan',
            'Lokasi', 'Merk', 'Model', 'Nomor Seri', 'Nomor Inventaris Unit',
            'Tahun Perolehan', 'Harga Perolehan', 'Kondisi', 'Status', 'Stok Awal', 'Stok Minimum',
        ];

        foreach ($headers as $index => $label) {
            $data->setCellValue([$index + 1, 1], $label);
        }

        $data->fromArray([
            '', 'Laptop Siswa', 'Elektronik', 'individual', 'Unit', 'RPL',
            'Lab RPL 1', 'Lenovo', 'ThinkPad E15', 'SN-0001', '', 2024, 8500000, 'Baik', 'Aktif', '', '',
        ], null, 'A2');

        $data->fromArray([
            'BRG-RPL-0001', 'Laptop Siswa', 'Elektronik', 'individual', 'Unit', 'RPL',
            'Lab RPL 1', 'Lenovo', 'ThinkPad E15', 'SN-0002', '', 2024, 8500000, 'Baik', 'Aktif', '', '',
        ], null, 'A3');

        $data->fromArray([
            '', 'Kertas HVS A4', 'ATK', 'habis pakai', 'Rim', '', '', 'PaperOne', '', '', '', '', '', 'Baik', 'Aktif', 50, 10,
        ], null, 'A4');

        $headerStyle = $data->getStyle('A1:Q1');
        $headerStyle->getFont()->setBold(true);
        $headerStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE8EFF7');
        $headerStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        foreach (range(1, count($headers)) as $column) {
            $data->getColumnDimensionByColumn($column)->setAutoSize(true);
        }

        $guide = $spreadsheet->createSheet();
        $guide->setTitle('Petunjuk');
        $guide->fromArray(['Kolom', 'Wajib', 'Nilai Valid / Keterangan'], null, 'A1');
        $guide->getStyle('A1:C1')->getFont()->setBold(true);

        $instructions = [
            ['Kode Barang', 'Tidak', 'Kosongkan agar kode dibuat otomatis (mis. BRG-RPL-0001). Baris dengan kode sama digabung menjadi satu barang dengan beberapa unit.'],
            ['Nama Barang', 'Ya', 'Nama barang inventaris.'],
            ['Kategori', 'Tidak', 'Kosongkan untuk memakai "Umum".'],
            ['Tipe', 'Ya', 'individual atau habis pakai.'],
            ['Satuan', 'Tidak', 'Contoh: Unit, Pcs, Rim.'],
            ['Jurusan', 'Tidak', 'Kode jurusan: '.implode(', ', array_keys(config('departments', []))).'.'],
            ['Lokasi', 'Tidak', 'Nama persis dari master lokasi.'],
            ['Merk', 'Tidak', 'Merk / produsen.'],
            ['Model', 'Tidak', 'Tipe / model.'],
            ['Nomor Seri', 'Tidak', 'Harus unik; duplikat dilaporkan sebagai kesalahan.'],
            ['Nomor Inventaris Unit', 'Tidak', 'Kosongkan agar dibuat otomatis. Harus unik.'],
            ['Tahun Perolehan', 'Tidak', 'Angka 4 digit, contoh 2024.'],
            ['Harga Perolehan', 'Tidak', 'Angka tanpa simbol, contoh 8500000.'],
            ['Kondisi', 'Tidak', 'Salah satu: '.implode(', ', array_values(AssetCondition::LABELS)).'.'],
            ['Status', 'Tidak', 'Bawaan Aktif. Salah satu: '.implode(', ', array_map(fn ($status) => AssetUnit::STATUS_LABELS[$s = $status], AssetUnit::IMPORTABLE_STATUSES)).'. Kondisi Hilang / Tidak Layak Pakai otomatis menjadi Tidak Aktif. Status Dipinjam/Dihapuskan tidak boleh diimpor.'],
            ['Stok Awal', 'Tidak', 'Wajib untuk barang habis pakai.'],
            ['Stok Minimum', 'Tidak', 'Untuk barang habis pakai.'],
        ];

        foreach ($instructions as $rowIndex => $instruction) {
            $guide->fromArray($instruction, null, 'A'.($rowIndex + 2));
        }

        foreach (['A', 'B', 'C'] as $column) {
            $guide->getColumnDimension($column)->setAutoSize(true);
        }

        $path = tempnam(sys_get_temp_dir(), 'template-impor').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return response()->download($path, 'template-impor-inventaris.xlsx')->deleteFileAfterSend(true);
    }

    public function preview(Request $request): View
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,csv', 'max:2048'],
        ], [
            'file.mimes' => 'Berkas harus berformat .xlsx atau .csv.',
            'file.max' => 'Ukuran berkas maksimal 2 MB.',
        ]);

        $file = $request->file('file');
        $extension = strtolower((string) $file->getClientOriginalExtension()) === 'csv' ? 'csv' : 'xlsx';
        $token = now()->format('YmdHis').'-'.bin2hex(random_bytes(8)).'.'.$extension;

        Storage::disk(self::DISK)->putFileAs(self::IMPORT_DIRECTORY, $file, $token);

        $path = Storage::disk(self::DISK)->path(self::IMPORT_DIRECTORY.'/'.$token);
        $analysis = $this->import->analyze($path);

        return view('sarpras.inventory.import-preview', [
            'token' => $token,
            'originalName' => $file->getClientOriginalName(),
            'analysis' => $analysis,
        ]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9.\-]+$/'],
        ]);

        $token = basename($validated['token']);
        $path = Storage::disk(self::DISK)->path(self::IMPORT_DIRECTORY.'/'.$token);

        if (! Storage::disk(self::DISK)->exists(self::IMPORT_DIRECTORY.'/'.$token)) {
            throw ValidationException::withMessages(['file' => 'Berkas pratinjau sudah tidak tersedia. Silakan unggah ulang.']);
        }

        $analysis = $this->import->analyze($path);

        if (! empty($analysis['errors'])) {
            return redirect()->route('sarpras.inventory.import.create')
                ->withErrors(['file' => 'Impor dibatalkan karena masih ada kesalahan validasi.'])
                ->with('import_errors', $analysis['errors']);
        }

        $result = $this->import->commit($analysis, $request->user());

        Storage::disk(self::DISK)->delete(self::IMPORT_DIRECTORY.'/'.$token);

        return redirect()->route('sarpras.inventory.index')->with(
            'success',
            "Impor inventaris berhasil (batch {$result['batch']}): {$analysis['summary']['barang']} barang dan {$analysis['summary']['unit']} unit ditambahkan."
        );
    }

    public function errors(Request $request, string $token): Response
    {
        $token = basename($token);
        $path = Storage::disk(self::DISK)->path(self::IMPORT_DIRECTORY.'/'.$token);

        if (! Storage::disk(self::DISK)->exists(self::IMPORT_DIRECTORY.'/'.$token)) {
            abort(404, 'Berkas pratinjau tidak ditemukan.');
        }

        $analysis = $this->import->analyze($path);

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['Baris', 'Kolom', 'Keterangan']);

        foreach ($analysis['errors'] as $error) {
            fputcsv($handle, [$error['row'] ?: '-', $error['column'], $error['message']]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv, Response::HTTP_OK, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="kesalahan-impor.csv"',
        ]);
    }
}
