@extends('layouts.app')

@section('title', 'Daftar Pengajuan Saya')
@section('header-title', 'Pengajuan Inventaris (Kajur)')

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <h3 class="fw-bolder">Riwayat Permohonan Pengajuan Barang</h3>
        </div>
        <div class="card-toolbar">
            <a href="{{ route('kajur.submissions.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> Buat Pengajuan Baru
            </a>
        </div>
    </div>
    <div class="card-body pt-0">
        @if(session('success'))
            <div class="alert alert-success d-flex align-items-center p-4 mb-5">
                <i class="bi bi-check-circle fs-2 text-success me-3"></i>
                <div class="d-flex flex-column">
                    <span>{{ session('success') }}</span>
                </div>
            </div>
        @endif

        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5">
                <thead>
                    <tr class="text-start text-muted fw-bolder fs-7 text-uppercase gs-0">
                        <th>No. Pengajuan</th>
                        <th>Judul Permohonan</th>
                        <th>Jumlah Item</th>
                        <th>Status</th>
                        <th>Tanggal Dibuat</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody class="text-gray-600 fw-bold">
                    @forelse($submissions as $sub)
                        <tr>
                            <td>
                                <a href="{{ route('kajur.submissions.show', $sub) }}" class="text-dark fw-bolder text-hover-primary">
                                    {{ $sub->submission_number }}
                                </a>
                            </td>
                            <td>{{ $sub->title }}</td>
                            <td><span class="badge badge-light-info">{{ $sub->items->count() }} Item</span></td>
                            <td>
                                @if($sub->status === 'draft')
                                    <span class="badge badge-light-secondary">Draft</span>
                                @elseif($sub->status === 'submitted')
                                    <span class="badge badge-light-warning">Menunggu Review Sarpras</span>
                                @elseif($sub->status === 'reviewed_sarpras')
                                    <span class="badge badge-light-primary">Menunggu Approval Kepsek</span>
                                @elseif($sub->status === 'approved')
                                    <span class="badge badge-light-success">Disetujui</span>
                                @elseif($sub->status === 'rejected')
                                    <span class="badge badge-light-danger">Ditolak</span>
                                @elseif($sub->status === 'cancelled')
                                    <span class="badge badge-light-dark">Dibatalkan</span>
                                @endif
                            </td>
                            <td>{{ $sub->created_at->format('d M Y H:i') }}</td>
                            <td class="text-end">
                                <a href="{{ route('kajur.submissions.show', $sub) }}" class="btn btn-sm btn-light btn-active-light-primary">
                                    Detail
                                </a>
                                @if($sub->isDraft())
                                    <form action="{{ route('kajur.submissions.submitDraft', $sub) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-light-success" onclick="return confirm('Ajukan draf ke pihak Sarpras?')">
                                            Kirim
                                        </button>
                                    </form>
                                    <form action="{{ route('kajur.submissions.cancel', $sub) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-light-danger" onclick="return confirm('Batalkan draft permohonan ini?')">
                                            Batal
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-8">Belum ada pengajuan inventaris yang dibuat.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $submissions->links() }}
        </div>
    </div>
</div>
@endsection
