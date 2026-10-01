@extends('layouts.app')

@section('title', 'Logistik Barang Masuk')
@section('header-title', 'Pencatatan Barang Masuk')

@section('content')
<div class="row g-5">
    <div class="col-xl-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header pt-6">
                <h4 class="fw-bolder">Catat Transaksi Masuk</h4>
            </div>
            <form action="{{ route('sarpras.logistics.incoming.store') }}" method="POST">
                @csrf
                <div class="card-body">
                    @if($errors->any())
                        <div class="alert alert-danger p-3 mb-4">
                            @foreach($errors->all() as $err)<div>{{ $err }}</div>@endforeach
                        </div>
                    @endif

                    <div class="mb-4">
                        <label class="form-label fw-bold required">Barang Inventaris</label>
                        <select name="item_id" class="form-select form-select-solid" required>
                            <option value="">-- Pilih Barang --</option>
                            @foreach($items as $it)
                                <option value="{{ $it->id }}">{{ $it->name }} (Stok Saat Ini: {{ $it->stock }} {{ $it->unit }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold required">Jumlah Masuk</label>
                        <input type="number" name="quantity" class="form-control form-control-solid" min="1" value="1" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold required">Sumber Asal</label>
                        <select name="source" class="form-select form-select-solid" required>
                            <option value="pembelian">Pembelian Sekolah</option>
                            <option value="bantuan">Bantuan / Hibah</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Keterangan Sumber (Toko / Instansi)</label>
                        <input type="text" name="source_origin" class="form-control form-control-solid" placeholder="Contoh: Toko Komputer XYZ / PT Telkom">
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold required">Tanggal Masuk</label>
                        <input type="date" name="entry_date" class="form-control form-control-solid" value="{{ date('Y-m-d') }}" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Catatan Log</label>
                        <textarea name="notes" class="form-control form-control-solid" rows="2" placeholder="Nomor faktur/surat pengantar..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-box-arrow-in-down me-1"></i> Simpan Transaksi Masuk
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="col-xl-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header border-0 pt-6">
                <h3 class="fw-bolder">Riwayat Log Barang Masuk</h3>
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
                                <th>Asal Sumber</th>
                                <th>Tanggal</th>
                                <th>Pencatat</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-600 fw-bold">
                            @forelse($incomingLogs as $log)
                                <tr>
                                    <td><span class="text-dark fw-bolder">{{ $log->transaction_number }}</span></td>
                                    <td>{{ $log->item->name }}</td>
                                    <td><span class="badge badge-light-success">+{{ $log->quantity }} {{ $log->item->unit }}</span></td>
                                    <td>{{ $log->source_origin ?: $log->source }}</td>
                                    <td>{{ $log->entry_date->format('d/m/Y') }}</td>
                                    <td>{{ $log->user->name }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted py-6">Belum ada catatan barang masuk.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $incomingLogs->withQueryString()->links() }}</div>
            </div>
        </div>
    </div>
</div>
@endsection
