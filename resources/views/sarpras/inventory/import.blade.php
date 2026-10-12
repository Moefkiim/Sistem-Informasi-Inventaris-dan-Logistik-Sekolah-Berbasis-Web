@extends('layouts.app')

@section('title', 'Impor Inventaris')
@section('header-title', 'Impor Inventaris Awal')

@section('content')
<div class="row g-5">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header pt-6">
                <h3 class="fw-bolder">Unggah Berkas Impor</h3>
                <span class="text-muted">Format didukung: .xlsx atau .csv, maksimal 2 MB dan 2.000 baris.</span>
            </div>
            <form action="{{ route('sarpras.inventory.import.preview') }}" method="POST" enctype="multipart/form-data" data-disable-on-submit>
                @csrf
                <div class="card-body">
                    @error('file')
                        <div class="alert alert-danger">{{ $message }}</div>
                    @enderror

                    <div class="mb-5">
                        <label class="form-label fw-bold required">Berkas Inventaris</label>
                        <input type="file" name="file" accept=".xlsx,.csv" class="form-control form-control-solid" required>
                    </div>

                    <div class="alert alert-light-primary d-flex align-items-start">
                        <div>
                            <strong>Alur:</strong> unggah berkas &rarr; pratinjau &amp; validasi &rarr; konfirmasi.
                            Tidak ada data yang ditulis sebelum Anda menekan konfirmasi.
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-between">
                    <a href="{{ route('sarpras.inventory.index') }}" class="btn btn-light">Batal</a>
                    <button type="submit" class="btn btn-primary">Unggah &amp; Pratinjau</button>
                </div>
            </form>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header pt-6">
                <h3 class="fw-bolder">Template Impor</h3>
            </div>
            <div class="card-body">
                <p class="text-muted">
                    Gunakan template resmi agar kolom sesuai. Template memuat lembar <strong>Data</strong> (contoh baris)
                    dan <strong>Petunjuk</strong> (daftar kolom serta nilai validnya).
                </p>
                <a href="{{ route('sarpras.inventory.import.template') }}" class="btn btn-light-primary">
                    Unduh Template .xlsx
                </a>
                <hr>
                <ul class="text-muted fs-7 mb-0 ps-4">
                    <li>Satu baris untuk satu unit barang individual.</li>
                    <li>Baris dengan <em>Kode Barang</em> sama digabung menjadi satu barang dengan beberapa unit.</li>
                    <li>Kode barang dan nomor unit boleh dikosongkan agar dibuat otomatis.</li>
                    <li>Nomor seri / nomor unit yang sudah ada akan dilaporkan sebagai kesalahan.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
