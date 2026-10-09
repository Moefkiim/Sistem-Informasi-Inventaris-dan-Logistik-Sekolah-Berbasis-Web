@extends('layouts.app')

@section('title', 'Master Inventaris Sarpras')
@section('header-title', 'Master Data Inventaris Barang')

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <form action="{{ route('sarpras.inventory.index') }}" method="GET" class="d-flex align-items-center gap-2">
                <input type="text" name="search" class="form-control form-control-solid w-250px" placeholder="Cari nama/kode barang..." value="{{ request('search') }}">
                <button type="submit" class="btn btn-light-primary">Cari</button>
            </form>
        </div>
        <div class="card-toolbar">
            <a href="{{ route('sarpras.inventory.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> Registrasi Barang Baru
            </a>
        </div>
    </div>
    <div class="card-body pt-0">
        @if(session('success'))
            <div class="alert alert-success d-flex align-items-center p-4 mb-5">
                <i class="bi bi-check-circle fs-2 text-success me-3"></i>
                <div>{{ session('success') }}</div>
            </div>
        @endif

        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5">
                <thead>
                    <tr class="text-start text-muted fw-bolder fs-7 text-uppercase gs-0">
                        <th>Kode</th>
                        <th>Nama Barang</th>
                        <th>Kategori</th>
                        <th>Stok</th>
                        <th>Alokasi Jurusan</th>
                        <th>Lokasi</th>
                        <th>Kondisi</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody class="text-gray-600 fw-bold">
                    @forelse($items as $item)
                        <tr>
                            <td><span class="badge badge-light-dark font-monospace">{{ $item->code }}</span></td>
                            <td class="text-dark fw-bolder">{{ $item->name }}</td>
                            <td><span class="badge badge-light-secondary">{{ $item->category }}</span></td>
                            <td>
                                <span class="badge badge-light-{{ $item->isStockEmpty() ? 'danger' : ($item->isStockLow() ? 'warning' : 'primary') }}">
                                    {{ $item->stock }} {{ $item->unit }}
                                </span>
                                @if($item->isStockLow())
                                    <span class="badge badge-light-warning fs-9 ms-1" title="Minimum: {{ $item->minimum_stock }}">Menipis</span>
                                @elseif($item->isStockEmpty())
                                    <span class="badge badge-light-danger fs-9 ms-1">Habis</span>
                                @endif
                            </td>
                            <td>{{ $item->department ?: 'Umum / Sarpras' }}</td>
                            <td>{{ $item->location?->name ?: 'Belum Ada' }}</td>
                            <td>
                                <x-condition-badge :condition="$item->current_condition" />
                            </td>
                            <td class="text-end">
                                <a href="{{ route('sarpras.inventory.show', $item) }}" class="btn btn-sm btn-light btn-active-light-primary">
                                    Kelola & Riwayat
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-8">Belum ada master barang inventaris.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $items->withQueryString()->links() }}
        </div>
    </div>
</div>
@endsection
