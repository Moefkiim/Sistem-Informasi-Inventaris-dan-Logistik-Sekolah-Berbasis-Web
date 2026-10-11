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
                        <th>Diminta</th>
                        <th>Terealisasi</th>
                        <th>Estimasi Biaya Satuan</th>
                        <th>Spesifikasi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($submission->items as $idx => $item)
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
                        <textarea name="sarpras_notes" id="sarprasNotes" class="form-control @error('sarpras_notes') is-invalid @enderror" rows="3" placeholder="Tuliskan catatan kelayakan ketersediaan anggaran / kebutuhan sebelum diteruskan ke Kepala Sekolah..." data-required-action="reject">{{ old('sarpras_notes') }}</textarea>
                        <div class="form-text text-muted fs-8">Wajib diisi (minimal 10 karakter) saat menolak pengajuan.</div>
                        @error('sarpras_notes')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="d-flex justify-content-end gap-3">
                        <button type="submit" name="action" value="reject" class="btn btn-danger js-submit-action" data-action="reject" onclick="return confirm('Tolak permohonan pengajuan ini? Pastikan alasan penolakan sudah diisi.')">
                            <i class="bi bi-x-circle me-1"></i> Tolak Pengajuan
                        </button>
                        <button type="submit" name="action" value="review" class="btn btn-primary js-submit-action" data-action="review" onclick="return confirm('Teruskan pengajuan ke Kepala Sekolah?')">
                            <i class="bi bi-check2-circle me-1"></i> Teruskan ke Kepala Sekolah
                        </button>
                    </div>
                </form>
            </div>
        @else
            <div class="alert alert-light-info p-4">
                <strong>Status Pengajuan:</strong> <x-submission-status-badge :status="$submission->status" />
                @if($submission->sarpras_notes)
                    <div class="mt-2"><strong>Catatan Sarpras:</strong> {{ $submission->sarpras_notes }}</div>
                @endif
            </div>
            @if($submission->canBeRealizedBySarpras())
                <div class="bg-light-primary p-6 rounded border border-primary mt-4">
                    <h4 class="fw-bolder text-primary mb-2"><i class="bi bi-box-arrow-down me-2"></i> Pengajuan Siap Direalisasi</h4>
                    <p class="text-muted fs-7 mb-4">Pengajuan telah disetujui Kepala Sekolah. Realisasikan untuk mencatat barang masuk dan memperbarui inventaris.</p>
                    <a href="{{ route('sarpras.submissions.realize_form', $submission) }}" class="btn btn-primary" data-disable-on-submit>
                        <i class="bi bi-box-arrow-down me-1"></i> Realisasikan Sekarang
                    </a>
                </div>
            @elseif($submission->isRealized())
                <div class="bg-light-success p-6 rounded border border-success mt-4">
                    <h4 class="fw-bolder text-success mb-4"><i class="bi bi-check-circle me-2"></i> Pengajuan Terealisasi</h4>
                    <div class="row g-4">
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
            @endif
        @endif

        @if($submission->incomingItems->isNotEmpty())
            <div class="separator separator-dashed my-6"></div>
            <h4 class="fw-bolder mb-4">Transaksi Barang Masuk Terkait</h4>
            <div class="table-responsive">
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

        @if($submission->histories && $submission->histories->isNotEmpty())
            <div class="separator separator-dashed my-6"></div>
            <h4 class="fw-bolder mb-4">Linimasa Proses Pengajuan</h4>
            <x-submission-timeline :submission="$submission" />
        @endif
    </div>
    <div class="card-footer">
        <a href="{{ route('sarpras.submissions.index') }}" class="btn btn-light">
            <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar
        </a>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var notes = document.getElementById('sarprasNotes');
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
