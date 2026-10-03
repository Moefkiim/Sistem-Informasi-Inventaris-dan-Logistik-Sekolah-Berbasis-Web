@extends('layouts.app')

@section('title', 'Tinjauan Pengajuan Sarpras')
@section('header-title', 'Pengajuan Masuk dari Kajur')

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <h3 class="fw-bolder">Verifikasi & Pemrosesan Pengajuan Sarana Prasarana</h3>
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
                        <th>Status</th>
                        <th>Tanggal Masuk</th>
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
                            <td>
                                <x-submission-status-badge :status="$sub->status" />
                            </td>
                            <td>{{ $sub->created_at->format('d/m/Y H:i') }}</td>
                            <td class="text-end">
                                <a href="{{ route('sarpras.submissions.show', $sub) }}" class="btn btn-sm btn-light btn-active-light-primary">
                                    {{ $sub->status === 'submitted' ? 'Proses Review' : 'Lihat Detail' }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-8">Belum ada pengajuan masuk.</td>
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
