@extends('layouts.app')

@section('title', 'Putusan Pengajuan - ' . $submission->submission_number)
@section('header-title', 'Tinjauan Eksekutif & Keputusan')

@section('content')
<div class="card border-0 shadow-sm mb-5">
    <div class="card-header pt-6">
        <h3 class="fw-bolder">Pengajuan #{{ $submission->submission_number }}</h3>
        <div class="card-toolbar">
            <span class="badge badge-light-primary fs-7 py-2 px-3">Jurusan: {{ $submission->department }}</span>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-5 mb-5">
            <div class="col-md-4">
                <span class="text-muted fs-7">Kajur Pemohon</span>
                <div class="fw-bolder fs-6 text-dark">{{ $submission->user->name }}</div>
            </div>
            <div class="col-md-4">
                <span class="text-muted fs-7">Judul Permohonan</span>
                <div class="fw-bolder fs-6 text-dark">{{ $submission->title }}</div>
            </div>
            <div class="col-md-4">
                <span class="text-muted fs-7">Tanggal Diajukan</span>
                <div class="fw-bold fs-6">{{ $submission->created_at->format('d/m/Y H:i') }}</div>
            </div>
            <div class="col-12">
                <span class="text-muted fs-7">Urgensi & Maksud Kebutuhan</span>
                <div class="bg-light p-4 rounded mt-1">{{ $submission->purpose ?: 'Tidak ada catatan.' }}</div>
            </div>
        </div>

        <div class="alert alert-light-primary p-4 mb-5 border border-primary">
            <div class="fw-bolder text-primary">Rekomendasi & Hasil Telaah Sarpras:</div>
            <div class="mt-1">{{ $submission->sarpras_notes }}</div>
            <div class="text-muted fs-8 mt-1">Diverifikasi oleh: {{ $submission->sarprasUser?->name }} ({{ $submission->reviewed_at?->format('d/m/Y H:i') }})</div>
        </div>

        <h4 class="fw-bolder mt-6 mb-4">Rincian Item Barang yang Diajukan</h4>
        <div class="table-responsive mb-5">
            <table class="table table-row-bordered align-middle gy-4">
                <thead class="bg-light">
                    <tr class="fw-bolder fs-7 text-gray-800">
                        <th>#</th>
                        <th>Nama Item</th>
                        <th>Kuantitas</th>
                        <th>Estimasi Harga</th>
                        <th>Spesifikasi Teknis</th>
                    </tr>
                </thead>
                <tbody>
                    @php $totalEstimasi = 0; @endphp
                    @foreach($submission->items as $idx => $item)
                        @php $totalEstimasi += ($item->estimated_price * $item->quantity); @endphp
                        <tr>
                            <td>{{ $idx + 1 }}</td>
                            <td class="fw-bold">{{ $item->item_name }}</td>
                            <td><span class="badge badge-light-primary">{{ $item->quantity }} {{ $item->unit }}</span></td>
                            <td>Rp {{ number_format($item->estimated_price, 0, ',', '.') }}</td>
                            <td class="text-muted">{{ $item->specification ?: '-' }}</td>
                        </tr>
                    @endforeach
                    <tr class="fw-bold fs-6 bg-light">
                        <td colspan="3" class="text-end">Total Perkiraan Biaya:</td>
                        <td colspan="2" class="text-primary fw-bolder">Rp {{ number_format($totalEstimasi, 0, ',', '.') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        @if($submission->status === 'reviewed_sarpras')
            <div class="separator separator-dashed my-6"></div>
            <div class="bg-light-success p-6 rounded border border-success">
                <h4 class="fw-bolder text-success mb-3">Keputusan Kepala Sekolah</h4>
                <form action="{{ route('kepala_sekolah.approval.decide', $submission) }}" method="POST" data-disable-on-submit>
                    @csrf
                    <div class="mb-4">
                        <label class="form-label fw-bold">Catatan / Arahan Kepala Sekolah</label>
                        <textarea name="principal_notes" class="form-control" rows="3" placeholder="Tuliskan arahan pelaksanaan pengadaan barang..."></textarea>
                    </div>
                    <div class="d-flex justify-content-end gap-3">
                        <button type="submit" name="action" value="reject" class="btn btn-danger" onclick="return confirm('Apakah Anda yakin ingin MENOLAK pengajuan ini?')">
                            <i class="bi bi-x-circle me-1"></i> Tolak Pengajuan
                        </button>
                        <button type="submit" name="action" value="approve" class="btn btn-success" onclick="return confirm('Apakah Anda yakin ingin MENYETUJUI pengajuan ini?')">
                            <i class="bi bi-check-circle me-1"></i> Setujui Pengajuan (Approve)
                        </button>
                    </div>
                </form>
            </div>
        @elseif(in_array($submission->status, ['approved', 'rejected']))
            <div class="separator separator-dashed my-6"></div>
            <div class="alert alert-light-{{ $submission->status === 'approved' ? 'success' : 'danger' }} p-4">
                <strong>Keputusan Kepala Sekolah:</strong>
                {{ $submission->status === 'approved' ? 'DISETUJUI' : 'DITOLAK' }}
                @if($submission->principal_notes)
                    <div class="mt-2"><strong>Catatan:</strong> {{ $submission->principal_notes }}</div>
                @endif
                @if($submission->decided_at)
                    <div class="text-muted fs-8 mt-1">Pada: {{ $submission->decided_at->format('d/m/Y H:i') }}</div>
                @endif
            </div>
        @else
            <div class="separator separator-dashed my-6"></div>
            <div class="alert alert-light-warning p-4">
                <strong>Belum ada keputusan.</strong>
                Pengajuan berstatus <span class="text-uppercase fw-bold">{{ $submission->status }}</span>
                @if($submission->status === 'draft' || $submission->status === 'submitted')
                    , sehingga masih menunggu verifikasi Sarpras sebelum dapat diputuskan oleh Kepala Sekolah.
                @elseif($submission->status === 'cancelled')
                    karena dibatalkan, sehingga tidak memerlukan keputusan Kepala Sekolah.
                @endif
                . Kepala Sekolah baru dapat menyetujui atau menolak setelah pengajuan berstatus
                <span class="text-uppercase fw-bold">reviewed_sarpras</span>.
            </div>
        @endif
    </div>
    <div class="card-footer">
        <a href="{{ route('kepala_sekolah.approval.index') }}" class="btn btn-light">
            <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar
        </a>
    </div>
</div>
@endsection
