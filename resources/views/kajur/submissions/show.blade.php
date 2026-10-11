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
            <x-submission-status-badge :status="$submission->status" class="fs-7 py-2 px-4" />
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

        @php($rejection = $submission->histories->firstWhere('to_status', 'rejected'))

        @if($submission->revision_of_id && $submission->revisionOf)
            <div class="alert alert-light-info border border-info p-4 mb-5">
                <div class="fw-bolder text-info mb-1"><i class="bi bi-arrow-repeat me-1"></i> Draf Revisi</div>
                <div>
                    Revisi dari pengajuan <strong>{{ $submission->revisionOf->submission_number }}</strong>.
                    @if($alasanSebelumnya = $submission->revisionOf->latestRejection())
                        Alasan penolakan sebelumnya: <em>{{ $alasanSebelumnya->notes }}</em>
                    @endif
                </div>
            </div>
        @endif

        @if($submission->isRejected() && $rejection)
            <div class="alert alert-danger p-5 mb-5 border border-danger">
                <h5 class="text-danger fw-bolder mb-2"><i class="bi bi-x-octagon-fill me-2"></i> Pengajuan Ditolak</h5>
                <div class="mb-2">{{ $rejection->notes ?: 'Tidak ada alasan tertulis.' }}</div>
                <div class="text-muted fs-8">
                    Ditolak oleh {{ $rejection->actor?->name ?: 'Sistem' }}
                    ({{ \App\Models\Submission::statusLabel($rejection->from_status) }})
                    pada {{ $rejection->recorded_at->format('d/m/Y H:i') }}
                </div>
            </div>
        @endif

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

        @if($submission->isRealized())
            <div class="bg-light-success p-5 rounded border border-success mb-4">
                <h5 class="text-success fw-bolder mb-3"><i class="bi bi-check-circle me-2"></i> Pengajuan Terealisasi</h5>
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
        @endif

        <h4 class="fw-bolder mt-6 mb-4">Daftar Item Kebutuhan</h4>
        <div class="table-responsive">
            <table class="table table-row-bordered align-middle gy-4">
                <thead class="bg-light">
                    <tr class="fw-bolder fs-7 text-gray-800">
                        <th>#</th>
                        <th>Nama Item Barang</th>
                        <th>Diminta</th>
                        <th>Terealisasi</th>
                        <th>Estimasi Harga</th>
                        <th>Spesifikasi Teknis</th>
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

        @if($submission->incomingItems->isNotEmpty())
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

        @if($submission->histories && $submission->histories->isNotEmpty())
            <div class="separator separator-dashed my-6"></div>
            <h4 class="fw-bolder mb-4">Linimasa Proses Pengajuan</h4>
            <x-submission-timeline :submission="$submission" />
        @endif
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
                <form action="{{ route('kajur.submissions.cancel', $submission) }}" method="POST" data-disable-on-submit>
                    @csrf
                    <button type="submit" class="btn btn-light-danger" onclick="return confirm('Yakin ingin membatalkan draf pengajuan?')">
                        Batalkan Draft
                    </button>
                </form>
                <form action="{{ route('kajur.submissions.submitDraft', $submission) }}" method="POST" data-disable-on-submit>
                    @csrf
                    <button type="submit" class="btn btn-primary" onclick="return confirm('Kirim pengajuan ke Sarpras?')">
                        <i class="bi bi-send me-1"></i> Kirim ke Sarpras
                    </button>
                </form>
            </div>
        @elseif($submission->isRejected())
            <form action="{{ route('kajur.submissions.revise', $submission) }}" method="POST" data-disable-on-submit>
                @csrf
                <button type="submit" class="btn btn-primary" onclick="return confirm('Buat draf revisi dari pengajuan yang ditolak ini? Baris barang akan disalin ke draf baru.')">
                    <i class="bi bi-arrow-repeat me-1"></i> Ajukan Ulang
                </button>
            </form>
        @endif
    </div>
</div>
@endsection
