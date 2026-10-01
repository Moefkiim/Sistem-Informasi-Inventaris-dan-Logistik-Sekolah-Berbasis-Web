@extends('layouts.app')

@section('title', 'Dokumen Terlampir')
@section('header-title', 'Manajemen Dokumen Terlampir')

@section('content')
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center mb-6 gap-3">
    <div>
        <h2 class="fw-bolder mb-1">Manajemen Dokumen</h2>
        <p class="text-muted fs-7 mb-0">Daftar berkas pendukung, nota pengadaan, BAST, dan dokumen inventaris.</p>
    </div>
    @if(!auth()->user()->isKepalaSekolah())
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
            <i class="bi bi-cloud-arrow-up me-2"></i> Unggah Dokumen
        </button>
    @endif
</div>

<!-- Filter Kategori -->
<div class="card border-0 shadow-sm mb-5">
    <div class="card-body py-4 d-flex flex-wrap align-items-center gap-2">
        <span class="text-muted fw-bold me-2 fs-7">Filter Kategori:</span>
        <a href="{{ route('documents.index') }}" class="btn btn-sm {{ !request('category') ? 'btn-primary' : 'btn-light' }}">Semua</a>
        @foreach(['Nota', 'BAST', 'Surat Bantuan', 'Foto', 'Umum'] as $cat)
            <a href="{{ route('documents.index', ['category' => $cat]) }}" class="btn btn-sm {{ request('category') == $cat ? 'btn-primary' : 'btn-light' }}">{{ $cat }}</a>
        @endforeach
    </div>
</div>

<!-- Tabel Dokumen -->
<div class="card border-0 shadow-sm">
    <div class="card-body pt-4">
        @if(session('success'))
            <div class="alert alert-success d-flex align-items-center p-4 mb-5">
                <i class="bi bi-check-circle fs-2 text-success me-3"></i>
                <div>{{ session('success') }}</div>
            </div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger p-4 mb-5">
                @foreach($errors->all() as $err)<div>{{ $err }}</div>@endforeach
            </div>
        @endif

        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-7 gy-4">
                <thead>
                    <tr class="text-start text-muted fw-bolder fs-7 text-uppercase gs-0">
                        <th>Judul Dokumen</th>
                        <th>Kategori</th>
                        <th>Jurusan</th>
                        <th>Pengunggah</th>
                        <th>Ukuran</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody class="text-gray-600 fw-bold">
                    @forelse($documents as $doc)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="symbol symbol-40px me-3">
                                        <span class="symbol-label bg-light-primary text-primary">
                                            <i class="bi bi-file-earmark-text fs-3"></i>
                                        </span>
                                    </div>
                                    <div>
                                        <div class="text-dark fw-bolder">{{ $doc->title }}</div>
                                        <div class="text-muted fs-8">{{ $doc->file_name }}</div>
                                    </div>
                                </div>
                            </td>
                            <td><span class="badge badge-light-info">{{ $doc->category }}</span></td>
                            <td><span class="badge badge-light-secondary">{{ $doc->department ?: 'Umum' }}</span></td>
                            <td>{{ $doc->user->name ?? '-' }}</td>
                            <td>{{ number_format($doc->file_size / 1024, 1) }} KB</td>
                            <td class="text-end">
                                <a href="{{ route('documents.download', $doc) }}" class="btn btn-sm btn-light-primary me-2">
                                    <i class="bi bi-download me-1"></i> Unduh
                                </a>
                                @if(auth()->user()->isSarpras())
                                    <form action="{{ route('documents.destroy', $doc) }}" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus dokumen ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-light-danger">
                                            <i class="bi bi-trash me-1"></i> Hapus
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-8">Belum ada dokumen yang diunggah.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $documents->withQueryString()->links() }}
        </div>
    </div>
</div>

<!-- Modal Unggah Dokumen -->
@if(!auth()->user()->isKepalaSekolah())
<div class="modal fade" id="uploadModal" tabindex="-1" aria-labelledby="uploadModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bolder" id="uploadModalLabel">Unggah Dokumen Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('documents.store') }}" method="POST" enctype="multipart/form-data" data-disable-on-submit>
                @csrf
                <div class="modal-body">
                    <div class="mb-4">
                        <label class="form-label fw-bold required">Judul Dokumen</label>
                        <input type="text" name="title" required placeholder="Contoh: BAST Pengadaan Lab RPL 2026" class="form-control form-control-solid" value="{{ old('title') }}">
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold required">Kategori</label>
                        <select name="category" required class="form-select form-select-solid">
                            <option value="Nota" {{ old('category') == 'Nota' ? 'selected' : '' }}>Nota / Kwitansi</option>
                            <option value="BAST" {{ old('category') == 'BAST' ? 'selected' : '' }}>BAST (Berita Acara Serah Terima)</option>
                            <option value="Surat Bantuan" {{ old('category') == 'Surat Bantuan' ? 'selected' : '' }}>Surat Bantuan / Hibah</option>
                            <option value="Foto" {{ old('category') == 'Foto' ? 'selected' : '' }}>Foto Fisik Barang</option>
                            <option value="Umum" {{ old('category', 'Umum') == 'Umum' ? 'selected' : '' }}>Umum</option>
                        </select>
                    </div>

                    @if(auth()->user()->isSarpras())
                    <div class="mb-4">
                        <label class="form-label fw-bold">Jurusan Terkait (Opsional)</label>
                        <input type="text" name="department" placeholder="Kosongkan jika dokumen umum" class="form-control form-control-solid" value="{{ old('department') }}">
                    </div>
                    @endif

                    <div class="mb-4">
                        <label class="form-label fw-bold required">Pilih Berkas File (PDF, PNG, JPG, DOCX, XLSX max 10MB)</label>
                        <input type="file" name="file" required class="form-control form-control-solid">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Dokumen</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection
