@extends('layouts.app')

@section('title', 'Detail Peminjaman ' . $loan->loan_number)
@section('header-title', 'Detail Peminjaman')

@section('content')
<div class="row g-6">
    {{-- Informasi utama --}}
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-6">
            <div class="card-header border-0 pt-6">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                    <div>
                        <h3 class="fw-bolder mb-1 font-monospace">{{ $loan->loan_number }}</h3>
                        <p class="text-muted mb-0">Dicatat {{ $loan->created_at?->format('d M Y H:i') }}</p>
                    </div>
                    <x-loan-status-badge :status="$loan->status" class="fs-6 px-4 py-3" />
                </div>
            </div>

            <div class="card-body pt-0">
                @if(session('success'))
                    <div class="alert alert-success d-flex align-items-center p-4 mb-5">
                        <i class="fas fa-check-circle fs-2 text-success me-3"></i>
                        <div>{{ session('success') }}</div>
                    </div>
                @endif

                @if($errors->any())
                    <div class="alert alert-danger d-flex align-items-start p-4 mb-5">
                        <i class="fas fa-exclamation-triangle fs-2 text-danger me-3 mt-1"></i>
                        <div>
                            @foreach($errors->all() as $error)
                                <div>{{ $error }}</div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if($loan->isOverdue())
                    <div class="alert alert-danger d-flex align-items-center p-4 mb-5">
                        <i class="fas fa-clock fs-2 text-danger me-3"></i>
                        <div>
                            Peminjaman ini <strong>terlambat {{ $loan->overdueDays() }} hari</strong>
                            dari rencana tanggal kembali
                            ({{ $loan->due_date?->format('d M Y') }}).
                        </div>
                    </div>
                @endif

                <div class="row g-5">
                    <div class="col-md-6">
                        <h6 class="text-muted text-uppercase fs-8 mb-2">Peminjam</h6>
                        <p class="fw-bold text-dark mb-0">{{ $loan->borrower_name }}</p>
                        <p class="text-muted mb-0">{{ $loan->borrower_department ?? 'Jurusan tidak diisi' }}</p>
                        @if($loan->borrower)
                            <p class="text-muted fs-7 mb-0">Akun sistem: {{ $loan->borrower->name }}</p>
                        @endif
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted text-uppercase fs-8 mb-2">Barang Dipinjam</h6>
                        <p class="fw-bold text-dark mb-0">{{ $loan->item?->name ?? 'Barang tidak ditemukan' }}</p>
                        <p class="text-muted font-monospace mb-0">
                            {{ $loan->item?->code }} &bull;
                            {{ $loan->quantity }} {{ $loan->item?->unit ?? 'unit' }}
                        </p>
                        @if($loan->assetUnit)
                            <div class="mt-1">
                                <span class="badge badge-light-primary font-monospace">
                                    <i class="fas fa-hdd me-1"></i>{{ $loan->assetUnit->unit_inventory_number }}
                                </span>
                            </div>
                        @endif
                    </div>
                    <div class="col-md-4">
                        <h6 class="text-muted text-uppercase fs-8 mb-2">Tanggal Pinjam</h6>
                        <p class="fw-bold mb-0">{{ $loan->loan_date?->format('d M Y') ?? '-' }}</p>
                    </div>
                    <div class="col-md-4">
                        <h6 class="text-muted text-uppercase fs-8 mb-2">Rencana Kembali</h6>
                        <p class="fw-bold mb-0 {{ $loan->isOverdue() ? 'text-danger' : '' }}">
                            {{ $loan->due_date?->format('d M Y') ?? '-' }}
                        </p>
                    </div>
                    <div class="col-md-4">
                        <h6 class="text-muted text-uppercase fs-8 mb-2">Tanggal Dikembalikan</h6>
                        <p class="fw-bold mb-0">{{ $loan->return_date?->format('d M Y') ?? '-' }}</p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted text-uppercase fs-8 mb-2">Kondisi Saat Dipinjam</h6>
                        @if($loan->condition_on_loan)
                            <x-condition-badge :condition="$loan->condition_on_loan" />
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted text-uppercase fs-8 mb-2">Kondisi Saat Dikembalikan</h6>
                        @if($loan->condition_on_return)
                            <x-condition-badge :condition="$loan->condition_on_return" />
                        @else
                            <span class="text-muted">Belum dikembalikan</span>
                        @endif
                    </div>
                    <div class="col-12">
                        <h6 class="text-muted text-uppercase fs-8 mb-2">Keperluan</h6>
                        <p class="mb-0">{{ $loan->purpose }}</p>
                    </div>
                    @if($loan->notes)
                        <div class="col-12">
                            <h6 class="text-muted text-uppercase fs-8 mb-2">Catatan</h6>
                            <p class="mb-0" style="white-space: pre-line">{{ $loan->notes }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Riwayat proses --}}
        <div class="card border-0 shadow-sm">
            <div class="card-header border-0 pt-6">
                <h5 class="fw-bold mb-0">Riwayat Proses</h5>
            </div>
            <div class="card-body pt-0">
                <div class="timeline">
                    <div class="timeline-item">
                        <div class="timeline-line w-40px"></div>
                        <div class="timeline-icon">
                            <i class="fas fa-plus-circle text-primary fs-3"></i>
                        </div>
                        <div class="timeline-content">
                            <div class="fw-bold text-dark">Peminjaman dicatat</div>
                            <div class="text-muted fs-7">
                                {{ $loan->created_at?->format('d M Y H:i') }}
                                @if($loan->recordedBy)
                                    &bullet; dicatat oleh {{ $loan->recordedBy->name }}
                                @endif
                            </div>
                        </div>
                    </div>

                    @if($loan->approved_at)
                        <div class="timeline-item">
                            <div class="timeline-line w-40px"></div>
                            <div class="timeline-icon">
                                <i class="fas fa-{{ $loan->status === 'ditolak' ? 'times-circle text-danger' : 'check-circle text-success' }} fs-3"></i>
                            </div>
                            <div class="timeline-content">
                                <div class="fw-bold text-dark">
                                    {{ $loan->status === 'ditolak' ? 'Permohonan ditolak' : 'Disetujui & diserahkan' }}
                                </div>
                                <div class="text-muted fs-7">
                                    {{ $loan->approved_at->format('d M Y H:i') }}
                                    &bullet; oleh {{ $loan->approvedBy?->name ?? 'tidak diketahui' }}
                                </div>
                            </div>
                        </div>
                    @endif

                    @if($loan->returned_at)
                        <div class="timeline-item">
                            <div class="timeline-line w-40px"></div>
                            <div class="timeline-icon">
                                <i class="fas fa-undo-alt text-info fs-3"></i>
                            </div>
                            <div class="timeline-content">
                                <div class="fw-bold text-dark">
                                    Barang dikembalikan
                                    @if($loan->condition_on_return)
                                        (kondisi: {{ ucfirst(str_replace('_', ' ', $loan->condition_on_return)) }})
                                    @endif
                                </div>
                                <div class="text-muted fs-7">
                                    {{ $loan->returned_at->format('d M Y H:i') }}
                                    &bullet; diterima oleh {{ $loan->returnedTo?->name ?? 'tidak diketahui' }}
                                </div>
                            </div>
                        </div>
                    @endif

                    @if($loan->status === 'menunggu')
                        <div class="timeline-item">
                            <div class="timeline-icon">
                                <i class="fas fa-hourglass-half text-warning fs-3"></i>
                            </div>
                            <div class="timeline-content">
                                <div class="fw-bold text-dark">Menunggu peninjauan Sarpras</div>
                            </div>
                        </div>
                    @elseif(in_array($loan->status, ['dipinjam', 'terlambat'], true))
                        <div class="timeline-item">
                            <div class="timeline-icon">
                                <i class="fas fa-hourglass-half text-warning fs-3"></i>
                            </div>
                            <div class="timeline-content">
                                <div class="fw-bold text-dark">
                                    Menunggu pengembalian barang
                                    @if($loan->due_date)
                                        (sisa {{ max(0, (int) $loan->due_date->diffInDays(now())) }} hari)
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Panel aksi --}}
    <div class="col-lg-4">
        @if($loan->isApprovable())
            <div class="card border-0 shadow-sm mb-5">
                <div class="card-header border-0 pt-5">
                    <h6 class="fw-bold text-success mb-0">Setujui &amp; Serahkan Barang</h6>
                </div>
                <div class="card-body pt-0">
                    <p class="text-muted fs-7">
                        Konfirmasi barang telah diserahkan kepada peminjam.
                        @if($loan->item?->isConsumable())
                            Stok akan berkurang sebanyak <strong>{{ $loan->quantity }} {{ $loan->item->unit }}</strong>.
                        @else
                            Status aset akan berubah menjadi <strong>Dipinjam</strong>.
                        @endif
                    </p>
                    <form action="{{ route('sarpras.loans.approve', $loan) }}" method="POST"
                          onsubmit="return confirm('Pastikan barang sudah diserahkan kepada peminjam. Lanjutkan?')">
                        @csrf
                        <button type="submit" class="btn btn-success w-100">
                            <i class="fas fa-handshake me-2"></i>Serahkan Barang
                        </button>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-5">
                <div class="card-header border-0 pt-5">
                    <h6 class="fw-bold text-danger mb-0">Tolak Permohonan</h6>
                </div>
                <div class="card-body pt-0">
                    <form action="{{ route('sarpras.loans.reject', $loan) }}" method="POST"
                          onsubmit="return confirm('Tolak permohonan peminjaman ini?')">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label required" for="reject_notes">Alasan Penolakan</label>
                            <textarea name="notes" id="reject_notes" rows="3"
                                      class="form-control form-control-solid"
                                      placeholder="Jelaskan alasan penolakan..." required></textarea>
                        </div>
                        <button type="submit" class="btn btn-danger w-100">
                            <i class="fas fa-times me-2"></i>Tolak Permohonan
                        </button>
                    </form>
                </div>
            </div>
        @endif

        @if($loan->isReturnable())
            <div class="card border-0 shadow-sm mb-5">
                <div class="card-header border-0 pt-5">
                    <h6 class="fw-bold text-info mb-0">Terima Pengembalian Barang</h6>
                </div>
                <div class="card-body pt-0">
                    <form action="{{ route('sarpras.loans.return', $loan) }}" method="POST"
                          onsubmit="return confirm('Konfirmasi barang sudah diterima kembali?')">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label required" for="condition_on_return">
                                Kondisi Barang Saat Kembali
                            </label>
                            <select name="condition_on_return" id="condition_on_return"
                                    class="form-select form-select-solid" required>
                                <option value="baik">Baik</option>
                                <option value="rusak_ringan">Rusak Ringan</option>
                                <option value="rusak_berat">Rusak Berat</option>
                            </select>
                            <div class="form-text">
                                Jika kondisi berubah, riwayat kondisi barang akan tercatat otomatis.
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="return_notes">Catatan Pengembalian</label>
                            <textarea name="notes" id="return_notes" rows="2"
                                      class="form-control form-control-solid"
                                      placeholder="Kondisi fisik, kelengkapan, dll..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-info text-white w-100">
                            <i class="fas fa-undo me-2"></i>Terima Pengembalian
                        </button>
                    </form>
                </div>
            </div>
        @endif

        @if($loan->item)
            <div class="card border-0 shadow-sm">
                <div class="card-header border-0 pt-5">
                    <h6 class="fw-bold mb-0">Info Barang</h6>
                </div>
                <div class="card-body pt-0">
                    <table class="table table-sm mb-0">
                        <tbody>
                            <tr><td class="text-muted fw-normal">Kode</td>
                                <td class="font-monospace fw-bold text-end">{{ $loan->item->code }}</td></tr>
                            @if($loan->item->inventory_number)
                                <tr><td class="text-muted fw-normal">No. Inventaris</td>
                                    <td class="font-monospace fw-bold text-end">{{ $loan->item->inventory_number }}</td></tr>
                            @endif
                            @if($loan->assetUnit)
                                <tr><td class="text-muted fw-normal">Unit Aset</td>
                                    <td class="font-monospace fw-bold text-end">{{ $loan->assetUnit->unit_inventory_number }}</td></tr>
                            @endif
                            <tr><td class="text-muted fw-normal">Nama</td>
                                <td class="text-end">{{ $loan->item->name }}</td></tr>
                            <tr><td class="text-muted fw-normal">Merk</td>
                                <td class="text-end">{{ $loan->item->brand ?? '-' }}</td></tr>
                            <tr><td class="text-muted fw-normal">Tipe</td>
                                <td class="text-end">{{ $loan->item->isIndividual() ? 'Aset Individual' : 'Stok / Consumable' }}</td></tr>
                            <tr><td class="text-muted fw-normal">Stok</td>
                                <td class="text-end">{{ $loan->item->stock }} {{ $loan->item->unit }}</td></tr>
                            <tr><td class="text-muted fw-normal">Kondisi</td>
                                <td class="text-end"><x-condition-badge :condition="$loan->assetUnit?->current_condition ?? $loan->item->current_condition" /></td></tr>
                            <tr><td class="text-muted fw-normal">Status</td>
                                <td class="text-end"><x-unit-status-badge :status="$loan->assetUnit?->current_status ?? $loan->item->current_status" /></td></tr>
                            <tr><td class="text-muted fw-normal">Lokasi</td>
                                <td class="text-end">{{ $loan->item->location?->name ?? '-' }}</td></tr>
                        </tbody>
                    </table>
                    <a href="{{ route('sarpras.inventory.show', $loan->item) }}"
                       class="btn btn-light btn-sm w-100 mt-3">
                        Lihat Detail Barang
                    </a>
                </div>
            </div>
        @endif

        <div class="d-flex gap-2 mt-5">
            <a href="{{ route('sarpras.loans.index') }}" class="btn btn-light flex-grow-1">
                <i class="fas fa-arrow-left me-2"></i>Kembali
            </a>
            <button type="button" onclick="window.print()" class="btn btn-light" title="Cetak halaman">
                <i class="fas fa-print"></i>
            </button>
        </div>
    </div>
</div>
@endsection
