<?php

namespace App\Http\Controllers;

use App\Models\Distribution;
use App\Models\IncomingItem;
use App\Models\Item;
use App\Models\OutgoingItem;
use App\Models\Submission;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    /**
     * Halaman laporan inventaris dan transaksi logistik dengan filter date range.
     */
    public function index(Request $request): View
    {
        $type = $request->get('type', 'inventory');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        $department = $this->resolveDepartment($request);

        $isPrint = $request->get('export') === 'print';
        $data = $this->reportQuery($type, $startDate, $endDate, $department, ! $isPrint);

        if ($isPrint) {
            return view('reports.print', compact('data', 'type', 'startDate', 'endDate', 'department'));
        }

        return view('reports.index', compact('data', 'type', 'startDate', 'endDate', 'department'));
    }

    /**
     * Unduh laporan dalam bentuk PDF (server-side render via dompdf).
     */
    public function exportPdf(Request $request): Response
    {
        $validated = $this->validatedType($request);

        $type = $validated['type'];
        $startDate = $validated['start_date'] ?? null;
        $endDate = $validated['end_date'] ?? null;
        $department = $this->resolveDepartment($request);

        $data = $this->reportQuery($type, $startDate, $endDate, $department, false);

        $fileName = 'laporan-'.$type.'-'.date('Y-m-d-His').'.pdf';
        $pdf = Pdf::loadView('reports.pdf', compact('data', 'type', 'department', 'startDate', 'endDate'))
            ->setPaper('a4', 'landscape');

        return $pdf->download($fileName);
    }

    /**
     * Unduh laporan dalam bentuk Excel (.xlsx) via PhpSpreadsheet.
     */
    public function exportExcel(Request $request): BinaryFileResponse
    {
        $validated = $this->validatedType($request);

        $type = $validated['type'];
        $startDate = $validated['start_date'] ?? null;
        $endDate = $validated['end_date'] ?? null;
        $department = $this->resolveDepartment($request);

        $data = $this->reportQuery($type, $startDate, $endDate, $department, false);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr(str_replace(['-', ' '], '_', ucfirst($type)), 0, 31));

        $columns = $this->excelColumns($type);
        $rows = [];

        foreach ($data as $record) {
            $rows[] = $this->excelRow($type, $record);
        }

        $lastColumn = Coordinate::stringFromColumnIndex(count($columns));

        foreach ($columns as $index => $label) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($index + 1).'1', $label);
        }

        $headerStyle = $sheet->getStyle('A1:'.$lastColumn.'1');
        $headerStyle->getFont()->setBold(true);
        $headerStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE8EFF7');
        $headerStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        foreach ($rows as $rowIndex => $row) {
            $excelRow = $rowIndex + 2;
            foreach ($row as $colIndex => $value) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex + 1).$excelRow, $value);
            }
        }

        foreach (range(1, count($columns)) as $colIndex) {
            $columnLetter = Coordinate::stringFromColumnIndex($colIndex);
            $sheet->getColumnDimension($columnLetter)->setAutoSize(true);
        }

        $sheet->getStyle('A1:'.$lastColumn.($rows ? count($rows) + 1 : 1))
            ->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

        $tempPath = tempnam(sys_get_temp_dir(), 'laporan').'.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);

        $fileName = 'laporan-'.$type.'-'.date('Y-m-d-His').'.xlsx';

        return response()->download($tempPath, $fileName)->deleteFileAfterSend(true);
    }

    /**
     * Validasi tipe laporan dan rentang tanggal (dipakai export PDF & Excel).
     */
    private function validatedType(Request $request): array
    {
        return $request->validate([
            'type' => ['required', 'in:inventory,incoming,outgoing,distribution,submission'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ]);
    }

    /**
     * Kajur otomatis dikunci ke jurusannya; role lain bebas memilih filter.
     */
    private function resolveDepartment(Request $request): ?string
    {
        $user = $request->user();
        $department = $request->get('department');

        if ($user->isKajur()) {
            if (empty($user->department)) {
                abort(403, 'Akses ditolak: Jurusan akun Kajur belum diatur.');
            }
            $department = $user->department;
        }

        return $department;
    }

    /**
     * Bangun query laporan sesuai jenis; paginate=false untuk print/export.
     */
    private function reportQuery(string $type, ?string $startDate, ?string $endDate, ?string $department, bool $paginate)
    {
        switch ($type) {
            case 'incoming':
                $query = IncomingItem::with(['item', 'user'])->latest('entry_date');
                if ($startDate && $endDate) {
                    $query->whereBetween('entry_date', [$startDate, $endDate]);
                }
                if ($department) {
                    $query->whereHas('item', fn ($q) => $q->where('department', $department));
                }
                break;

            case 'outgoing':
                $query = OutgoingItem::with(['item', 'user'])->latest('exit_date');
                if ($startDate && $endDate) {
                    $query->whereBetween('exit_date', [$startDate, $endDate]);
                }
                if ($department) {
                    $query->whereHas('item', fn ($q) => $q->where('department', $department));
                }
                break;

            case 'distribution':
                $query = Distribution::with(['item', 'toLocation', 'user'])->latest('distribution_date');
                if ($startDate && $endDate) {
                    $query->whereBetween('distribution_date', [$startDate, $endDate]);
                }
                if ($department) {
                    $query->where('recipient_department', $department);
                }
                break;

            case 'submission':
                $query = Submission::with(['user', 'items'])->latest();
                if ($startDate && $endDate) {
                    $query->whereBetween('created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59']);
                }
                if ($department) {
                    $query->where('department', $department);
                }
                break;

            case 'inventory':
            default:
                $query = Item::with(['location'])->latest();
                if ($department) {
                    $query->where('department', $department);
                }
                break;
        }

        return $paginate ? $query->paginate(20) : $query->get();
    }

    /**
     * Daftar label kolom untuk file Excel per jenis laporan.
     */
    private function excelColumns(string $type): array
    {
        return match ($type) {
            'incoming' => ['No. Transaksi', 'Nama Barang', 'Jumlah', 'Asal / Sumber', 'Tanggal Masuk', 'Pencatat'],
            'outgoing' => ['No. Transaksi', 'Nama Barang', 'Jumlah', 'Alasan Keluar', 'Tanggal Keluar', 'Pencatat'],
            'distribution' => ['No. Distribusi', 'Nama Barang', 'Jumlah', 'Lokasi Tujuan', 'Jurusan Penerima', 'Tanggal'],
            'submission' => ['No. Pengajuan', 'Judul', 'Jurusan', 'Pengaju', 'Status', 'Tanggal'],
            default => ['Kode Barang', 'Nama Barang', 'Kategori', 'Stok', 'Kondisi', 'Lokasi', 'Jurusan'],
        };
    }

    /**
     * Konversi satu baris data menjadi array nilai sesuai urutan kolom Excel.
     */
    private function excelRow(string $type, $record): array
    {
        return match ($type) {
            'incoming' => [
                $record->transaction_number,
                $record->item->name ?? '-',
                (int) $record->quantity,
                ucfirst((string) $record->source).($record->source_origin ? ' ('.$record->source_origin.')' : ''),
                $record->entry_date->format('d/m/Y'),
                $record->user->name ?? '-',
            ],
            'outgoing' => [
                $record->transaction_number,
                $record->item->name ?? '-',
                (int) $record->quantity,
                $record->reason,
                $record->exit_date->format('d/m/Y'),
                $record->user->name ?? '-',
            ],
            'distribution' => [
                $record->distribution_number,
                $record->item->name ?? '-',
                (int) $record->quantity,
                $record->toLocation->name ?? '-',
                $record->recipient_department ?? '-',
                $record->distribution_date->format('d/m/Y'),
            ],
            'submission' => [
                $record->submission_number,
                $record->title,
                $record->department,
                $record->user->name ?? '-',
                strtoupper(str_replace('_', ' ', $record->status)),
                $record->created_at->format('d/m/Y'),
            ],
            default => [
                $record->code,
                $record->name,
                $record->category,
                (int) $record->stock.' '.$record->unit,
                ucfirst(str_replace('_', ' ', $record->current_condition)),
                $record->location->name ?? '-',
                $record->department ?: 'Umum',
            ],
        };
    }
}
