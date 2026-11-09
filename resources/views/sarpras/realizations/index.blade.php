@extends('layouts.app')

@section('title', 'Siap Direalisasi - Sarpras')
@section('header-title', 'Pengajuan Siap Direalisasi')

@section('content')
<x-page-header
    title="Realisasi Pengajuan yang Telah Disetujui"
    description="Pengajuan berstatus Disetujui yang menunggu dicatat sebagai Barang Masuk." />

@if(session('success'))
    <div class="alert alert-success d-flex align-items-center p-4 mb-5">
        <i class="bi bi-check-circle fs-2 text-success me-3"></i>
        <div>{{ session('success') }}</div>
    </div>
@endif

<div class="card border-0 shadow-sm">
    <div class="card-body pt-6">
        {{-- Toolbar Pencarian & Filter --}}
        <form action="{{ route('sarpras.realizations.index') }}" method="GET" class="card bg-light p-5 border-0 mb-6">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label fs-7 fw-bold text-gray-700">Pencarian</label>
                    <div class="position-relative">
                        <i class="bi bi-search position-absolute top-50 translate-middle-y ms-3 text-gray-500"></i>
                        <input type="text" name="search" class="form-control form-control-solid ps-10 form-control-sm" placeholder="No. Permohonan, judul, nama..." value="{{ $search }}">
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label fs-7 fw-bold text-gray-700">Jurusan</label>
                    <select name="department" class="form-select form-select-solid form-select-sm">
                        <option value="">Semua Jurusan</option>
                        @foreach(config('departments') as $code => $name)
                            <option value="{{ $code }}" {{ $department === $code ? 'selected' : '' }}>{{ $code }} - {{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fs-7 fw-bold text-gray-700">Rentang Tanggal Disetujui</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-0"><i class="bi bi-calendar-range text-gray-500"></i></span>
                        <input type="text" class="form-control form-control-solid js-daterangepicker" placeholder="Semua tanggal" value="{{ $startDate && $endDate ? date('d/m/Y', strtotime($startDate)) . ' - ' . date('d/m/Y', strtotime($endDate)) : '' }}" readonly>
                        <input type="hidden" name="start_date" value="{{ $startDate }}">
                        <input type="hidden" name="end_date" value="{{ $endDate }}">
                    </div>
                </div>
                <div class="col-12 d-flex justify-content-end gap-2 mt-2">
                    @if($search || $department || $startDate || $endDate)
                        <a href="{{ route('sarpras.realizations.index') }}" class="btn btn-sm btn-light">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Filter
                        </a>
                    @endif
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-filter me-1"></i> Terapkan Filter
                    </button>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5">
                <thead>
                    <tr class="text-start text-muted fw-bolder fs-7 text-uppercase gs-0">
                        <th>No. Permohonan</th>
                        <th>Pengaju (Kajur)</th>
                        <th>Jurusan</th>
                        <th>Judul Kebutuhan</th>
                        <th>Jumlah Baris</th>
                        <th>Disetujui Pada</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody class="text-gray-600 fw-bold">
                    @forelse($submissions as $sub)
                        <tr>
                            <td><span class="text-dark fw-bolder">{{ $sub->submission_number }}</span></td>
                            <td>{{ $sub->user->name }}</td>
                            <td><span class="badge badge-light-primary">{{ $sub->department }}</span></td>
                            <td>{{ $sub->title }}</td>
                            <td>{{ $sub->items_count ?? $sub->items()->count() }}</td>
                            <td>{{ $sub->decided_at?->format('d/m/Y H:i') ?? '-' }}</td>
                            <td class="text-end">
                                <a href="{{ route('sarpras.submissions.realize_form', $sub) }}" class="btn btn-sm btn-primary" data-disable-on-submit>
                                    <i class="bi bi-box-arrow-down me-1"></i> Realisasikan
                                </a>
                                <a href="{{ route('sarpras.submissions.show', $sub) }}" class="btn btn-sm btn-light btn-active-light-primary">Detail</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <x-empty-state
                                    icon="bi-inbox"
                                    title="Tidak ada pengajuan siap direalisasi"
                                    description="Pengajuan yang disetujui Kepala Sekolah akan muncul di sini." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $submissions->withQueryString()->links() }}
        </div>
    </div>
</div>
@endsection