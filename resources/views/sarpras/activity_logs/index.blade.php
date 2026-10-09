@extends('layouts.app')

@section('title', 'Riwayat Aktivitas')
@section('header-title', 'Audit Trail')

@php
    use Illuminate\Support\Arr;
    use App\Models\ActivityLog;

    $q = request()->query();
    $months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $formatTime = function ($date) use ($months) {
        return $date ? $date->format('d').' '.$months[$date->month - 1].' '.$date->format('Y H:i') : '—';
    };
    $roleLabels = ['kajur' => 'Kajur', 'sarpras' => 'Sarpras', 'kepala_sekolah' => 'Kepala Sekolah'];
    $roleColors = ['kajur' => 'info', 'sarpras' => 'primary', 'kepala_sekolah' => 'dark'];

    $today = now()->toDateString();
    $datePresets = [
        ['label' => 'Hari Ini', 'query' => array_merge($q, ['start_date' => $today, 'end_date' => $today, 'page' => null])],
        ['label' => '7 Hari', 'query' => array_merge($q, ['start_date' => now()->subDays(6)->toDateString(), 'end_date' => $today, 'page' => null])],
        ['label' => '30 Hari', 'query' => array_merge($q, ['start_date' => now()->subDays(29)->toDateString(), 'end_date' => $today, 'page' => null])],
        ['label' => 'Bulan Ini', 'query' => array_merge($q, ['start_date' => now()->startOfMonth()->toDateString(), 'end_date' => $today, 'page' => null])],
    ];
@endphp

@push('styles')
<style>
    .log-table thead th { position: sticky; top: 0; z-index: 2; background-color: #f9fafb; box-shadow: inset 0 -1px 0 #e4e6ef; }
    .log-table tbody tr:hover { background-color: #f9fafb; }
    .change-row { display: flex; flex-wrap: wrap; gap: .25rem .5rem; padding: .5rem .75rem; border-radius: .475rem; background: #f9fafb; }
    .change-field { font-weight: 700; }
    .change-before { color: #b5b5c3; text-decoration: line-through; }
</style>
@endpush

@section('content')
<x-page-header
    title="Riwayat Aktivitas"
    description="Jejak audit perubahan data inventaris, pengajuan, dan peminjaman." />

<div class="alert alert-dismissible bg-light-primary border border-primary border-dashed d-flex align-items-center p-5 mb-5">
    <i class="bi bi-shield-lock-fill fs-2 text-primary me-4"></i>
    <div class="fs-7 text-gray-700">
        Catatan ini bersifat <strong>append-only</strong> — hanya dapat dibaca, tidak dapat diubah atau dihapus melalui antarmuka.
    </div>
</div>

{{-- Kartu Filter --}}
<div class="card border-0 shadow-sm mb-5">
    <div class="card-body p-5 p-lg-7">
        <form action="{{ route('sarpras.activity_logs.index') }}" method="GET" class="row g-3 align-items-end" data-disable-on-submit data-ui-filter>
            <div class="col-12 col-lg-4">
                <label class="form-label fw-bold" for="log-search">Pencarian</label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" id="log-search" name="search" class="form-control form-control-solid border-start-0"
                           placeholder="Nama pengguna, keterangan, atau label data"
                           value="{{ request('search') }}">
                </div>
            </div>

            <div class="col-12 col-md-6 col-lg-3">
                <label class="form-label fw-bold" for="log-action">Jenis Aktivitas</label>
                <select id="log-action" name="action" class="form-select form-select-solid">
                    <option value="">Semua jenis</option>
                    @foreach($actionGroups as $group => $options)
                        <optgroup label="{{ $group }}">
                            @foreach($options as $action => $label)
                                <option value="{{ $action }}" @selected(request('action') === $action)>{{ $label }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>

            <div class="col-12 col-md-6 col-lg-3">
                <label class="form-label fw-bold" for="log-start">Rentang Tanggal</label>
                <div class="input-group">
                    <input type="date" id="log-start" name="start_date" class="form-control form-control-solid" value="{{ request('start_date') }}">
                    <span class="input-group-text bg-light border-0">s/d</span>
                    <input type="date" name="end_date" class="form-control form-control-solid" value="{{ request('end_date') }}" aria-label="Tanggal akhir">
                </div>
                <div class="d-flex flex-wrap gap-1 mt-2">
                    @foreach($datePresets as $preset)
                        <a href="{{ route('sarpras.activity_logs.index', Arr::except($preset['query'], ['page'])) }}" class="btn btn-sm btn-light-primary py-1 px-2">{{ $preset['label'] }}</a>
                    @endforeach
                </div>
            </div>

            <div class="col-12 col-lg-2">
                <div class="d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">
                        <i class="bi bi-funnel me-1"></i> Terapkan
                    </button>
                    <a href="{{ route('sarpras.activity_logs.index') }}" class="btn btn-light flex-grow-1">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Setel Ulang
                    </a>
                </div>
            </div>
        </form>

        {{-- Chip filter aktif --}}
        <div class="d-flex flex-wrap align-items-center gap-2 mt-4">
            <span class="text-muted fs-7 fw-bold me-1">Filter aktif:</span>
            @php $anyChip = false; @endphp
            @if(request('search'))
                @php $anyChip = true; @endphp
                <x-filter-chip label="Pencarian" :value="request('search')" :remove-url="route('sarpras.activity_logs.index', Arr::except($q, ['search', 'page']))" />
            @endif
            @if(request('action'))
                @php $anyChip = true; @endphp
                <x-filter-chip label="Jenis" :value="ActivityLog::actionLabel(request('action'))" :remove-url="route('sarpras.activity_logs.index', Arr::except($q, ['action', 'page']))" />
            @endif
            @if(request('start_date') || request('end_date'))
                @php $anyChip = true; @endphp
                <x-filter-chip label="Periode" :value="(request('start_date') ?: '…').' s/d '.(request('end_date') ?: '…')" :remove-url="route('sarpras.activity_logs.index', Arr::except($q, ['start_date', 'end_date', 'page']))" />
            @endif
            @unless($anyChip)
                <span class="text-muted fs-7">Tidak ada filter tambahan</span>
            @endunless
        </div>
    </div>
</div>

{{-- Kartu Hasil --}}
<div class="card border-0 shadow-sm">
    <div class="card-body p-5 p-lg-7">
        @if($logs->total() === 0)
            @if($hasFilters)
                <x-empty-state
                    icon="bi-funnel"
                    title="Tidak ada hasil untuk filter ini"
                    description="Coba longgarkan filter atau setel ulang untuk melihat seluruh aktivitas.">
                    <a href="{{ route('sarpras.activity_logs.index') }}" class="btn btn-primary">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Setel Ulang Filter
                    </a>
                </x-empty-state>
            @else
                <x-empty-state
                    icon="bi-clipboard-check"
                    title="Belum ada aktivitas"
                    description="Aktivitas akan tercatat otomatis saat ada perubahan data inventaris, pengajuan, atau peminjaman." />
            @endif
        @else
            <div class="table-responsive">
                <table class="table table-hover table-row-dashed align-middle log-table fs-7 gy-3">
                    <thead class="fw-bold text-muted text-uppercase">
                        <tr>
                            <th style="min-width: 150px;">Waktu</th>
                            <th style="min-width: 160px;">Jenis</th>
                            <th style="min-width: 150px;">Pengguna</th>
                            <th>Keterangan</th>
                            <th style="min-width: 130px;">Data Terkait</th>
                            <th class="text-end">Perubahan</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold text-gray-700">
                        @foreach($logs as $log)
                            @php $relatedUrl = $log->relatedUrl(); @endphp
                            <tr>
                                <td class="text-gray-600">
                                    <span title="{{ $log->logged_at?->diffForHumans() }}">{{ $formatTime($log->logged_at) }}</span>
                                </td>
                                <td>
                                    <span class="badge badge-light-{{ ActivityLog::actionColor($log->action) }}">
                                        {{ ActivityLog::actionLabel($log->action) }}
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-bold text-gray-800">{{ $log->user_name ?? 'Sistem' }}</div>
                                    @if($log->user_role)
                                        <span class="badge badge-light-{{ $roleColors[$log->user_role] ?? 'secondary' }} mt-1">
                                            {{ $roleLabels[$log->user_role] ?? ucfirst($log->user_role) }}
                                        </span>
                                    @endif
                                </td>
                                <td>{{ $log->description }}</td>
                                <td>
                                    @if($log->auditable_label)
                                        @if($relatedUrl)
                                            <a href="{{ $relatedUrl }}" class="text-primary text-hover-primary fw-bold">{{ $log->auditable_label }}</a>
                                        @else
                                            <span class="fw-bold text-gray-700">{{ $log->auditable_label }}</span>
                                        @endif
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if($log->old_values || $log->new_values)
                                        <button class="btn btn-sm btn-light-primary" type="button" data-bs-toggle="modal"
                                                data-bs-target="#log-detail-{{ $log->id }}"
                                                title="Lihat perubahan data">
                                            <i class="bi bi-eye me-1"></i> Detail
                                        </button>
                                    @else
                                        <span class="text-muted fs-8">Tanpa perubahan</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-5">
                <div class="text-muted fs-7">
                    Menampilkan {{ $logs->firstItem() }}–{{ $logs->lastItem() }} dari {{ $logs->total() }} aktivitas
                </div>
                <form method="GET" action="{{ route('sarpras.activity_logs.index') }}" class="d-flex align-items-center gap-2">
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
                <div>{{ $logs->links() }}</div>
            </div>
        @endif
    </div>
</div>

{{-- Modal detail perubahan --}}
@foreach($logs as $log)
    @if($log->old_values || $log->new_values)
        @php
            $old = $log->old_values ?? [];
            $new = $log->new_values ?? [];
            $fields = $log->changedFields();
        @endphp
        <div class="modal fade" id="log-detail-{{ $log->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title mb-1">Detail Perubahan</h5>
                            <span class="badge badge-light-{{ ActivityLog::actionColor($log->action) }}">
                                {{ ActivityLog::actionLabel($log->action) }}
                            </span>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <dl class="row mb-4 fs-7">
                            <dt class="col-sm-3 text-muted">Waktu</dt>
                            <dd class="col-sm-9 fw-bold">{{ $formatTime($log->logged_at) }} <span class="text-muted fw-normal">({{ $log->logged_at?->diffForHumans() }})</span></dd>

                            <dt class="col-sm-3 text-muted">Pengguna</dt>
                            <dd class="col-sm-9 fw-bold">
                                {{ $log->user_name ?? 'Sistem' }}
                                @if($log->user_role)
                                    <span class="text-muted fw-normal">({{ $roleLabels[$log->user_role] ?? $log->user_role }})</span>
                                @endif
                            </dd>

                            <dt class="col-sm-3 text-muted">Keterangan</dt>
                            <dd class="col-sm-9">{{ $log->description }}</dd>
                        </dl>

                        <h6 class="fw-bolder text-muted fs-7 text-uppercase mb-3">Perubahan Kolom</h6>
                        @forelse($fields as $field)
                            <div class="change-row mb-2 fs-7">
                                <span class="change-field">{{ ActivityLog::fieldLabel($field) }}:</span>
                                <span class="change-before">{{ ActivityLog::displayValue($field, $old[$field] ?? null) }}</span>
                                <i class="bi bi-arrow-right text-muted"></i>
                                <span class="text-gray-800 fw-bold">{{ ActivityLog::displayValue($field, $new[$field] ?? null) }}</span>
                            </div>
                        @empty
                            <p class="text-muted fs-7 mb-0">Tidak ada kolom yang berubah.</p>
                        @endforelse

                        <div class="mt-5">
                            <button class="btn btn-sm btn-light" type="button" data-bs-toggle="collapse" data-bs-target="#log-raw-{{ $log->id }}" aria-expanded="false">
                                <i class="bi bi-code-slash me-1"></i> Lihat data mentah
                            </button>
                            <div class="collapse mt-3" id="log-raw-{{ $log->id }}">
                                <pre class="bg-light p-3 rounded fs-8 mb-0 text-wrap">Sebelum: {{ json_encode($old, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}

Sesudah: {{ json_encode($new, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endforeach
@endsection
