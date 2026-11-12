@extends('layouts.app')

@section('title', 'Logistik Barang Keluar')
@section('header-title', 'Pencatatan Barang Keluar')

@section('content')
<div class="row g-5">
    <div class="col-xl-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header pt-6">
                <h4 class="fw-bolder">Catat Transaksi Keluar</h4>
            </div>
            <form action="{{ route('sarpras.logistics.outgoing.store') }}" method="POST" data-disable-on-submit>
                @csrf
                <div class="card-body">

                    <div class="mb-4">
                        <label class="form-label fw-bold required">Barang Inventaris (Stok > 0)</label>
                        <select name="item_id" class="form-select form-select-solid" data-control="select2" data-placeholder="-- Pilih Barang --" required>
                            <option value="">-- Pilih Barang --</option>
                            @foreach($items as $it)
                                <option value="{{ $it->id }}" {{ old('item_id') == $it->id ? 'selected' : '' }}>{{ $it->name }} (Stok: {{ $it->stock }} {{ $it->unit }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold required">Jumlah Keluar</label>
                        <input type="number" name="quantity" class="form-control form-control-solid" min="1" value="{{ old('quantity', 1) }}" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold required">Alasan Pengeluaran</label>
                        <select name="reason" class="form-select form-select-solid" required>
                            <option value="Rusak Total" {{ old('reason') == 'Rusak Total' ? 'selected' : '' }}>Rusak Total / Tak Dapat Digunakan</option>
                            <option value="Pemusnahan / Afkir" {{ old('reason') == 'Pemusnahan / Afkir' ? 'selected' : '' }}>Pemusnahan / Aset Di-afkir</option>
                            <option value="Hibah / Penyaluran Luar" {{ old('reason') == 'Hibah / Penyaluran Luar' ? 'selected' : '' }}>Hibah ke Pihak Luar</option>
                            <option value="Lainnya" {{ old('reason') == 'Lainnya' ? 'selected' : '' }}>Lainnya</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold required">Tanggal Keluar</label>
                        <input type="date" name="exit_date" class="form-control form-control-solid" value="{{ old('exit_date', date('Y-m-d')) }}" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Catatan Log</label>
                        <textarea name="notes" class="form-control form-control-solid" rows="2" placeholder="Nomor BAST/berita acara penghapusan aset...">{{ old('notes') }}</textarea>
                    </div>

                    <button type="submit" class="btn btn-danger w-100">
                        <i class="bi bi-box-arrow-up-right me-1"></i> Simpan Transaksi Keluar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="col-xl-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header border-0 pt-6">
                <h3 class="fw-bolder">Riwayat Log Barang Keluar</h3>
            </div>
            <div class="card-body pt-0">
                @if(session('success'))
                    <div class="alert alert-success d-flex align-items-center p-3 mb-4">
                        <i class="bi bi-check-circle fs-3 text-success me-2"></i>
                        <div>{{ session('success') }}</div>
                    </div>
                @endif

                {{-- Toolbar Pencarian & Filter --}}
                <form action="{{ route('sarpras.logistics.outgoing') }}" method="GET" class="card bg-light p-4 border-0 mb-5">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label fs-7 fw-bold text-gray-700">Pencarian</label>
                            <div class="position-relative">
                                <i class="bi bi-search position-absolute top-50 translate-middle-y ms-3 text-gray-500"></i>
                                <input type="text" name="search" class="form-control form-control-solid ps-10 form-control-sm" placeholder="No. Transaksi, barang, catatan..." value="{{ $search }}">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fs-7 fw-bold text-gray-700">Alasan</label>
                            <select name="reason" class="form-select form-select-solid form-select-sm">
                                <option value="">Semua Alasan</option>
                                <option value="Rusak Total" {{ $reason === 'Rusak Total' ? 'selected' : '' }}>Rusak Total</option>
                                <option value="Pemusnahan / Afkir" {{ $reason === 'Pemusnahan / Afkir' ? 'selected' : '' }}>Pemusnahan / Afkir</option>
                                <option value="Hibah / Penyaluran Luar" {{ $reason === 'Hibah / Penyaluran Luar' ? 'selected' : '' }}>Hibah Luar</option>
                                <option value="Lainnya" {{ $reason === 'Lainnya' ? 'selected' : '' }}>Lainnya</option>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fs-7 fw-bold text-gray-700">Rentang Tanggal</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white border-0"><i class="bi bi-calendar-range text-gray-500"></i></span>
                                <input type="text" class="form-control form-control-solid js-daterangepicker" placeholder="Semua tanggal (klik untuk pilih)" value="{{ $startDate && $endDate ? date('d/m/Y', strtotime($startDate)) . ' - ' . date('d/m/Y', strtotime($endDate)) : '' }}" readonly>
                                <input type="hidden" name="start_date" value="{{ $startDate }}">
                                <input type="hidden" name="end_date" value="{{ $endDate }}">
                            </div>
                        </div>
                        <div class="col-12 d-flex justify-content-end gap-2 mt-2">
                            @if($search || $reason || $startDate || $endDate)
                                <a href="{{ route('sarpras.logistics.outgoing') }}" class="btn btn-sm btn-light">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                                </a>
                            @endif
                            <button type="submit" class="btn btn-sm btn-primary">
                                <i class="bi bi-filter me-1"></i> Terapkan Filter
                            </button>
                        </div>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-7 gy-4">
                        <thead>
                            <tr class="text-start text-muted fw-bolder fs-7 text-uppercase gs-0">
                                <th style="width: 50px;" class="text-center">No</th>
                                <th>No. Transaksi</th>
                                <th>Barang</th>
                                <th>Kuantitas</th>
                                <th>Alasan</th>
                                <th>Tanggal</th>
                                <th>Pencatat</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-600 fw-bold">
                            @forelse($outgoingLogs as $log)
                                <tr>
                                    <td class="text-center text-muted fw-bold">{{ $outgoingLogs->firstItem() + $loop->index }}</td>
                                    <td>
                                        <span class="text-dark fw-bolder">{{ $log->transaction_number }}</span>
                                        @if($log->isReversed())
                                            <span class="badge badge-light-danger ms-1">Dikoreksi</span>
                                        @elseif($log->isReversal())
                                            <span class="badge badge-light-warning ms-1">Pembalik</span>
                                        @endif
                                    </td>
                                    <td>{{ $log->item?->name ?? 'Barang Telah Dihapus' }}</td>
                                    <td>
                                        @if($log->isReversal())
                                            <span class="badge badge-light-warning">+{{ $log->quantity }} {{ $log->item?->unit ?? 'Unit' }}</span>
                                        @else
                                            <span class="badge badge-light-danger">-{{ $log->quantity }} {{ $log->item?->unit ?? 'Unit' }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $log->reason }}</td>
                                    <td>{{ $log->exit_date->format('d/m/Y') }}</td>
                                    <td>{{ $log->user?->name ?? 'Sistem' }}</td>
                                    <td class="text-end">
                                        @if(! $log->isReversed() && ! $log->isReversal())
                                            <button type="button" class="btn btn-sm btn-icon btn-light-danger" data-bs-toggle="modal" data-bs-target="#reverseOutgoingModal{{ $log->id }}" title="Koreksi Transaksi" aria-label="Koreksi Transaksi">
                                                <i class="bi bi-arrow-counterclockwise"></i>
                                            </button>

                                            <div class="modal fade text-start" id="reverseOutgoingModal{{ $log->id }}" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <form action="{{ route('sarpras.logistics.outgoing.reverse', $log) }}" method="POST" class="modal-content">
                                                        @csrf
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Koreksi Transaksi {{ $log->transaction_number }}</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <p class="text-gray-700 fs-7">
                                                                Koreksi akan membuat transaksi pembalik untuk mengembalikan stok <strong>{{ $log->item?->name }}</strong> sebanyak <strong>{{ $log->quantity }} {{ $log->item?->unit }}</strong>.
                                                            </p>
                                                            <div class="mb-3">
                                                                <label class="form-label required fw-bold fs-7">Alasan Koreksi</label>
                                                                <input type="text" name="reason" class="form-control form-control-sm form-control-solid" required placeholder="Contoh: Pengeluaran dibatalkan panitia / salah catat">
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Batal</button>
                                                            <button type="submit" class="btn btn-sm btn-danger">Simpan Koreksi</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        @else
                                            <span class="text-muted fs-8">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="text-center text-muted py-6">Belum ada catatan barang keluar.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $outgoingLogs->withQueryString()->links() }}</div>
            </div>
        </div>
    </div>
</div>
@endsection
