@extends('layouts.app')

@section('title', 'Pratinjau Impor Inventaris')
@section('header-title', 'Pratinjau Impor Inventaris')

@section('content')
@php($hasErrors = count($analysis['errors']) > 0)

<div class="row g-5 mb-5">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <span class="text-muted fs-7">Barang akan dibuat</span>
                <div class="fw-bolder fs-2x">{{ $analysis['summary']['barang'] }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <span class="text-muted fs-7">Unit akan dibuat</span>
                <div class="fw-bolder fs-2x">{{ $analysis['summary']['unit'] }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <span class="text-muted fs-7">Kesalahan ditemukan</span>
                <div class="fw-bolder fs-2x {{ $hasErrors ? 'text-danger' : 'text-success' }}">{{ $analysis['summary']['errors'] }}</div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header pt-6 d-flex justify-content-between align-items-center">
        <div>
            <h3 class="fw-bolder mb-1">Berkas: {{ $originalName }}</h3>
            <span class="text-muted">Tinjau hasil validasi sebelum menyimpan.</span>
        </div>
        @if($hasErrors)
            <a href="{{ route('sarpras.inventory.import.errors', ['token' => $token]) }}" class="btn btn-light-danger">
                Unduh Daftar Kesalahan (CSV)
            </a>
        @endif
    </div>

    <div class="card-body">
        @if($hasErrors)
            <div class="alert alert-danger">
                Terdapat <strong>{{ $analysis['summary']['errors'] }}</strong> kesalahan. Perbaiki berkas lalu unggah ulang.
                Tombol konfirmasi dinonaktifkan sampai tidak ada kesalahan.
            </div>

            <div class="table-responsive" style="max-height: 420px;">
                <table class="table table-row-bordered table-row-gray-200 align-middle gs-4 gy-2">
                    <thead class="sticky-top bg-white">
                        <tr class="fw-bold text-muted">
                            <th style="width: 80px;">Baris</th>
                            <th style="width: 200px;">Kolom</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($analysis['errors'] as $error)
                            <tr>
                                <td>{{ $error['row'] ?: '-' }}</td>
                                <td class="fw-semibold">{{ $error['column'] }}</td>
                                <td class="text-danger">{{ $error['message'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="alert alert-success">
                Semua baris valid. {{ $analysis['summary']['barang'] }} barang dan {{ $analysis['summary']['unit'] }} unit siap dibuat.
            </div>

            <h5 class="fw-bold mb-3">Ringkasan Barang</h5>
            <div class="table-responsive" style="max-height: 360px;">
                <table class="table table-row-bordered table-row-gray-200 align-middle gs-4 gy-2">
                    <thead class="sticky-top bg-white">
                        <tr class="fw-bold text-muted">
                            <th>Kode</th>
                            <th>Nama</th>
                            <th>Tipe</th>
                            <th>Kategori</th>
                            <th>Jumlah Unit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($analysis['items'] as $group)
                            <tr>
                                <td class="font-monospace">{{ $group['data']['code'] ?: '(otomatis)' }}</td>
                                <td class="fw-semibold">{{ $group['data']['name'] }}</td>
                                <td>{{ $group['data']['item_type'] === 'individual' ? 'Individual' : 'Habis Pakai' }}</td>
                                <td>{{ $group['data']['category'] ?? 'Umum' }}</td>
                                <td>{{ $group['data']['item_type'] === 'individual' ? count($group['units']) : '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="card-footer d-flex justify-content-between">
        <a href="{{ route('sarpras.inventory.import.create') }}" class="btn btn-light">&larr; Unggah Berkas Lain</a>
        <form action="{{ route('sarpras.inventory.import.confirm') }}" method="POST" data-disable-on-submit>
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <button type="submit" class="btn btn-primary" @disabled($hasErrors)>
                Konfirmasi &amp; Impor {{ $analysis['summary']['barang'] }} Barang
            </button>
        </form>
    </div>
</div>
@endsection
