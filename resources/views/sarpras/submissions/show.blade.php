@extends('layouts.app')

@section('title', 'Proses Pengajuan - ' . $submission->submission_number)
@section('header-title', 'Verifikasi Pengajuan Kajur')

@section('content')
<div class="card border-0 shadow-sm mb-5">
    <div class="card-header pt-6">
        <h3 class="fw-bolder">Pengajuan #{{ $submission->submission_number }}</h3>
        <div class="card-toolbar">
            <span class="badge badge-light-primary fs-7 py-2 px-4">Pengaju: {{ $submission->user->name }} (Jurusan {{ $submission->department }})</span>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-5 mb-5">
            <div class="col-md-6">
                <span class="text-muted fs-7">Judul Permohonan</span>
                <div class="fw-bolder fs-5 text-dark">{{ $submission->title }}</div>
            </div>
            <div class="col-md-6">
                <span class="text-muted fs-7">Waktu Diajukan</span>
                <div class="fw-bold fs-6">{{ $submission->created_at->format('d F Y, H:i') }}</div>
            </div>
            <div class="col-12">
                <span class="text-muted fs-7">Tujuan Pengadaan</span>
                <div class="bg-light p-4 rounded mt-1">{{ $submission->purpose ?: 'Tidak ada keterangan maksud & tujuan.' }}</div>
            </div>
        </div>

        <h4 class="fw-bolder mt-6 mb-4">Rincian Item Kebutuhan</h4>
        <div class="table-responsive mb-5">
            <table class="table table-row-bordered align-middle gy-4">
                <thead class="bg-light">
                    <tr class="fw-bolder fs-7 text-gray-800">
                        <th>#</th>
                        <th>Nama Barang</th>
                        <th>Jumlah</th>
                        <th>Estimasi Biaya Satuan</th>
                        <th>Spesifikasi</th>
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

        @if($submission->status === 'submitted')
            <div class="separator separator-dashed my-6"></div>
            <div class="bg-light-primary p-6 rounded border border-primary">
                <h4 class="fw-bolder text-primary mb-3">Tindakan Verifikasi Sarpras</h4>
                <form action="{{ route('sarpras.submissions.process', $submission) }}" method="POST" data-disable-on-submit>
                    @csrf
                    <div class="mb-4">
                        <label class="form-label fw-bold required">Catatan Hasil Telaah & Rekomendasi Sarpras</label>
                        <textarea name="sarpras_notes" class="form-control" rows="3" placeholder="Tuliskan catatan kelayakan ketersediaan anggaran / kebutuhan sebelum diteruskan ke Kepala Sekolah..." required></textarea>
                    </div>
                    <div class="d-flex justify-content-end gap-3">
                        <button type="submit" name="action" value="reject" class="btn btn-danger" onclick="return confirm('Tolak permohonan pengajuan ini?')">
                            <i class="bi bi-x-circle me-1"></i> Tolak Pengajuan
                        </button>
                        <button type="submit" name="action" value="review" class="btn btn-primary" onclick="return confirm('Teruskan pengajuan ke Kepala Sekolah?')">
                            <i class="bi bi-check2-circle me-1"></i> Teruskan ke Kepala Sekolah
                        </button>
                    </div>
                </form>
            </div>
        @else
            <div class="alert alert-light-info p-4">
                <strong>Status Pengajuan:</strong> {{ strtoupper(str_replace('_', ' ', $submission->status)) }}
                @if($submission->sarpras_notes)
                    <div class="mt-2"><strong>Catatan Sarpras:</strong> {{ $submission->sarpras_notes }}</div>
                @endif
            </div>
        @endif
    </div>
    <div class="card-footer">
        <a href="{{ route('sarpras.submissions.index') }}" class="btn btn-light">
            <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar
        </a>
    </div>
</div>
@endsection
