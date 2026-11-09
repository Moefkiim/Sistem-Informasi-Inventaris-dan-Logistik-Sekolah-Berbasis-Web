@extends('layouts.app')

@section('title', 'Master Lokasi')
@section('header-title', 'Master Data Lokasi Inventaris')

@section('content')
<div class="row g-5">
    <div class="col-xl-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header pt-6">
                <h4 class="fw-bolder">Tambah Lokasi Baru</h4>
            </div>
            <form action="{{ route('sarpras.locations.store') }}" method="POST" data-disable-on-submit>
                @csrf
                <div class="card-body">

                    <div class="mb-4">
                        <label class="form-label fw-bold required">Kode Lokasi (Unik)</label>
                        <input type="text" name="code" class="form-control form-control-solid font-monospace" value="{{ old('code') }}" placeholder="LAB-RPL-01 / GDG-A" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold required">Nama Ruangan / Lokasi</label>
                        <input type="text" name="name" class="form-control form-control-solid" value="{{ old('name') }}" placeholder="Contoh: Lab Komputer RPL 1" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Nama Gedung</label>
                        <input type="text" name="building" class="form-control form-control-solid" value="{{ old('building') }}" placeholder="Gedung A Lantai 2">
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Jurusan Pemakai (Opsional)</label>
                        <select name="department" class="form-select form-select-solid">
                            <option value="">-- Umum / Tidak Dikunci --</option>
                            @foreach(config('departments') as $code => $name)
                                <option value="{{ $code }}" {{ old('department') == $code ? 'selected' : '' }}>{{ $name }} ({{ $code }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Keterangan</label>
                        <textarea name="description" class="form-control form-control-solid" rows="2">{{ old('description') }}</textarea>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-geo-alt me-1"></i> Simpan Lokasi
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="col-xl-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header border-0 pt-6">
                <h3 class="fw-bolder">Daftar Lokasi & Ruangan</h3>
            </div>
            <div class="card-body pt-0">
                @if(session('success'))
                    <div class="alert alert-success p-3 mb-4">{{ session('success') }}</div>
                @endif

                {{-- Toolbar Pencarian & Filter --}}
                <form action="{{ route('sarpras.locations.index') }}" method="GET" class="card bg-light p-4 border-0 mb-5">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label fs-7 fw-bold text-gray-700">Pencarian</label>
                            <div class="position-relative">
                                <i class="bi bi-search position-absolute top-50 translate-middle-y ms-3 text-gray-500"></i>
                                <input type="text" name="search" class="form-control form-control-solid ps-10 form-control-sm" placeholder="Nama, kode, gedung..." value="{{ $search }}">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fs-7 fw-bold text-gray-700">Jurusan</label>
                            <select name="department" class="form-select form-select-solid form-select-sm">
                                <option value="">Semua Jurusan</option>
                                @foreach(config('departments') as $code => $name)
                                    <option value="{{ $code }}" {{ $department === $code ? 'selected' : '' }}>{{ $code }} - {{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fs-7 fw-bold text-gray-700">Rentang Tanggal Dibuat</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white border-0"><i class="bi bi-calendar-range text-gray-500"></i></span>
                                <input type="text" class="form-control form-control-solid js-daterangepicker" placeholder="Semua tanggal" value="{{ $startDate && $endDate ? date('d/m/Y', strtotime($startDate)) . ' - ' . date('d/m/Y', strtotime($endDate)) : '' }}" readonly>
                                <input type="hidden" name="start_date" value="{{ $startDate }}">
                                <input type="hidden" name="end_date" value="{{ $endDate }}">
                            </div>
                        </div>
                        <div class="col-12 d-flex justify-content-end gap-2 mt-2">
                            @if($search || $department || $startDate || $endDate)
                                <a href="{{ route('sarpras.locations.index') }}" class="btn btn-sm btn-light">
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
                                <th>Kode</th>
                                <th>Nama Lokasi</th>
                                <th>Gedung</th>
                                <th>Jurusan</th>
                                <th>Total Barang</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-600 fw-bold">
                            @forelse($locations as $loc)
                                <tr>
                                    <td><span class="badge badge-light-dark font-monospace">{{ $loc->code }}</span></td>
                                    <td class="text-dark fw-bolder">{{ $loc->name }}</td>
                                    <td>{{ $loc->building ?: '-' }}</td>
                                    <td>{{ $loc->department ?: 'Umum' }}</td>
                                    <td>
                                        <span class="badge badge-light-primary">{{ $loc->items_count }} Jenis</span>
                                        @if($loc->asset_units_count > 0)
                                            <span class="badge badge-light-info ms-1">{{ $loc->asset_units_count }} Unit</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        @if($loc->items_count === 0 && $loc->asset_units_count === 0)
                                            <form action="{{ route('sarpras.locations.destroy', $loc) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-icon btn-light-danger" data-confirm-delete="Apakah Anda yakin ingin menghapus master lokasi {{ $loc->name }}?">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        @else
                                            <span class="text-muted fs-8">Sedang dipakai</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted py-6">Belum ada data master lokasi.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $locations->withQueryString()->links() }}</div>
            </div>
        </div>
    </div>
</div>
@endsection
