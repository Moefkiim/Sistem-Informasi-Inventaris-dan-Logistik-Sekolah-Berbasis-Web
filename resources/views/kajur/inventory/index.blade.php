@extends('layouts.app')

@section('title', 'Inventaris Jurusan ' . $department)
@section('header-title', 'Inventaris Jurusan ' . $department)

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <h3 class="fw-bolder">Daftar Barang & Sarana Milik Jurusan {{ $department }}</h3>
        </div>
    </div>
    <div class="card-body pt-0">
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5">
                <thead>
                    <tr class="text-start text-muted fw-bolder fs-7 text-uppercase gs-0">
                        <th>Kode Barang</th>
                        <th>Nama Barang</th>
                        <th>Kategori</th>
                        <th>Stok Tersedia</th>
                        <th>Lokasi Penempatan</th>
                        <th>Kondisi Fisik</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody class="text-gray-600 fw-bold">
                    @forelse($items as $item)
                        <tr>
                            <td><span class="badge badge-light-dark font-monospace">{{ $item->code }}</span></td>
                            <td class="text-dark fw-bolder">{{ $item->name }}</td>
                            <td>{{ $item->category }}</td>
                            <td>
                                <span class="badge badge-light-primary fs-7">{{ $item->stock }} {{ $item->unit }}</span>
                            </td>
                            <td>
                                <span class="text-gray-800">{{ $item->location?->name ?: 'Belum Ditempatkan' }}</span>
                            </td>
                            <td>
                                @if($item->current_condition === 'baik')
                                    <span class="badge badge-light-success">Baik</span>
                                @elseif($item->current_condition === 'rusak_ringan')
                                    <span class="badge badge-light-warning">Rusak Ringan</span>
                                @else
                                    <span class="badge badge-light-danger">Rusak Berat</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('kajur.inventory.show', $item) }}" class="btn btn-sm btn-light btn-active-light-primary">
                                    Histori & Detail
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-8">Belum ada barang inventaris yang teralokasi ke jurusan {{ $department }}.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $items->links() }}
        </div>
    </div>
</div>
@endsection
