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

            <div class="row g-5">
                <div class="col-md-4">
                    <label class="form-label fw-bold required">Kode Barang (Unik)</label>
                    <input type="text" name="code" class="form-control form-control-solid font-monospace" placeholder="INV-2026-001" value="{{ old('code') }}" required>
                </div>
                <div class="col-md-8">
                    <label class="form-label fw-bold required">Nama Barang</label>
                    <input type="text" name="name" class="form-control form-control-solid" placeholder="Nama barang inventaris" value="{{ old('name') }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold required">Kategori</label>
                    <input type="text" name="category" class="form-control form-control-solid" placeholder="Elektronik / Alat Praktik / Mebel" value="{{ old('category', 'Elektronik') }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold required">Satuan</label>
                    <input type="text" name="unit" class="form-control form-control-solid" value="{{ old('unit', 'Unit') }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold required">Stok Awal</label>
                    <input type="number" name="stock" class="form-control form-control-solid" min="0" value="{{ old('stock', 0) }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold required">Sumber Asal Barang</label>
                    <select name="source" class="form-select form-select-solid" required>
                        <option value="pembelian">Pembelian</option>
                        <option value="bantuan">Bantuan / Hibah</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Alokasi Jurusan (Kosongkan bila umum)</label>
                    <input type="text" name="department" class="form-control form-control-solid" placeholder="Contoh: Rekayasa Perangkat Lunak" value="{{ old('department') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Lokasi Penempatan Awal</label>
                    <select name="location_id" class="form-select form-select-solid">
                        <option value="">-- Belum Ditempatkan --</option>
                        @foreach($locations as $loc)
                            <option value="{{ $loc->id }}">{{ $loc->name }} ({{ $loc->code }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold required">Kondisi Fisik Awal</label>
                    <select name="current_condition" class="form-select form-select-solid" required>
                        <option value="baik">Baik</option>
                        <option value="rusak_ringan">Rusak Ringan</option>
                        <option value="rusak_berat">Rusak Berat</option>
                    </select>
                </div>
                <div class="col-md-8">
                    <label class="form-label fw-bold">Keterangan Tambahan</label>
                    <input type="text" name="description" class="form-control form-control-solid" placeholder="Catatan atau spesifikasi singkat" value="{{ old('description') }}">
                </div>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-end gap-3">
            <a href="{{ route('sarpras.inventory.index') }}" class="btn btn-light">Batal</a>
            <button type="submit" class="btn btn-primary">Simpan Master Barang</button>
        </div>
    </form>
</div>
@endsection
