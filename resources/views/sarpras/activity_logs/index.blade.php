@extends('layouts.app')

@section('title', 'Riwayat Aktivitas')
@section('header-title', 'Audit Trail')

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-header border-0 pt-6">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h3 class="fw-bolder mb-1">Riwayat Aktivitas</h3>
                <p class="text-muted mb-0">
                    Log perubahan data inventaris, pengajuan, dan peminjaman. Data bersifat append-only
                    dan tidak dapat dihapus atau diedit melalui antarmuka ini.
                </p>
            </div>
        </div>
    </div>

    <div class="card-body pt-0">
        {{-- Pencarian & filter --}}
        <form method="GET" action="{{ route('sarpras.activity_logs.index') }}" class="mb-6">
            <div class="row g-3 align-items-end">
                <div class="col-lg-4">
                    <label class="form-label text-muted fs-7 fw-bold" for="log-search">Pencarian</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0">
                            <i class="fas fa-search text-muted"></i>
                        </span>
                        <input type="text" id="log-search" name="search" class="form-control"
                               placeholder="Nama pengguna, keterangan, atau label data"
                               value="{{ request('search') }}">
                    </div>
                </div>

                <div class="col-lg-3">
                    <label class="form-label text-muted fs-7 fw-bold" for="log-action">Jenis Aktivitas</label>
                    <select id="log-action" name="action" class="form-select">
                        <option value="">Semua jenis</option>
                        @foreach($actions as $action)
                            <option value="{{ $action }}" @selected(request('action') === $action)>
                                {{ $action }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-lg-2">
                    <label class="form-label text-muted fs-7 fw-bold" for="log-start">Dari Tanggal</label>
                    <input type="date" id="log-start" name="start_date" class="form-control"
                           value="{{ request('start_date') }}">
                </div>

                <div class="col-lg-2">
                    <label class="form-label text-muted fs-7 fw-bold" for="log-end">Sampai Tanggal</label>
                    <input type="date" id="log-end" name="end_date" class="form-control"
                           value="{{ request('end_date') }}">
                </div>

                <div class="col-lg-1 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">
                        <i class="fas fa-filter me-1"></i>
                    </button>
                    <a href="{{ route('sarpras.activity_logs.index') }}" class="btn btn-light flex-grow-1">
                        <i class="fas fa-rotate-left"></i>
                    </a>
                </div>
            </div>
        </form>

        @if($logs->isEmpty())
            <div class="text-center py-10">
                <i class="fas fa-clipboard-list fs-1 text-muted mb-3"></i>
                <p class="text-muted mb-0">Belum ada aktivitas yang tercatat.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th class="w-150px">Waktu</th>
                            <th class="w-120px">Jenis</th>
                            <th>Pengguna</th>
                            <th>Keterangan</th>
                            <th>Data Terkait</th>
                            <th class="text-end w-100px">Perubahan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($logs as $log)
                            <tr>
                                <td class="text-muted fs-7">
                                    {{ $log->logged_at?->format('d M Y H:i:s') ?? '-' }}
                                </td>
                                <td><span class="badge badge-light text-uppercase">{{ $log->action }}</span></td>
                                <td>
                                    <div class="fs-7 fw-bold">{{ $log->user_name ?? '-' }}</div>
                                    <div class="text-muted fs-7">{{ $log->user_role ?? '-' }}</div>
                                </td>
                                <td class="fs-7">{{ $log->description }}</td>
                                <td class="fs-7">
                                    @if($log->auditable_label)
                                        {{ $log->auditable_label }}
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-light" type="button" data-bs-toggle="modal"
                                            data-bs-target="#log-detail-{{ $log->id }}"
                                            title="Lihat nilai sebelum dan sesudah">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-4">
                <div class="text-muted fs-7">
                    Menampilkan {{ $logs->firstItem() }} - {{ $logs->lastItem() }} dari {{ $logs->total() }} aktivitas
                </div>
                {{ $logs->links() }}
            </div>
        @endif
    </div>
</div>

{{-- Modal detail perubahan --}}
@foreach($logs as $log)
    <div class="modal fade" id="log-detail-{{ $log->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detail Aktivitas</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-3">Waktu</dt>
                        <dd class="col-sm-9">{{ $log->logged_at?->format('d F Y H:i:s') ?? '-' }}</dd>

                        <dt class="col-sm-3">Jenis</dt>
                        <dd class="col-sm-9">{{ $log->action }}</dd>

                        <dt class="col-sm-3">Pengguna</dt>
                        <dd class="col-sm-9">
                            {{ $log->user_name ?? '-' }}
                            @if($log->user_role)
                                <span class="text-muted">({{ $log->user_role }})</span>
                            @endif
                        </dd>

                        <dt class="col-sm-3">IP</dt>
                        <dd class="col-sm-9">{{ $log->ip_address ?? '-' }}</dd>

                        <dt class="col-sm-3">Keterangan</dt>
                        <dd class="col-sm-9">{{ $log->description }}</dd>
                    </dl>

                    <div class="row g-4 mt-2">
                        <div class="col-md-6">
                            <h6 class="fw-bolder text-muted fs-7 text-uppercase">Sebelum</h6>
                            <pre class="bg-light p-3 rounded fs-7 mb-0">{{ $log->old_values ? json_encode($log->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '—' }}</pre>
                        </div>
                        <div class="col-md-6">
                            <h6 class="fw-bolder text-muted fs-7 text-uppercase">Sesudah</h6>
                            <pre class="bg-light p-3 rounded fs-7 mb-0">{{ $log->new_values ? json_encode($log->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '—' }}</pre>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>
@endforeach
@endsection