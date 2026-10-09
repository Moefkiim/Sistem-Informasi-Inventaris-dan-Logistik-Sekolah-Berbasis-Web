@extends('layouts.app')

@section('title', 'Laporan & Rekapitulasi')
@section('header-title', 'Laporan Inventaris & Logistik')

@php
    use Illuminate\Support\Arr;

    $q = request()->query();
    $isKajur = auth()->user()->isKajur();
    $showDateRange = $type !== 'inventory';
    $hasActiveFilters = (bool) (
        $filters['start_date'] || $filters['end_date'] || $filters['location_id']
        || $filters['condition'] || $filters['unit_status'] || $filters['status']
        || (! $isKajur && $filters['department'])
    );

    $conditionLabels = ['baik' => 'Baik', 'rusak_ringan' => 'Rusak Ringan', 'rusak_berat' => 'Rusak Berat'];
    $statusLabels = \App\Models\AssetUnit::STATUS_LABELS;
    $statusColors = \App\Models\AssetUnit::STATUS_BADGE_COLORS;
    $totalRows = $data instanceof \Illuminate\Pagination\LengthAwarePaginator ? $data->total() : $data->count();
@endphp

@push('styles')
<style>
    .report-table thead th { position: sticky; top: 0; z-index: 2; background-color: #f9fafb; box-shadow: inset 0 -1px 0 #e4e6ef; }
    .report-table tbody tr:hover { background-color: #f9fafb; }
    .filter-card .form-label { font-size: 0.8rem; }
    .empty-dash { color: #b5b5c3; }
</style>
@endpush

@section('content')
<x-page-header
    title="Laporan & Rekapitulasi"
    description="Susun laporan inventaris dan logistik, lalu ekspor ke PDF, Excel, atau cetak." />

{{-- Kartu Filter --}}
<div class="card border-0 shadow-sm mb-5 filter-card">
    <div class="card-body p-5 p-lg-7">
        <form action="{{ route('reports.index') }}" method="GET" class="row g-3 align-items-end" data-disable-on-submit data-ui-filter>
            <div class="col-12 col-md-6 col-lg-3">
                <label class="form-label fw-bold" for="filter-type">Jenis Laporan</label>
                <select id="filter-type" name="type" class="form-select form-select-solid" onchange="this.form.submit()">
                    @foreach(\App\Support\ReportTypes::titles() as $value => $label)
                        <option value="{{ $value }}" {{ $type === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            @if($showDateRange)
                <div class="col-12 col-md-6 col-lg-4">
                    <label class="form-label fw-bold" for="filter-start">Rentang Tanggal</label>
                    <div class="input-group">
                        <input type="date" id="filter-start" name="start_date" class="form-control form-control-solid" value="{{ $filters['start_date'] }}">
                        <span class="input-group-text bg-light border-0">s/d</span>
                        <input type="date" name="end_date" class="form-control form-control-solid" value="{{ $filters['end_date'] }}" aria-label="Tanggal akhir">
                    </div>
                    <div class="d-flex flex-wrap gap-1 mt-2">
                        @foreach($datePresets as $preset)
                            <a href="{{ route('reports.index', $preset['query']) }}" class="btn btn-sm btn-light-primary py-1 px-2">{{ $preset['label'] }}</a>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="col-12 col-md-6 col-lg-3">
                <label class="form-label fw-bold" for="filter-department">Filter Jurusan</label>
                @if($isKajur)
                    <div class="d-flex align-items-center gap-2 form-control form-control-solid bg-light">
                        <i class="bi bi-lock-fill text-muted"></i>
                        <span class="fw-bold">Jurusan: {{ $departmentLabel ?? $department }}</span>
                    </div>
                @else
                    <select id="filter-department" name="department" class="form-select form-select-solid">
                        <option value="">Semua Jurusan</option>
                        @foreach(config('departments') as $code => $name)
                            <option value="{{ $code }}" {{ $filters['department'] === $code ? 'selected' : '' }}>{{ $name }} ({{ $code }})</option>
                        @endforeach
                    </select>
                @endif
            </div>

            @if($type === 'inventory')
                <div class="col-12 col-md-4 col-lg-3">
                    <label class="form-label fw-bold" for="filter-location">Lokasi</label>
                    <select id="filter-location" name="location_id" class="form-select form-select-solid">
                        <option value="">Semua Lokasi</option>
                        @foreach($locations as $location)
                            <option value="{{ $location->id }}" {{ (string) $filters['location_id'] === (string) $location->id ? 'selected' : '' }}>{{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-4 col-lg-3">
                    <label class="form-label fw-bold" for="filter-condition">Kondisi</label>
                    <select id="filter-condition" name="condition" class="form-select form-select-solid">
                        <option value="">Semua Kondisi</option>
                        @foreach($conditionLabels as $value => $label)
                            <option value="{{ $value }}" {{ $filters['condition'] === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-4 col-lg-3">
                    <label class="form-label fw-bold" for="filter-unit-status">Status Unit</label>
                    <select id="filter-unit-status" name="unit_status" class="form-select form-select-solid">
                        <option value="">Semua Status</option>
                        @foreach($statusLabels as $value => $label)
                            <option value="{{ $value }}" {{ $filters['unit_status'] === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            @if($type === 'submission')
                <div class="col-12 col-md-4 col-lg-3">
                    <label class="form-label fw-bold" for="filter-status">Status Pengajuan</label>
                    <select id="filter-status" name="status" class="form-select form-select-solid">
                        <option value="">Semua Status</option>
                        @foreach(['draft' => 'Draft', 'submitted' => 'Diajukan', 'reviewed_sarpras' => 'Diverifikasi Sarpras', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'cancelled' => 'Dibatalkan'] as $value => $label)
                            <option value="{{ $value }}" {{ $filters['status'] === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="col-12 col-lg-auto ms-lg-auto">
                <div class="d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-funnel me-1"></i> Filter
                    </button>
                    <a href="{{ route('reports.index', ['type' => $type]) }}" class="btn btn-light">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Setel Ulang
                    </a>
                    <div class="dropdown">
                        <button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-download me-1"></i> Ekspor
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a class="dropdown-item" href="{{ route('reports.pdf', $q) }}">
                                    <i class="bi bi-file-earmark-pdf me-2 text-danger"></i> Unduh PDF
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="{{ route('reports.excel', $q) }}">
                                    <i class="bi bi-file-earmark-excel me-2 text-success"></i> Unduh Excel
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="{{ route('reports.index', array_merge($q, ['export' => 'print'])) }}" target="_blank" rel="noopener">
                                    <i class="bi bi-printer me-2 text-dark"></i> Cetak
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </form>

        {{-- Chip filter aktif --}}
        <div class="d-flex flex-wrap align-items-center gap-2 mt-4">
            <span class="text-muted fs-7 fw-bold me-1">Filter aktif:</span>
            @php $anyChip = false; @endphp

            @if(! $isKajur && $filters['department'])
                @php $anyChip = true; @endphp
                <x-filter-chip label="Jurusan" :value="$departmentLabel ?? $filters['department']" :remove-url="route('reports.index', array_merge(Arr::except($q, ['department', 'page']), ['type' => $type]))" />
            @endif
            @if($filters['start_date'] || $filters['end_date'])
                @php $anyChip = true; @endphp
                <x-filter-chip label="Periode" :value="($filters['start_date'] ?: '…').' s/d '.($filters['end_date'] ?: '…')" :remove-url="route('reports.index', array_merge(Arr::except($q, ['start_date', 'end_date', 'page']), ['type' => $type]))" />
            @endif
            @if($filters['location_id'])
                @php $anyChip = true; @endphp
                <x-filter-chip label="Lokasi" :value="$locations->firstWhere('id', $filters['location_id'])?->name ?? '—'" :remove-url="route('reports.index', array_merge(Arr::except($q, ['location_id', 'page']), ['type' => $type]))" />
            @endif
            @if($filters['condition'])
                @php $anyChip = true; @endphp
                <x-filter-chip label="Kondisi" :value="$conditionLabels[$filters['condition']] ?? $filters['condition']" :remove-url="route('reports.index', array_merge(Arr::except($q, ['condition', 'page']), ['type' => $type]))" />
            @endif
            @if($filters['unit_status'])
                @php $anyChip = true; @endphp
                <x-filter-chip label="Status Unit" :value="$statusLabels[$filters['unit_status']] ?? $filters['unit_status']" :remove-url="route('reports.index', array_merge(Arr::except($q, ['unit_status', 'page']), ['type' => $type]))" />
            @endif
            @if($filters['status'])
                @php $anyChip = true; @endphp
                <x-filter-chip label="Status" :value="\App\Models\Submission::statusLabel($filters['status'])" :remove-url="route('reports.index', array_merge(Arr::except($q, ['status', 'page']), ['type' => $type]))" />
            @endif

            @unless($anyChip)
                <span class="text-muted fs-7">Tidak ada filter tambahan</span>
            @endunless
        </div>
    </div>
</div>

{{-- Kartu Hasil --}}
<div class="card border-0 shadow-sm">
    <div class="card-header border-0 pt-6 pb-0">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
            <div>
                <h3 class="fw-bolder mb-1">{{ $reportTitle }}</h3>
                <p class="text-muted fs-7 mb-0">{{ $filterSummary }}</p>
            </div>
        </div>
    </div>
    <div class="card-body pt-5">
        {{-- Kartu ringkasan --}}
        <div class="row g-4 mb-6">
            @if($type === 'inventory')
                <div class="col-6 col-lg-3">
                    <div class="border border-gray-300 border-dashed rounded p-4 h-100">
                        <div class="text-muted fw-bold fs-7 text-uppercase">Total Unit</div>
                        <div class="fs-2 fw-bolder">{{ number_format($summary['rows'] ?? 0, 0, ',', '.') }}</div>
                    </div>
                </div>
                @foreach($conditionLabels as $value => $label)
                    <div class="col-6 col-lg-3">
                        <div class="border border-gray-300 border-dashed rounded p-4 h-100">
                            <div class="text-muted fw-bold fs-7 text-uppercase">{{ $label }}</div>
                            <div class="fs-2 fw-bolder">{{ number_format($summary['conditions'][$value] ?? 0, 0, ',', '.') }}</div>
                        </div>
                    </div>
                @endforeach
                @foreach($statusLabels as $value => $label)
                    <div class="col-6 col-lg-3">
                        <div class="border border-gray-300 border-dashed rounded p-4 h-100">
                            <div class="text-muted fw-bold fs-7 text-uppercase">{{ $label }}</div>
                            <div class="fs-2 fw-bolder text-{{ $statusColors[$value] ?? 'dark' }}">{{ number_format($summary['statuses'][$value] ?? 0, 0, ',', '.') }}</div>
                        </div>
                    </div>
                @endforeach
            @else
                <div class="col-6 col-lg-3">
                    <div class="border border-gray-300 border-dashed rounded p-4 h-100">
                        <div class="text-muted fw-bold fs-7 text-uppercase">Jumlah Transaksi</div>
                        <div class="fs-2 fw-bolder">{{ number_format($summary['transactions'] ?? 0, 0, ',', '.') }}</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="border border-gray-300 border-dashed rounded p-4 h-100">
                        <div class="text-muted fw-bold fs-7 text-uppercase">Total Kuantitas</div>
                        <div class="fs-2 fw-bolder">{{ number_format($summary['quantity'] ?? 0, 0, ',', '.') }}</div>
                    </div>
                </div>
            @endif
        </div>

        @if($totalRows === 0)
            @if($hasActiveFilters)
                <x-empty-state
                    icon="bi-funnel"
                    title="Tidak ada hasil untuk filter ini"
                    description="Coba longgarkan filter atau setel ulang untuk melihat seluruh data.">
                    <a href="{{ route('reports.index', ['type' => $type]) }}" class="btn btn-primary">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Setel Ulang Filter
                    </a>
                </x-empty-state>
            @else
                <x-empty-state
                    icon="bi-inbox"
                    title="Belum ada data"
                    description="Data akan muncul di sini setelah inventaris atau transaksi dicatat." />
            @endif
        @else
            <div class="table-responsive">
                @if($type === 'inventory')
                    <table class="table table-hover table-row-dashed align-middle report-table fs-7 gy-3">
                        <thead class="fw-bold text-muted text-uppercase">
                            <tr>
                                <th>Kode Barang</th>
                                <th>No. Unit</th>
                                <th>No. Seri</th>
                                <th>Nama Barang</th>
                                <th>Kategori</th>
                                <th class="text-end">Jumlah/Stok</th>
                                <th>Kondisi</th>
                                <th>Status Unit</th>
                                <th>Lokasi</th>
                                <th>Jurusan</th>
                            </tr>
                        </thead>
                        <tbody class="fw-semibold text-gray-700">
                            @include('reports.partials.inventory_rows')
                        </tbody>
                    </table>
                @elseif($type === 'incoming')
                    <table class="table table-hover table-row-dashed align-middle report-table fs-7 gy-3">
                        <thead class="fw-bold text-muted text-uppercase">
                            <tr><th>No. Transaksi</th><th>Nama Barang</th><th class="text-end">Jumlah Masuk</th><th>Asal Sumber</th><th>Tanggal</th><th>Pencatat</th></tr>
                        </thead>
                        <tbody class="fw-semibold text-gray-700">
                            @foreach($data as $row)
                                <tr>
                                    <td>{{ $row->transaction_number }}</td>
                                    <td>{{ $row->item->name ?? '—' }}</td>
                                    <td class="text-end"><span class="badge badge-light-success">+{{ $row->quantity }} {{ $row->item->unit ?? '' }}</span></td>
                                    <td>{{ $row->source_origin ?: $row->source }}</td>
                                    <td>{{ $row->entry_date->format('d/m/Y') }}</td>
                                    <td>{{ $row->user->name ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @elseif($type === 'outgoing')
                    <table class="table table-hover table-row-dashed align-middle report-table fs-7 gy-3">
                        <thead class="fw-bold text-muted text-uppercase">
                            <tr><th>No. Transaksi</th><th>Nama Barang</th><th class="text-end">Jumlah Keluar</th><th>Alasan</th><th>Tanggal</th><th>Pencatat</th></tr>
                        </thead>
                        <tbody class="fw-semibold text-gray-700">
                            @foreach($data as $row)
                                <tr>
                                    <td>{{ $row->transaction_number }}</td>
                                    <td>{{ $row->item->name ?? '—' }}</td>
                                    <td class="text-end"><span class="badge badge-light-danger">-{{ $row->quantity }} {{ $row->item->unit ?? '' }}</span></td>
                                    <td>{{ $row->reason }}</td>
                                    <td>{{ $row->exit_date->format('d/m/Y') }}</td>
                                    <td>{{ $row->user->name ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @elseif($type === 'distribution')
                    <table class="table table-hover table-row-dashed align-middle report-table fs-7 gy-3">
                        <thead class="fw-bold text-muted text-uppercase">
                            <tr><th>No. Distribusi</th><th>Barang</th><th class="text-end">Jumlah</th><th>Lokasi Penempatan</th><th>Penerima</th><th>Tanggal</th></tr>
                        </thead>
                        <tbody class="fw-semibold text-gray-700">
                            @foreach($data as $row)
                                <tr>
                                    <td>{{ $row->distribution_number }}</td>
                                    <td>{{ $row->item->name ?? '—' }}</td>
                                    <td class="text-end">{{ $row->quantity }} {{ $row->item->unit ?? '' }}</td>
                                    <td>{{ $row->toLocation->name ?? '—' }}</td>
                                    <td>{{ $row->recipient_name ?: ($row->recipient_department ? \App\Support\Departments::display($row->recipient_department) : '—') }}</td>
                                    <td>{{ $row->distribution_date->format('d/m/Y') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @elseif($type === 'submission')
                    <table class="table table-hover table-row-dashed align-middle report-table fs-7 gy-3">
                        <thead class="fw-bold text-muted text-uppercase">
                            <tr><th>No. Pengajuan</th><th>Pengaju</th><th>Jurusan</th><th>Judul</th><th>Status</th><th>Tanggal</th></tr>
                        </thead>
                        <tbody class="fw-semibold text-gray-700">
                            @foreach($data as $row)
                                <tr>
                                    <td>{{ $row->submission_number }}</td>
                                    <td>{{ $row->user->name ?? '—' }}</td>
                                    <td>{{ \App\Support\Departments::display($row->department) }}</td>
                                    <td>{{ $row->title }}</td>
                                    <td><x-submission-status-badge :status="$row->status" /></td>
                                    <td>{{ $row->created_at->format('d/m/Y H:i') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            @if($data instanceof \Illuminate\Pagination\LengthAwarePaginator)
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-5">
                    <div class="text-muted fs-7">
                        Menampilkan {{ $data->firstItem() }}–{{ $data->lastItem() }} dari {{ $data->total() }}
                    </div>
                    <form method="GET" action="{{ route('reports.index') }}" class="d-flex align-items-center gap-2">
                        @foreach(Arr::except($q, ['per_page', 'page']) as $key => $value)
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endforeach
                        <label class="text-muted fs-7 mb-0" for="per-page">Baris per halaman</label>
                        <select id="per-page" name="per_page" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                            @foreach($perPageOptions as $option)
                                <option value="{{ $option }}" {{ $perPage === $option ? 'selected' : '' }}>{{ $option }}</option>
                            @endforeach
                        </select>
                    </form>
                    <div>{{ $data->links() }}</div>
                </div>
            @endif
        @endif
    </div>
</div>
@endsection
