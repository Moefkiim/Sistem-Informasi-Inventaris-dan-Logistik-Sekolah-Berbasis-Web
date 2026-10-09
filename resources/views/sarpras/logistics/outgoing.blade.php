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
                    @if($errors->any())
                        <div class="alert alert-danger p-3 mb-4">
                            @foreach($errors->all() as $err)<div>{{ $err }}</div>@endforeach
                        </div>
                    @endif

                    <div class="mb-4">
                        <label class="form-label fw-bold required">Barang Inventaris (Stok > 0)</label>
                        <select name="item_id" class="form-select form-select-solid" required>
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

                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-7 gy-4">
                        <thead>
                            <tr class="text-start text-muted fw-bolder fs-7 text-uppercase gs-0">
                                <th>No. Transaksi</th>
                                <th>Barang</th>
                                <th>Kuantitas</th>
                                <th>Alasan</th>
                                <th>Tanggal</th>
                                <th>Pencatat</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-600 fw-bold">
                            @forelse($outgoingLogs as $log)
                                <tr>
                                    <td><span class="text-dark fw-bolder">{{ $log->transaction_number }}</span></td>
                                    <td>{{ $log->item?->name ?? 'Barang Telah Dihapus' }}</td>
                                    <td><span class="badge badge-light-danger">-{{ $log->quantity }} {{ $log->item?->unit ?? 'Unit' }}</span></td>
                                    <td>{{ $log->reason }}</td>
                                    <td>{{ $log->exit_date->format('d/m/Y') }}</td>
                                    <td>{{ $log->user?->name ?? 'Sistem' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted py-6">Belum ada catatan barang keluar.</td></tr>
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
