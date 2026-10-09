<?php

namespace App\Http\Controllers;

use App\Models\AssetUnit;
use App\Models\Distribution;
use App\Models\IncomingItem;
use App\Models\Item;
use App\Models\Location;
use App\Models\OutgoingItem;
use App\Models\Submission;
use App\Support\Departments;
use App\Support\ReportTypes;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
     * Ukuran halaman yang diizinkan untuk tabel laporan.
     */
    private const PER_PAGE_OPTIONS = [10, 25, 50];

    /**
     * Halaman laporan inventaris dan transaksi logistik dengan filter.
     */
    public function index(Request $request): View
    {
        $type = $this->resolveType($request);
        $filters = $this->filters($request);
        $perPage = $this->resolvePerPage($request);

        $isPrint = $request->get('export') === 'print';
        $query = $this->buildQuery($type, $filters);

        $data = $isPrint ? $query->get() : $query->paginate($perPage)->withQueryString();

        $summary = $this->summary($type, $filters);
        $filterSummary = $this->filterSummary($filters, $summary, $type);

        $viewData = [
            'data' => $data,
            'type' => $type,
            'reportTitle' => ReportTypes::title($type),
            'filters' => $filters,
            'startDate' => $filters['start_date'],
            'endDate' => $filters['end_date'],
            'department' => $filters['department'],
            'departmentLabel' => Departments::label($filters['department']),
            'summary' => $summary,
            'filterSummary' => $filterSummary,
            'perPage' => $perPage,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'locations' => Location::orderBy('name')->get(['id', 'name', 'code']),
            'datePresets' => $this->datePresets($filters),
        ];

        if ($isPrint) {
            return view('reports.print', $viewData);
        }

        return view('reports.index', $viewData);
    }

    /**
     * Unduh laporan dalam bentuk PDF (server-side render via dompdf).
     */
    public function exportPdf(Request $request): Response
    {
        $validated = $this->validatedType($request);

        $type = $validated['type'];
        $filters = $this->filters($request);
        $data = $this->buildQuery($type, $filters)->get();
        $summary = $this->summary($type, $filters);

        $fileName = 'laporan-'.$type.'-'.date('Y-m-d-His').'.pdf';
        $pdf = Pdf::loadView('reports.pdf', [
            'data' => $data,
            'type' => $type,
            'reportTitle' => ReportTypes::title($type),
            'department' => $filters['department'],
            'departmentLabel' => Departments::label($filters['department']),
            'startDate' => $filters['start_date'],
            'endDate' => $filters['end_date'],
            'summary' => $summary,
            'filterSummary' => $this->filterSummary($filters, $summary, $type),
        ])->setPaper('a4', 'landscape');

        return $pdf->download($fileName);
    }

    /**
     * Unduh laporan dalam bentuk Excel (.xlsx) via PhpSpreadsheet.
     */
    public function exportExcel(Request $request): BinaryFileResponse
    {
        $validated = $this->validatedType($request);

        $type = $validated['type'];
        $filters = $this->filters($request);
        $data = $this->buildQuery($type, $filters)->get();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr(str_replace(['-', ' '], '_', ucfirst($type)), 0, 31));

        $columns = $this->excelColumns($type);
        $rows = [];

        foreach ($data as $record) {
            if ($type === 'inventory') {
                foreach ($this->inventoryRows($record) as $row) {
                    $rows[] = $this->excelRow('inventory', $row);
                }

                continue;
            }

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
     * Jenis laporan yang dipilih; fallback ke inventaris bila tidak valid.
     */
    private function resolveType(Request $request): string
    {
        $type = (string) $request->get('type', 'inventory');

        return ReportTypes::isKnown($type) ? $type : 'inventory';
    }

    private function resolvePerPage(Request $request): int
    {
        $perPage = (int) $request->get('per_page', 25);

        return in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : 25;
    }

    /**
     * Kajur otomatis dikunci ke jurusannya; role lain bebas memilih filter.
     * Nilai jurusan dinormalkan ke kode kanonik (config/departments.php).
     */
    private function resolveDepartment(Request $request): ?string
    {
        $user = $request->user();
        $department = Departments::normalize($request->get('department'));

        if ($user->isKajur()) {
            if (empty($user->department)) {
                abort(403, 'Akses ditolak: Jurusan akun Kajur belum diatur.');
            }
            $department = $user->department;
        }

        return $department;
    }

    /**
     * Seluruh filter aktif yang dipakai query laporan.
     *
     * @return array<string,mixed>
     */
    private function filters(Request $request): array
    {
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        return [
            'department' => $this->resolveDepartment($request),
            'start_date' => $startDate ?: null,
            'end_date' => $endDate ?: null,
            'location_id' => $request->filled('location_id') ? (int) $request->get('location_id') : null,
            'condition' => in_array($request->get('condition'), ['baik', 'rusak_ringan', 'rusak_berat'], true)
                ? $request->get('condition')
                : null,
            'unit_status' => in_array($request->get('unit_status'), array_keys(AssetUnit::STATUS_LABELS), true)
                ? $request->get('unit_status')
                : null,
            'status' => in_array($request->get('status'), ['draft', 'submitted', 'reviewed_sarpras', 'approved', 'rejected', 'cancelled'], true)
                ? $request->get('status')
                : null,
        ];
    }

    /**
     * Bangun query laporan sesuai jenis dan filter.
     */
    private function buildQuery(string $type, array $filters): Builder
    {
        $startDate = $filters['start_date'];
        $endDate = $filters['end_date'];
        $department = $filters['department'];

        switch ($type) {
            case 'incoming':
                $query = IncomingItem::with(['item', 'user'])->latest('entry_date');
                $this->applyDateRange($query, 'entry_date', $startDate, $endDate);
                if ($department) {
                    $query->whereHas('item', fn ($q) => $q->where('department', $department));
                }
                break;

            case 'outgoing':
                $query = OutgoingItem::with(['item', 'user'])->latest('exit_date');
                $this->applyDateRange($query, 'exit_date', $startDate, $endDate);
                if ($department) {
                    $query->whereHas('item', fn ($q) => $q->where('department', $department));
                }
                break;

            case 'distribution':
                $query = Distribution::with(['item', 'toLocation', 'user'])->latest('distribution_date');
                $this->applyDateRange($query, 'distribution_date', $startDate, $endDate);
                if ($department) {
                    $query->where('recipient_department', $department);
                }
                break;

            case 'submission':
                $query = Submission::with(['user', 'items'])->latest();
                $this->applyDateRange($query, 'created_at', $startDate, $endDate, true);
                if ($department) {
                    $query->where('department', $department);
                }
                if ($filters['status']) {
                    $query->where('status', $filters['status']);
                }
                break;

            case 'inventory':
            default:
                $query = Item::with(['location', 'assetUnits.location'])->orderBy('code')->orderBy('id');
                if ($department) {
                    $query->where('department', $department);
                }
                if ($filters['location_id']) {
                    $locationId = $filters['location_id'];
                    $query->where(fn ($q) => $q
                        ->where('location_id', $locationId)
                        ->orWhereHas('assetUnits', fn ($u) => $u->where('location_id', $locationId)));
                }
                if ($filters['condition']) {
                    $condition = $filters['condition'];
                    $query->where(fn ($q) => $q
                        ->where('current_condition', $condition)
                        ->orWhereHas('assetUnits', fn ($u) => $u->where('current_condition', $condition)));
                }
                if ($filters['unit_status']) {
                    $status = $filters['unit_status'];
                    $query->where(fn ($q) => $q
                        ->where('current_status', $status)
                        ->orWhereHas('assetUnits', fn ($u) => $u->where('current_status', $status)));
                }
                break;
        }

        return $query;
    }

    private function applyDateRange(Builder $query, string $column, ?string $startDate, ?string $endDate, bool $withTime = false): void
    {
        if (! $startDate && ! $endDate) {
            return;
        }

        $start = $startDate ? ($withTime ? $startDate.' 00:00:00' : $startDate) : null;
        $end = $endDate ? ($withTime ? $endDate.' 23:59:59' : $endDate) : null;

        if ($start && $end) {
            $query->whereBetween($column, [$start, $end]);
        } elseif ($start) {
            $query->where($column, '>=', $start);
        } else {
            $query->where($column, '<=', $end);
        }
    }

    /**
     * Kartu ringkasan melalui query agregat (bukan iterasi baris data).
     *
     * @return array<string,mixed>
     */
    private function summary(string $type, array $filters): array
    {
        if ($type === 'inventory') {
            return $this->inventorySummary($filters);
        }

        $base = $this->buildQuery($type, $filters);

        return [
            'transactions' => (clone $base)->count(),
            'quantity' => (int) (clone $base)->sum('quantity'),
        ];
    }

    /**
     * Agregat inventaris per unit: total, per kondisi, per status.
     *
     * @return array<string,mixed>
     */
    private function inventorySummary(array $filters): array
    {
        $itemIds = $this->buildQuery('inventory', $filters)->pluck('id');

        if ($itemIds->isEmpty()) {
            return ['rows' => 0, 'conditions' => [], 'statuses' => []];
        }

        $unitItemIds = Item::whereIn('id', $itemIds)
            ->where('item_type', 'individual')
            ->has('assetUnits')
            ->pluck('id');
        $plainItemIds = $itemIds->diff($unitItemIds);

        $conditionCounts = [];
        $statusCounts = [];
        $rows = 0;

        if ($unitItemIds->isNotEmpty()) {
            $units = AssetUnit::whereIn('item_id', $unitItemIds)
                ->selectRaw('current_condition, current_status, count(*) as total')
                ->groupBy('current_condition', 'current_status')
                ->get();

            foreach ($units as $group) {
                $count = (int) $group->total;
                $rows += $count;
                $conditionCounts[$group->current_condition] = ($conditionCounts[$group->current_condition] ?? 0) + $count;
                $statusCounts[$group->current_status] = ($statusCounts[$group->current_status] ?? 0) + $count;
            }
        }

        if ($plainItemIds->isNotEmpty()) {
            $items = Item::whereIn('id', $plainItemIds)
                ->selectRaw('current_condition, current_status, count(*) as total')
                ->groupBy('current_condition', 'current_status')
                ->get();

            foreach ($items as $group) {
                $count = (int) $group->total;
                $rows += $count;
                $conditionCounts[$group->current_condition] = ($conditionCounts[$group->current_condition] ?? 0) + $count;
                $statusCounts[$group->current_status] = ($statusCounts[$group->current_status] ?? 0) + $count;
            }
        }

        return [
            'rows' => $rows,
            'conditions' => $conditionCounts,
            'statuses' => $statusCounts,
        ];
    }

    /**
     * Baris ringkasan filter yang ditampilkan di layar, print, dan pdf.
     *
     * @param  array<string,mixed>  $summary
     */
    private function filterSummary(array $filters, array $summary, string $type): string
    {
        $period = ($filters['start_date'] && $filters['end_date'])
            ? $this->formatDate($filters['start_date']).' – '.$this->formatDate($filters['end_date'])
            : 'semua';

        $department = $filters['department']
            ? (Departments::label($filters['department']) ?? $filters['department'])
            : 'semua';

        $rows = $type === 'inventory' ? ($summary['rows'] ?? 0) : ($summary['transactions'] ?? 0);

        return 'Periode: '.$period
            .' · Jurusan: '.$department
            .' · '.$rows.' baris'
            .' · dibuat '.now()->translatedFormat('d M Y H:i');
    }

    private function formatDate(string $date): string
    {
        try {
            return Carbon::parse($date)->translatedFormat('d M Y');
        } catch (\Throwable) {
            return $date;
        }
    }

    /**
     * Preset rentang tanggal untuk membantu pengguna. Setiap preset berisi
     * label + query string yang siap dipasang ke link.
     *
     * @return array<int,array{label:string,query:array<string,string>}>
     */
    private function datePresets(array $filters): array
    {
        $today = now();

        $presets = [
            'Bulan ini' => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()],
            '30 hari terakhir' => [$today->copy()->subDays(29), $today->copy()],
            'Tahun ini' => [$today->copy()->startOfYear(), $today->copy()->endOfYear()],
        ];

        $result = [];
        foreach ($presets as $label => [$start, $end]) {
            $result[] = [
                'label' => $label,
                'query' => array_merge(request()->query(), [
                    'start_date' => $start->format('Y-m-d'),
                    'end_date' => $end->format('Y-m-d'),
                ]),
            ];
        }

        return $result;
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
            default => ['Kode Barang', 'No. Unit', 'No. Seri', 'Nama Barang', 'Kategori', 'Jumlah/Stok', 'Kondisi', 'Status Unit', 'Lokasi', 'Jurusan'],
        };
    }

    /**
     * Item individual dengan unit dipecah menjadi satu baris per unit;
     * consumable / item legacy tetap satu baris.
     */
    private function inventoryRows(Item $item): array
    {
        $asRow = function (?string $unitNo, ?string $serial, int $stock, string $condition, ?string $status, $location) use ($item) {
            return (object) [
                'code' => $item->code,
                'unit_no' => $unitNo,
                'serial_number' => $serial,
                'name' => $item->name,
                'category' => $item->category,
                'stock' => $stock,
                'unit' => $item->unit,
                'current_condition' => $condition,
                'current_status' => $status,
                'location' => $location,
                'department' => $item->department,
            ];
        };

        if ($item->isIndividual() && $item->assetUnits && $item->assetUnits->isNotEmpty()) {
            return $item->assetUnits
                ->map(fn ($unit) => $asRow(
                    $unit->unit_inventory_number,
                    $unit->serial_number,
                    1,
                    $unit->current_condition,
                    $unit->current_status,
                    $unit->location
                ))
                ->all();
        }

        return [$asRow(
            $item->inventory_number,
            $item->serial_number,
            (int) $item->stock,
            $item->current_condition,
            $item->current_status,
            $item->location
        )];
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
                Departments::label($record->department) ?? $record->department,
                $record->user->name ?? '-',
                Submission::statusLabel($record->status),
                $record->created_at->format('d/m/Y'),
            ],
            default => [
                $record->code,
                (string) ($record->unit_no ?? ''),
                (string) ($record->serial_number ?? ''),
                $record->name,
                $record->category,
                $record->stock.' '.$record->unit,
                ucfirst(str_replace('_', ' ', (string) $record->current_condition)),
                AssetUnit::STATUS_LABELS[$record->current_status] ?? ucfirst(str_replace('_', ' ', (string) $record->current_status)),
                $record->location->name ?? '-',
                Departments::label($record->department) ?? 'Umum',
            ],
        };
    }
}
