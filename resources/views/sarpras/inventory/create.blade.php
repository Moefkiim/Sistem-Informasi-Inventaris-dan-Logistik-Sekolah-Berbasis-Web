@extends('layouts.app')

@section('title', 'Registrasi Barang Inventaris')
@section('header-title', 'Form Registrasi Barang Baru')

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-header pt-6">
        <h3 class="fw-bolder">Master Registrasi Barang Inventaris</h3>
    </div>
    <form action="{{ route('sarpras.inventory.store') }}" method="POST" data-disable-on-submit>
        @csrf
        <div class="card-body">
            @if($errors->any())
                <div class="alert alert-danger p-4 mb-5">
                    @foreach($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            {{-- Bagian 1: Identitas Barang --}}
            <h5 class="fw-bold text-primary mb-4 border-bottom pb-2">1. Identitas Barang</h5>
            <div class="row g-5 mb-7">
                <div class="col-md-3">
                    <label class="form-label fw-bold required">Kode Barang (Unik)</label>
                    <input type="text" name="code" class="form-control form-control-solid font-monospace @error('code') is-invalid @enderror"
                           placeholder="INV-2026-001" value="{{ old('code') }}" required>
                    @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">No. Inventaris (Nomor Aset Sekolah)</label>
                    <input type="text" name="inventory_number" class="form-control form-control-solid font-monospace @error('inventory_number') is-invalid @enderror"
                           placeholder="INV-RPL-LPT-001" value="{{ old('inventory_number') }}">
                    @error('inventory_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold required">Nama Barang</label>
                    <input type="text" name="name" class="form-control form-control-solid @error('name') is-invalid @enderror"
                           placeholder="Nama barang inventaris" value="{{ old('name') }}" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Merk / Produsen</label>
                    <input type="text" name="brand" class="form-control form-control-solid"
                           placeholder="Lenovo, HP, Canon..." value="{{ old('brand') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Tipe / Model</label>
                    <input type="text" name="model" class="form-control form-control-solid"
                           placeholder="ThinkPad E15, LaserJet P1102..." value="{{ old('model') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Nomor Seri (Serial Number)</label>
                    <input type="text" name="serial_number" class="form-control form-control-solid font-monospace"
                           placeholder="SN-ABC12345" value="{{ old('serial_number') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold required">Tipe Barang</label>
                    <select name="item_type" id="item_type" class="form-select form-select-solid @error('item_type') is-invalid @enderror" required>
                        <option value="consumable" {{ old('item_type', 'consumable') === 'consumable' ? 'selected' : '' }}>Stok / Consumable (Kertas, Tinta, dll)</option>
                        <option value="individual" {{ old('item_type') === 'individual' ? 'selected' : '' }}>Aset Individual (Laptop, Proyektor, dll)</option>
                    </select>
                    @error('item_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            {{-- Bagian 2: Klasifikasi --}}
            <h5 class="fw-bold text-primary mb-4 border-bottom pb-2">2. Klasifikasi & Sumber</h5>
            <div class="row g-5 mb-7">
                <div class="col-md-3">
                    <label class="form-label fw-bold required">Kategori</label>
                    <input type="text" name="category" class="form-control form-control-solid"
                           placeholder="Elektronik / Alat Praktik / Mebel" value="{{ old('category', 'Elektronik') }}" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold required">Satuan</label>
                    <input type="text" name="unit" class="form-control form-control-solid"
                           value="{{ old('unit', 'Unit') }}" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold required">Stok Awal</label>
                    <input type="number" name="stock" class="form-control form-control-solid"
                           min="0" value="{{ old('stock', 0) }}" required>
                </div>
                <div class="col-md-2" id="min_stock_group">
                    <label class="form-label fw-bold">Stok Minimum</label>
                    <input type="number" name="minimum_stock" class="form-control form-control-solid"
                           min="0" value="{{ old('minimum_stock', 0) }}"
                           title="Batas stok minimum untuk peringatan. Gunakan untuk barang consumable.">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold required">Sumber Asal Barang</label>
                    <select name="source" class="form-select form-select-solid" required>
                        <option value="pembelian">Pembelian</option>
                        <option value="bantuan">Bantuan / Hibah</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold">Tahun Perolehan</label>
                    <input type="number" name="acquisition_year" class="form-control form-control-solid"
                           min="1990" max="{{ date('Y') }}" placeholder="{{ date('Y') }}" value="{{ old('acquisition_year') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Harga Perolehan (Rp)</label>
                    <input type="number" name="acquisition_price" class="form-control form-control-solid"
                           min="0" step="1000" placeholder="0" value="{{ old('acquisition_price') }}">
                </div>
            </div>

            {{-- Bagian 3: Penempatan & Kondisi --}}
            <h5 class="fw-bold text-primary mb-4 border-bottom pb-2">3. Penempatan & Kondisi</h5>
            <div class="row g-5">
                <div class="col-md-4">
                    <label class="form-label fw-bold">Alokasi Jurusan (Kosongkan bila umum)</label>
                    <input type="text" name="department" class="form-control form-control-solid"
                           placeholder="Contoh: Rekayasa Perangkat Lunak" value="{{ old('department') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Lokasi Penempatan Awal</label>
                    <select name="location_id" class="form-select form-select-solid">
                        <option value="">-- Belum Ditempatkan --</option>
                        @foreach($locations as $loc)
                            <option value="{{ $loc->id }}" {{ old('location_id') == $loc->id ? 'selected' : '' }}>
                                {{ $loc->name }} ({{ $loc->code }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold required">Kondisi Fisik Awal</label>
                    <select name="current_condition" class="form-select form-select-solid" required>
                        <option value="baik" {{ old('current_condition', 'baik') === 'baik' ? 'selected' : '' }}>Baik</option>
                        <option value="rusak_ringan" {{ old('current_condition') === 'rusak_ringan' ? 'selected' : '' }}>Rusak Ringan</option>
                        <option value="rusak_berat" {{ old('current_condition') === 'rusak_berat' ? 'selected' : '' }}>Rusak Berat</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label fw-bold">Keterangan Tambahan</label>
                    <textarea name="description" class="form-control form-control-solid" rows="2"
                              placeholder="Catatan atau spesifikasi singkat">{{ old('description') }}</textarea>
                </div>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-end gap-3">
            <a href="{{ route('sarpras.inventory.index') }}" class="btn btn-light">Batal</a>
            <button type="submit" class="btn btn-primary">Simpan Master Barang</button>
        </div>
    </form>
</div>

<script>
// Tampilkan/sembunyikan stok minimum berdasarkan tipe barang
document.getElementById('item_type').addEventListener('change', function () {
    const minStockGroup = document.getElementById('min_stock_group');
    minStockGroup.style.display = this.value === 'consumable' ? '' : 'none';
});
// Trigger on load
document.getElementById('item_type').dispatchEvent(new Event('change'));
</script>
@endsection
