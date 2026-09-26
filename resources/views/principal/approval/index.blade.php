@extends('layouts.app')

@section('title', 'Persetujuan Kepala Sekolah')
@section('header-title', 'Persetujuan Pengajuan Inventaris')

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <h3 class="fw-bolder">Daftar Pengajuan Menunggu Keputusan Kepala Sekolah</h3>
        </div>
        <div class="card-toolbar">
            <div class="btn-group">
                <a href="{{ route('kepala_sekolah.approval.index', ['status' => 'reviewed_sarpras']) }}" class="btn btn-sm {{ $status === 'reviewed_sarpras' ? 'btn-primary' : 'btn-light' }}">
                    Menunggu Approval
                </a>
                <a href="{{ route('kepala_sekolah.approval.index', ['status' => 'all']) }}" class="btn btn-sm {{ $status === 'all' ? 'btn-primary' : 'btn-light' }}">
                    Semua Riwayat
                </a>
            </div>
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
                        <th>Kajur Pengaju</th>
                        <th>Jurusan</th>
                        <th>Judul Kebutuhan</th>
                        <th>Status</th>
                        <th>Diverifikasi Sarpras</th>
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
                                @if($sub->status === 'reviewed_sarpras')
                                    <span class="badge badge-light-warning">Perlu Keputusan</span>
                                @elseif($sub->status === 'approved')
                                    <span class="badge badge-light-success">Disetujui</span>
                                @elseif($sub->status === 'rejected')
                                    <span class="badge badge-light-danger">Ditolak</span>
                                @endif
                            </td>
                            <td>{{ $sub->sarprasUser?->name ?: '-' }}</td>
                            <td class="text-end">
                                <a href="{{ route('kepala_sekolah.approval.show', $sub) }}" class="btn btn-sm btn-light-primary">
                                    {{ $sub->status === 'reviewed_sarpras' ? 'Tinjau & Putuskan' : 'Lihat Detail' }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-8">Tidak ada permohonan inventaris pada status ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $submissions->links() }}</div>
    </div>
</div>
@endsection
