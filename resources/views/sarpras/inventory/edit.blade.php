@extends('layouts.app')

@section('title', 'Edit Barang - ' . $item->name)
@section('header-title', 'Edit Master Data Barang')

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-header pt-6">
        <h3 class="fw-bolder">Edit Master Data: {{ $item->name }}</h3>
    </div>
    <form action="{{ route('sarpras.inventory.update', $item) }}" method="POST" data-disable-on-submit>
        @csrf
        @method('PUT')
        <div class="card-body">

            {{-- Bagian 1: Identitas & Informasi Terkunci --}}
            <h5 class="fw-bold text-primary mb-4 border-bottom pb-2">1. Identitas & Informasi Master</h5>
            <div class="row g-5 mb-7">
                <div class="col-md-3">
                    <label class="form-label fw-bold">Kode Barang (Terkunci)</label>
                    <input type="text" class="form-control form-control-solid font-monospace bg-light" value="{{ $item->code }}" readonly>
                    <div class="form-text fs-8 text-muted">Kode barang bersifat unik dan permanen untuk menjaga integritas riwayat.</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Tipe Barang (Terkunci)</label>
                    <input type="text" class="form-control form-control-solid bg-light" value="{{ $item->item_type === 'individual' ? 'Aset Individual' : 'Stok / Consumable' }}" readonly>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Stok Saat Ini (Terkunci)</label>
                    <div class="input-group">
                        <input type="text" class="form-control form-control-solid bg-light fw-bold text-primary" value="{{ $item->stock }}" readonly>
                        <span class="input-group-text bg-light border-0">{{ $item->unit }}</span>
                    </div>
                    <div class="form-text fs-8 text-muted">Perubahan stok hanya dapat dilakukan melalui menu Logistik.</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">No. Inventaris Aset</label>
                    <input type="text" name="inventory_number" class="form-control form-control-solid font-monospace @error('inventory_number') is-invalid @enderror"
                           placeholder="INV-RPL-LPT-001" value="{{ old('inventory_number', $item->inventory_number) }}">
                    @error('inventory_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold required">Nama Barang</label>
                    <input type="text" name="name" class="form-control form-control-solid @error('name') is-invalid @enderror"
                           placeholder="Nama barang inventaris" value="{{ old('name', $item->name) }}" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Merk / Produsen</label>
                    <input type="text" name="brand" class="form-control form-control-solid @error('brand') is-invalid @enderror"
                           placeholder="Lenovo, HP, Canon..." value="{{ old('brand', $item->brand) }}">
                    @error('brand')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Tipe / Model</label>
                    <input type="text" name="model" class="form-control form-control-solid @error('model') is-invalid @enderror"
                           placeholder="ThinkPad E15, LaserJet P1102..." value="{{ old('model', $item->model) }}">
                    @error('model')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Nomor Seri (Serial Number)</label>
                    <input type="text" name="serial_number" class="form-control form-control-solid font-monospace @error('serial_number') is-invalid @enderror"
                           placeholder="SN-ABC12345" value="{{ old('serial_number', $item->serial_number) }}">
                    @error('serial_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            {{-- Bagian 2: Klasifikasi & Pengadaan --}}
            <h5 class="fw-bold text-primary mb-4 border-bottom pb-2">2. Klasifikasi & Informasi Pengadaan</h5>
            <div class="row g-5 mb-7">
                <div class="col-md-3">
                    <label class="form-label fw-bold">Kategori</label>
                    <input type="text" name="category" class="form-control form-control-solid @error('category') is-invalid @enderror"
                           placeholder="Elektronik / Alat Praktik / Mebel" value="{{ old('category', $item->category) }}">
                    @error('category')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Satuan</label>
                    <input type="text" name="unit" class="form-control form-control-solid @error('unit') is-invalid @enderror"
                           value="{{ old('unit', $item->unit) }}">
                    @error('unit')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Stok Minimum Peringatan</label>
                    <input type="number" name="minimum_stock" class="form-control form-control-solid @error('minimum_stock') is-invalid @enderror"
                           min="0" value="{{ old('minimum_stock', $item->minimum_stock) }}">
                    @error('minimum_stock')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Alokasi Jurusan</label>
                    <select name="department" class="form-select form-select-solid @error('department') is-invalid @enderror">
                        <option value="">-- Umum / Tidak Dikunci --</option>
                        @foreach(config('departments') as $code => $name)
                            <option value="{{ $code }}" {{ old('department', $item->department) == $code ? 'selected' : '' }}>{{ $name }} ({{ $code }})</option>
                        @endforeach
                    </select>
                    @error('department')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Tahun Perolehan</label>
                    <input type="number" name="acquisition_year" class="form-control form-control-solid @error('acquisition_year') is-invalid @enderror"
                           min="1990" max="{{ date('Y') + 1 }}" value="{{ old('acquisition_year', $item->acquisition_year) }}">
                    @error('acquisition_year')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Harga Perolehan (Rp)</label>
                    <input type="number" name="acquisition_price" class="form-control form-control-solid @error('acquisition_price') is-invalid @enderror"
                           min="0" step="1000" value="{{ old('acquisition_price', $item->acquisition_price) }}">
                    @error('acquisition_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Keterangan / Spesifikasi</label>
                    <textarea name="description" class="form-control form-control-solid @error('description') is-invalid @enderror" rows="2">{{ old('description', $item->description) }}</textarea>
                    @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

        </div>
        <div class="card-footer d-flex justify-content-between align-items-center">
            <a href="{{ route('sarpras.inventory.show', $item) }}" class="btn btn-light">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Detail
            </a>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check2-circle me-1"></i> Simpan Perubahan
            </button>
        </div>
    </form>
</div>
@endsection

