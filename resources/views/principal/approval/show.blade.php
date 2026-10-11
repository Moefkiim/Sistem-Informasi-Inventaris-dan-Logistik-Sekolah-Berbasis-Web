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
                        <th>Diminta</th>
                        <th>Terealisasi</th>
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
                            <td class="fw-bold">
                                {{ $item->item_name }}
                                @if($item->item)
                                    <div class="fs-8 text-muted">Master: {{ $item->item->code }}</div>
                                @endif
                            </td>
                            <td><span class="badge badge-light-primary">{{ $item->quantity }} {{ $item->unit }}</span></td>
                            <td>
                                @if($item->received_quantity)
                                    <span class="badge badge-light-success">{{ $item->received_quantity }} {{ $item->unit }}</span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>Rp {{ number_format($item->estimated_price, 0, ',', '.') }}</td>
                            <td class="text-muted">{{ $item->specification ?: '-' }}</td>
                        </tr>
                    @endforeach
                    <tr class="fw-bold fs-6 bg-light">
                        <td colspan="4" class="text-end">Total Perkiraan Biaya:</td>
                        <td colspan="2" class="text-primary fw-bolder">Rp {{ number_format($totalEstimasi, 0, ',', '.') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        @if($submission->isRealized())
            <div class="bg-light-success p-5 rounded border border-success mb-4">
                <h5 class="text-success fw-bolder mb-3"><i class="bi bi-check-circle me-2"></i> Pengajuan Terealisasi oleh Sarpras</h5>
                <div class="row g-3">
                    <div class="col-md-4">
                        <span class="text-muted fs-7">Direalisasikan oleh</span>
                        <div class="fw-bold fs-6">{{ $submission->realizedBy?->name ?? '-' }}</div>
                    </div>
                    <div class="col-md-4">
                        <span class="text-muted fs-7">Tanggal Realisasi</span>
                        <div class="fw-bold fs-6">{{ $submission->realized_at?->format('d/m/Y H:i') ?? '-' }}</div>
                    </div>
                    <div class="col-md-4">
                        <span class="text-muted fs-7">Catatan Realisasi</span>
                        <div class="fw-bold fs-6">{{ $submission->realization_notes ?: '-' }}</div>
                    </div>
                </div>
            </div>

            <h4 class="fw-bolder mt-6 mb-4">Transaksi Barang Masuk Terkait</h4>
            <div class="table-responsive mb-4">
                <table class="table table-sm table-row-bordered align-middle gy-3">
                    <thead class="fw-bold text-muted fs-8 text-uppercase">
                        <tr>
                            <th>No. Transaksi</th>
                            <th>Barang</th>
                            <th>Jumlah</th>
                            <th>Sumber</th>
                            <th>Tanggal Masuk</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($submission->incomingItems as $incoming)
                            <tr>
                                <td class="text-nowrap fw-bold">{{ $incoming->transaction_number }}</td>
                                <td>{{ $incoming->item?->name }} ({{ $incoming->item?->code }})</td>
                                <td>{{ $incoming->quantity }}</td>
                                <td>{{ ucfirst($incoming->source) }}</td>
                                <td>{{ $incoming->entry_date->format('d/m/Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if($submission->status === 'reviewed_sarpras')
            <div class="separator separator-dashed my-6"></div>
            <div class="bg-light-success p-6 rounded border border-success">
                <h4 class="fw-bolder text-success mb-3">Keputusan Kepala Sekolah</h4>
                <form action="{{ route('kepala_sekolah.approval.decide', $submission) }}" method="POST" data-disable-on-submit>
                    @csrf
                    <div class="mb-4">
                        <label class="form-label fw-bold">Catatan / Arahan Kepala Sekolah</label>
                        <textarea name="principal_notes" id="principalNotes" class="form-control @error('principal_notes') is-invalid @enderror" rows="3" placeholder="Tuliskan arahan pelaksanaan pengadaan barang...">{{ old('principal_notes') }}</textarea>
                        <div class="form-text text-muted fs-8">Wajib diisi (minimal 10 karakter) saat menolak pengajuan.</div>
                        @error('principal_notes')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="d-flex justify-content-end gap-3">
                        <button type="submit" name="action" value="reject" class="btn btn-danger js-submit-action" data-action="reject" onclick="return confirm('Apakah Anda yakin ingin MENOLAK pengajuan ini? Pastikan alasan penolakan sudah diisi.')">
                            <i class="bi bi-x-circle me-1"></i> Tolak Pengajuan
                        </button>
                        <button type="submit" name="action" value="approve" class="btn btn-success js-submit-action" data-action="approve" onclick="return confirm('Apakah Anda yakin ingin MENYETUJUI pengajuan ini?')">
                            <i class="bi bi-check-circle me-1"></i> Setujui Pengajuan (Approve)
                        </button>
                    </div>
                </form>
            </div>
        @elseif(in_array($submission->status, ['approved', 'rejected', 'realized']))
            <div class="separator separator-dashed my-6"></div>
            <div class="alert alert-light-{{ $submission->status === 'rejected' ? 'danger' : 'success' }} p-4">
                <strong>Keputusan Kepala Sekolah:</strong>
                {{ $submission->status === 'rejected' ? 'DITOLAK' : 'DISETUJUI' }}
                @if($submission->principal_notes)
                    <div class="mt-2"><strong>Catatan:</strong> {{ $submission->principal_notes }}</div>
                @endif
                @if($submission->decided_at)
                    <div class="text-muted fs-8 mt-1">Pada: {{ $submission->decided_at->format('d/m/Y H:i') }}</div>
                @endif
                @if($submission->isRealized())
                    <div class="mt-2 text-success fw-bolder"><i class="bi bi-check-circle me-1"></i> Pengajuan telah direalisasikan oleh Sarpras.</div>
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

        @if($submission->histories && $submission->histories->isNotEmpty())
            <div class="separator separator-dashed my-6"></div>
            <h4 class="fw-bolder mb-4">Linimasa Proses Pengajuan</h4>
            <x-submission-timeline :submission="$submission" />
        @endif
    </div>
    <div class="card-footer">
        <a href="{{ route('kepala_sekolah.approval.index') }}" class="btn btn-light">
            <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar
        </a>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var notes = document.getElementById('principalNotes');
        if (!notes) {
            return;
        }

        document.querySelectorAll('.js-submit-action').forEach(function (button) {
            button.addEventListener('click', function () {
                var isReject = this.getAttribute('data-action') === 'reject';
                notes.required = isReject;
                notes.minLength = isReject ? 10 : 0;
            });
        });
    });
</script>
@endpush
