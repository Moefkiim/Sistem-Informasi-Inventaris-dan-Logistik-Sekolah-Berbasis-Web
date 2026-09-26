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
            <form action="{{ route('sarpras.locations.store') }}" method="POST">
                @csrf
                <div class="card-body">
                    @if($errors->any())
                        <div class="alert alert-danger p-3 mb-4">
                            @foreach($errors->all() as $err)<div>{{ $err }}</div>@endforeach
                        </div>
                    @endif

                    <div class="mb-4">
                        <label class="form-label fw-bold required">Kode Lokasi (Unik)</label>
                        <input type="text" name="code" class="form-control form-control-solid font-monospace" placeholder="LAB-RPL-01 / GDG-A" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold required">Nama Ruangan / Lokasi</label>
                        <input type="text" name="name" class="form-control form-control-solid" placeholder="Contoh: Lab Komputer RPL 1" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Nama Gedung</label>
                        <input type="text" name="building" class="form-control form-control-solid" placeholder="Gedung A Lantai 2">
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Jurusan Pemakai (Opsional)</label>
                        <input type="text" name="department" class="form-control form-control-solid" placeholder="Rekayasa Perangkat Lunak">
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Keterangan</label>
                        <textarea name="description" class="form-control form-control-solid" rows="2"></textarea>
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
                                    <td><span class="badge badge-light-primary">{{ $loc->items_count }} Jenis</span></td>
                                    <td class="text-end">
                                        @if($loc->items_count === 0)
                                            <form action="{{ route('sarpras.locations.destroy', $loc) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-icon btn-light-danger" onclick="return confirm('Hapus lokasi ini?')">
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
                <div class="mt-4">{{ $locations->links() }}</div>
            </div>
        </div>
    </div>
</div>
@endsection
