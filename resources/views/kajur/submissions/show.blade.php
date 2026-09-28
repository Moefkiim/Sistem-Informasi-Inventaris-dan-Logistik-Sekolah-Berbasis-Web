@extends('layouts.app')

@section('title', 'Detail Pengajuan - ' . $submission->submission_number)
@section('header-title', 'Detail Permohonan Pengajuan')

@section('content')
<div class="card border-0 shadow-sm mb-5">
    <div class="card-header pt-6">
        <div class="card-title">
            <h3 class="fw-bolder">Pengajuan #{{ $submission->submission_number }}</h3>
        </div>
        <div class="card-toolbar">
            @if($submission->status === 'draft')
                <span class="badge badge-light-secondary fs-7 py-2 px-4">Status: Draft</span>
            @elseif($submission->status === 'submitted')
                <span class="badge badge-light-warning fs-7 py-2 px-4">Status: Menunggu Review Sarpras</span>
            @elseif($submission->status === 'reviewed_sarpras')
                <span class="badge badge-light-primary fs-7 py-2 px-4">Status: Menunggu Approval Kepala Sekolah</span>
            @elseif($submission->status === 'approved')
                <span class="badge badge-light-success fs-7 py-2 px-4">Status: Disetujui</span>
            @elseif($submission->status === 'rejected')
                <span class="badge badge-light-danger fs-7 py-2 px-4">Status: Ditolak</span>
            @elseif($submission->status === 'cancelled')
                <span class="badge badge-light-dark fs-7 py-2 px-4">Status: Dibatalkan</span>
            @endif
        </div>
    </div>
    <div class="card-body">
        <div class="row g-5 mb-5">
            <div class="col-md-4">
                <span class="text-muted fs-7">Judul Permohonan</span>
                <div class="fw-bolder fs-6 text-dark">{{ $submission->title }}</div>
            </div>
            <div class="col-md-4">
                <span class="text-muted fs-7">Jurusan Pengaju</span>
                <div class="fw-bolder fs-6 text-dark">{{ $submission->department }}</div>
            </div>
            <div class="col-md-4">
                <span class="text-muted fs-7">Tanggal Permohonan</span>
                <div class="fw-bolder fs-6 text-dark">{{ $submission->created_at->format('d F Y, H:i') }}</div>
            </div>
            <div class="col-12">
                <span class="text-muted fs-7">Maksud & Tujuan</span>
                <div class="text-gray-800 bg-light rounded p-4 mt-1">{{ $submission->purpose ?: 'Tidak ada keterangan tambahan.' }}</div>
            </div>
        </div>

        @if($submission->sarpras_notes)
            <div class="alert alert-light-primary p-4 mb-4">
                <div class="fw-bolder text-primary">Catatan Telaah Sarpras:</div>
                <div>{{ $submission->sarpras_notes }}</div>
                <div class="text-muted fs-8 mt-1">Ditinjau oleh: {{ $submission->sarprasUser?->name }} ({{ $submission->reviewed_at?->format('d/m/Y H:i') }})</div>
            </div>
        @endif

        @if($submission->principal_notes)
            <div class="alert alert-light-{{ $submission->status === 'approved' ? 'success' : 'danger' }} p-4 mb-4">
                <div class="fw-bolder text-{{ $submission->status === 'approved' ? 'success' : 'danger' }}">Keputusan Kepala Sekolah:</div>
                <div>{{ $submission->principal_notes }}</div>
                <div class="text-muted fs-8 mt-1">Oleh: {{ $submission->principalUser?->name }} ({{ $submission->decided_at?->format('d/m/Y H:i') }})</div>
            </div>
        @endif

        <h4 class="fw-bolder mt-6 mb-4">Daftar Item Kebutuhan</h4>
        <div class="table-responsive">
            <table class="table table-row-bordered align-middle gy-4">
                <thead class="bg-light">
                    <tr class="fw-bolder fs-7 text-gray-800">
                        <th>#</th>
                        <th>Nama Item Barang</th>
                        <th>Kuantitas</th>
                        <th>Estimasi Harga</th>
                        <th>Spesifikasi Teknis</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($submission->items as $idx => $item)
                        <tr>
                            <td>{{ $idx + 1 }}</td>
                            <td class="fw-bold">{{ $item->item_name }}</td>
                            <td><span class="badge badge-light-primary">{{ $item->quantity }} {{ $item->unit }}</span></td>
                            <td>Rp {{ number_format($item->estimated_price, 0, ',', '.') }}</td>
                            <td class="text-muted">{{ $item->specification ?: '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer d-flex justify-content-between">
        <a href="{{ route('kajur.submissions.index') }}" class="btn btn-light">
            <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar
        </a>

        @if($submission->isDraft())
            <div class="d-flex gap-2">
                <a href="{{ route('kajur.submissions.edit', $submission) }}" class="btn btn-light-warning">
                    <i class="bi bi-pencil me-1"></i> Edit Draft
                </a>
                <form action="{{ route('kajur.submissions.cancel', $submission) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-light-danger" onclick="return confirm('Yakin ingin membatalkan draf pengajuan?')">
                        Batalkan Draft
                    </button>
                </form>
                <form action="{{ route('kajur.submissions.submitDraft', $submission) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-primary" onclick="return confirm('Kirim pengajuan ke Sarpras?')">
                        <i class="bi bi-send me-1"></i> Kirim ke Sarpras
                    </button>
                </form>
            </div>
        @endif
    </div>
</div>
@endsection
