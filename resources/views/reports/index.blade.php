@extends('layouts.app')

@section('title', 'Laporan & Rekapitulasi')
@section('header-title', 'Laporan Inventaris & Logistik')

@section('content')
<div class="card border-0 shadow-sm mb-5">
    <div class="card-body p-6">
        <form action="{{ route('reports.index') }}" method="GET" class="row g-4 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-bold">Jenis Laporan</label>
                <select name="type" class="form-select form-select-solid" onchange="this.form.submit()">
                    <option value="inventory" {{ $type === 'inventory' ? 'selected' : '' }}>Rekap Inventaris Barang</option>
                    <option value="incoming" {{ $type === 'incoming' ? 'selected' : '' }}>Log Barang Masuk</option>
                    <option value="outgoing" {{ $type === 'outgoing' ? 'selected' : '' }}>Log Barang Keluar</option>
                    <option value="distribution" {{ $type === 'distribution' ? 'selected' : '' }}>Log Distribusi / Penyaluran</option>
                    <option value="submission" {{ $type === 'submission' ? 'selected' : '' }}>Riwayat Pengajuan</option>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label fw-bold">Filter Rentang Waktu (Date Range)</label>
                <div class="input-group">
                    <input type="date" name="start_date" class="form-control form-control-solid" value="{{ $startDate }}">
                    <span class="input-group-text bg-light border-0">s/d</span>
                    <input type="date" name="end_date" class="form-control form-control-solid" value="{{ $endDate }}">
                </div>
            </div>

            @if(!auth()->user()->isKajur())
                <div class="col-md-3">
                    <label class="form-label fw-bold">Filter Jurusan</label>
                    <input type="text" name="department" class="form-control form-control-solid" placeholder="Semua Jurusan" value="{{ $department }}">
                </div>
            @endif

            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-filter me-1"></i> Filter
                </button>
                <a href="{{ route('reports.index', array_merge(request()->all(), ['export' => 'print'])) }}" target="_blank" class="btn btn-light-success flex-shrink-0" title="Cetak / Export Laporan">
                    <i class="bi bi-printer me-1"></i> Cetak
                </a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header pt-6">
        <h3 class="fw-bolder">Hasil Laporan: {{ strtoupper($type) }}</h3>
    </div>
    <div class="card-body pt-0">
        <div class="table-responsive">
            @if($type === 'inventory')
                <table class="table align-middle table-row-dashed fs-7 gy-4">
                    <thead class="bg-light fw-bold text-muted text-uppercase">
                        <tr><th>Kode</th><th>Nama Barang</th><th>Kategori</th><th>Stok</th><th>Kondisi</th><th>Lokasi</th><th>Jurusan</th></tr>
                    </thead>
                    <tbody class="fw-bold text-gray-700">
                        @forelse($data as $row)
                            <tr>
                                <td>{{ $row->code }}</td>
                                <td>{{ $row->name }}</td>
                                <td>{{ $row->category }}</td>
                                <td>{{ $row->stock }} {{ $row->unit }}</td>
                                <td>{{ strtoupper(str_replace('_', ' ', $row->current_condition)) }}</td>
                                <td>{{ $row->location?->name ?: '-' }}</td>
                                <td>{{ $row->department ?: 'Umum' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-6">Tidak ada data untuk periode ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @elseif($type === 'incoming')
                <table class="table align-middle table-row-dashed fs-7 gy-4">
                    <thead class="bg-light fw-bold text-muted text-uppercase">
                        <tr><th>No. Transaksi</th><th>Nama Barang</th><th>Jumlah Masuk</th><th>Asal Sumber</th><th>Tanggal</th><th>Pencatat</th></tr>
                    </thead>
                    <tbody class="fw-bold text-gray-700">
                        @forelse($data as $row)
                            <tr>
                                <td>{{ $row->transaction_number }}</td>
                                <td>{{ $row->item->name }}</td>
                                <td><span class="badge badge-light-success">+{{ $row->quantity }} {{ $row->item->unit }}</span></td>
                                <td>{{ $row->source_origin ?: $row->source }}</td>
                                <td>{{ $row->entry_date->format('d/m/Y') }}</td>
                                <td>{{ $row->user->name }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-6">Tidak ada data barang masuk.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @elseif($type === 'outgoing')
                <table class="table align-middle table-row-dashed fs-7 gy-4">
                    <thead class="bg-light fw-bold text-muted text-uppercase">
                        <tr><th>No. Transaksi</th><th>Nama Barang</th><th>Jumlah Keluar</th><th>Alasan</th><th>Tanggal</th><th>Pencatat</th></tr>
                    </thead>
                    <tbody class="fw-bold text-gray-700">
                        @forelse($data as $row)
                            <tr>
                                <td>{{ $row->transaction_number }}</td>
                                <td>{{ $row->item->name }}</td>
                                <td><span class="badge badge-light-danger">-{{ $row->quantity }} {{ $row->item->unit }}</span></td>
                                <td>{{ $row->reason }}</td>
                                <td>{{ $row->exit_date->format('d/m/Y') }}</td>
                                <td>{{ $row->user->name }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-6">Tidak ada data barang keluar.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @elseif($type === 'distribution')
                <table class="table align-middle table-row-dashed fs-7 gy-4">
                    <thead class="bg-light fw-bold text-muted text-uppercase">
                        <tr><th>No. Distribusi</th><th>Barang</th><th>Jumlah</th><th>Lokasi Penempatan</th><th>Penerima</th><th>Tanggal</th></tr>
                    </thead>
                    <tbody class="fw-bold text-gray-700">
                        @forelse($data as $row)
                            <tr>
                                <td>{{ $row->distribution_number }}</td>
                                <td>{{ $row->item->name }}</td>
                                <td>{{ $row->quantity }} {{ $row->item->unit }}</td>
                                <td>{{ $row->toLocation->name }}</td>
                                <td>{{ $row->recipient_name ?: ($row->recipient_department ?: '-') }}</td>
                                <td>{{ $row->distribution_date->format('d/m/Y') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-6">Tidak ada data distribusi.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @elseif($type === 'submission')
                <table class="table align-middle table-row-dashed fs-7 gy-4">
                    <thead class="bg-light fw-bold text-muted text-uppercase">
                        <tr><th>No. Pengajuan</th><th>Pengaju</th><th>Jurusan</th><th>Judul</th><th>Status</th><th>Tanggal</th></tr>
                    </thead>
                    <tbody class="fw-bold text-gray-700">
                        @forelse($data as $row)
                            <tr>
                                <td>{{ $row->submission_number }}</td>
                                <td>{{ $row->user->name }}</td>
                                <td>{{ $row->department }}</td>
                                <td>{{ $row->title }}</td>
                                <td>{{ strtoupper(str_replace('_', ' ', $row->status)) }}</td>
                                <td>{{ $row->created_at->format('d/m/Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-6">Tidak ada riwayat pengajuan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @endif
        </div>
        <div class="mt-4">{{ $data->links() }}</div>
    </div>
</div>
@endsection
