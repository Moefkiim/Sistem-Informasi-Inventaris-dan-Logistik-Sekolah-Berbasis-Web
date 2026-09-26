@extends('layouts.app')

@section('title', 'Detail Inventaris - ' . $item->name)
@section('header-title', 'Detail Barang Inventaris')

@section('content')
<div class="card border-0 shadow-sm mb-5">
    <div class="card-header pt-6">
        <h3 class="fw-bolder">{{ $item->name }} ({{ $item->code }})</h3>
        <div class="card-toolbar">
            <span class="badge badge-light-primary fs-7 px-3 py-2 me-2">Jurusan: {{ $item->department }}</span>
            <span class="badge badge-light-success fs-7 px-3 py-2">Stok: {{ $item->stock }} {{ $item->unit }}</span>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-5 mb-5">
            <div class="col-md-4">
                <span class="text-muted fs-7">Kategori</span>
                <div class="fw-bold fs-6">{{ $item->category }}</div>
            </div>
            <div class="col-md-4">
                <span class="text-muted fs-7">Sumber Pengadaan</span>
                <div class="fw-bold fs-6 text-capitalize">{{ $item->source }}</div>
            </div>
            <div class="col-md-4">
                <span class="text-muted fs-7">Kondisi Terkini</span>
                <div class="fw-bold fs-6 text-capitalize">{{ str_replace('_', ' ', $item->current_condition) }}</div>
            </div>
            <div class="col-md-12">
                <span class="text-muted fs-7">Lokasi Saat Ini</span>
                <div class="fw-bold fs-6">{{ $item->location?->name ?: 'Belum ditetapkan' }} ({{ $item->location?->code }})</div>
            </div>
        </div>

        <div class="separator separator-dashed my-6"></div>

        <div class="row g-5">
            <div class="col-lg-6">
                <h4 class="fw-bolder mb-3"><i class="bi bi-geo-alt me-1 text-primary"></i> Riwayat Perpindahan Lokasi</h4>
                <div class="table-responsive">
                    <table class="table table-sm table-row-bordered">
                        <thead>
                            <tr class="fw-bold text-muted fs-7">
                                <th>Dari Lokasi</th>
                                <th>Ke Lokasi</th>
                                <th>Waktu</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($item->locationHistories as $lh)
                                <tr>
                                    <td>{{ $lh->fromLocation?->name ?: '-' }}</td>
                                    <td><strong class="text-primary">{{ $lh->toLocation->name }}</strong></td>
                                    <td>{{ $lh->moved_at->format('d/m/Y H:i') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-muted">Belum ada riwayat mutasi lokasi.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="col-lg-6">
                <h4 class="fw-bolder mb-3"><i class="bi bi-activity me-1 text-warning"></i> Riwayat Perubahan Kondisi</h4>
                <div class="table-responsive">
                    <table class="table table-sm table-row-bordered">
                        <thead>
                            <tr class="fw-bold text-muted fs-7">
                                <th>Status Lama</th>
                                <th>Status Baru</th>
                                <th>Waktu</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($item->conditionHistories as $ch)
                                <tr>
                                    <td class="text-capitalize">{{ str_replace('_', ' ', $ch->from_condition) }}</td>
                                    <td class="text-capitalize fw-bold text-dark">{{ str_replace('_', ' ', $ch->to_condition) }}</td>
                                    <td>{{ $ch->recorded_at->format('d/m/Y H:i') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-muted">Belum ada riwayat pergantian kondisi.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="card-footer">
        <a href="{{ route('kajur.inventory.index') }}" class="btn btn-light">
            <i class="bi bi-arrow-left me-1"></i> Kembali ke Inventaris
        </a>
    </div>
</div>
@endsection
