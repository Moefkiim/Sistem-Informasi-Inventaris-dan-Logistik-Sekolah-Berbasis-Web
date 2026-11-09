@extends('layouts.app')

@section('title', 'Master Inventaris Sarpras')
@section('header-title', 'Master Data Inventaris Barang')

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <h3 class="fw-bolder">Master Data Inventaris Barang</h3>
        </div>
        <div class="card-toolbar">
            <a href="{{ route('sarpras.inventory.import.create') }}" class="btn btn-light-primary me-2">
                <i class="bi bi-upload me-1"></i> Impor Inventaris
            </a>
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

        {{-- Toolbar Pencarian & Filter --}}
        <form action="{{ route('sarpras.inventory.index') }}" method="GET" class="card bg-light p-5 border-0 mb-6">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fs-7 fw-bold text-gray-700">Pencarian</label>
                    <div class="position-relative">
                        <i class="bi bi-search position-absolute top-50 translate-middle-y ms-3 text-gray-500"></i>
                        <input type="text" name="search" class="form-control form-control-solid ps-10 form-control-sm" placeholder="Nama, kode, no seri..." value="{{ $search }}">
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label fs-7 fw-bold text-gray-700">Jurusan</label>
                    <select name="department" class="form-select form-select-solid form-select-sm">
                        <option value="">Semua Jurusan</option>
                        @foreach(config('departments') as $code => $name)
                            <option value="{{ $code }}" {{ $department === $code ? 'selected' : '' }}>{{ $code }} - {{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fs-7 fw-bold text-gray-700">Kategori</label>
                    <select name="category" class="form-select form-select-solid form-select-sm">
                        <option value="">Semua Kategori</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat }}" {{ $category === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fs-7 fw-bold text-gray-700">Status Aset</label>
                    <select name="status" class="form-select form-select-solid form-select-sm">
                        <option value="">Semua Status</option>
                        <option value="aktif" {{ $status === 'aktif' ? 'selected' : '' }}>Aktif</option>
                        <option value="dipinjam" {{ $status === 'dipinjam' ? 'selected' : '' }}>Dipinjam</option>
                        <option value="dalam_perbaikan" {{ $status === 'dalam_perbaikan' ? 'selected' : '' }}>Dalam Perbaikan</option>
                        <option value="tidak_aktif" {{ $status === 'tidak_aktif' ? 'selected' : '' }}>Tidak Aktif</option>
                        <option value="disposed" {{ $status === 'disposed' ? 'selected' : '' }}>Dihapuskan</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fs-7 fw-bold text-gray-700">Rentang Tanggal Registrasi</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-0"><i class="bi bi-calendar-range text-gray-500"></i></span>
                        <input type="text" class="form-control form-control-solid js-daterangepicker" placeholder="Semua tanggal" value="{{ $startDate && $endDate ? date('d/m/Y', strtotime($startDate)) . ' - ' . date('d/m/Y', strtotime($endDate)) : '' }}" readonly>
                        <input type="hidden" name="start_date" value="{{ $startDate }}">
                        <input type="hidden" name="end_date" value="{{ $endDate }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label fs-7 fw-bold text-gray-700">Lokasi Penempatan</label>
                    <select name="location_id" class="form-select form-select-solid form-select-sm">
                        <option value="">Semua Lokasi</option>
                        @foreach($locations as $loc)
                            <option value="{{ $loc->id }}" {{ (string)$locationId === (string)$loc->id ? 'selected' : '' }}>{{ $loc->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fs-7 fw-bold text-gray-700">Kondisi</label>
                    <select name="condition" class="form-select form-select-solid form-select-sm">
                        <option value="">Semua Kondisi</option>
                        <option value="baik" {{ $condition === 'baik' ? 'selected' : '' }}>Baik</option>
                        <option value="rusak_ringan" {{ $condition === 'rusak_ringan' ? 'selected' : '' }}>Rusak Ringan</option>
                        <option value="rusak_berat" {{ $condition === 'rusak_berat' ? 'selected' : '' }}>Rusak Berat</option>
                    </select>
                </div>
                <div class="col-md-7 d-flex justify-content-end gap-2">
                    @if($search || $department || $category || $condition || $status || $locationId || $stok || $startDate || $endDate)
                        <a href="{{ route('sarpras.inventory.index') }}" class="btn btn-sm btn-light">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Filter
                        </a>
                    @endif
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-filter me-1"></i> Terapkan Filter
                    </button>
                </div>
            </div>
        </form>

        @if(request('stok') || request('status') || request('department'))
            <div class="d-flex align-items-center gap-2 mb-4">
                <span class="text-muted fs-7">Filter aktif:</span>
                @if(request('stok'))
                    <span class="badge badge-light-{{ request('stok') === 'habis' ? 'danger' : 'warning' }} d-inline-flex align-items-center">
                        Stok: {{ ucfirst(request('stok')) }}
                        <a href="{{ request()->fullUrlWithQuery(['stok' => null]) }}" class="ms-1 text-muted text-hover-dark text-decoration-none">×</a>
                    </span>
                @endif
                @if(request('status'))
                    <span class="badge badge-light-info d-inline-flex align-items-center">
                        Status: {{ str_replace('_', ' ', ucfirst(request('status'))) }}
                        <a href="{{ request()->fullUrlWithQuery(['status' => null]) }}" class="ms-1 text-muted text-hover-dark text-decoration-none">×</a>
                    </span>
                @endif
                @if(request('department'))
                    <span class="badge badge-light-primary d-inline-flex align-items-center">
                        Jurusan: {{ request('department') }}
                        <a href="{{ request()->fullUrlWithQuery(['department' => null]) }}" class="ms-1 text-muted text-hover-dark text-decoration-none">×</a>
                    </span>
                @endif
                <a href="{{ route('sarpras.inventory.index') }}" class="btn btn-link btn-color-muted btn-active-color-primary fs-7 p-0 ms-2">Reset Filter</a>
            </div>
        @endif

        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5">
                <thead>
                    <tr class="text-start text-muted fw-bolder fs-7 text-uppercase gs-0">
                        <th class="w-50px text-center">No</th>
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
                            <td class="text-center text-gray-700 fw-bold">{{ $items->firstItem() + $loop->index }}</td>
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
                            <td colspan="9">
                                <x-empty-state
                                    icon="bi-box-seam"
                                    title="Belum ada master barang inventaris"
                                    description="Tambahkan barang baru melalui menu Inventaris." />
                            </td>
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
