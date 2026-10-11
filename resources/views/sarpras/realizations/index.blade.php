@extends('layouts.app')

@section('title', 'Siap Direalisasi - Sarpras')
@section('header-title', 'Pengajuan Siap Direalisasi')

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <h3 class="fw-bolder">Realisasi Pengajuan yang Telah Disetujui</h3>
        </div>
    </div>
    <div class="card-body pt-0">
        @if(session('success'))
            <div class="alert alert-success d-flex align-items-center p-4 mb-5">
                <i class="bi bi-check-circle fs-2 text-success me-3"></i>
                <div>{{ session('success') }}</div>
            </div>
        @endif

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
                            <td colspan="7" class="text-center text-muted py-8">Tidak ada pengajuan yang menunggu realisasi.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection