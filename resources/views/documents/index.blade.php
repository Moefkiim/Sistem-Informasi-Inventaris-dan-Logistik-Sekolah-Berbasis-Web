@extends('layouts.app')

@section('title', 'Dokumen Terlampir')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Manajemen Dokumen</h1>
            <p class="text-sm text-slate-500">Daftar berkas pendukung, nota pengadaan, BAST, dan dokumen inventaris.</p>
        </div>
        @if(!auth()->user()->isKepalaSekolah())
        <button onclick="document.getElementById('upload-modal').classList.remove('hidden')" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-medium shadow-sm transition-all flex items-center justify-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Unggah Dokumen
        </button>
        @endif
    </div>

    <!-- Filter Category -->
    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex flex-wrap gap-2 items-center">
        <span class="text-sm font-medium text-slate-600 mr-2">Filter Kategori:</span>
        <a href="{{ route('documents.index') }}" class="px-3 py-1.5 rounded-lg text-xs font-semibold {{ !request('category') ? 'bg-indigo-100 text-indigo-700 border border-indigo-200' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">Semua</a>
        @foreach(['Nota', 'BAST', 'Surat Bantuan', 'Foto', 'Umum'] as $cat)
        <a href="{{ route('documents.index', ['category' => $cat]) }}" class="px-3 py-1.5 rounded-lg text-xs font-semibold {{ request('category') == $cat ? 'bg-indigo-100 text-indigo-700 border border-indigo-200' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">{{ $cat }}</a>
        @endforeach
    </div>

    <!-- Table Documents -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600">
                <thead class="bg-slate-50 border-b border-slate-200 text-slate-700 uppercase font-semibold text-xs">
                    <tr>
                        <th class="px-6 py-3">Judul Dokumen</th>
                        <th class="px-6 py-3">Kategori</th>
                        <th class="px-6 py-3">Jurusan</th>
                        <th class="px-6 py-3">Pengunggah</th>
                        <th class="px-6 py-3">Ukuran</th>
                        <th class="px-6 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($documents as $doc)
                    <tr class="hover:bg-slate-50/50">
                        <td class="px-6 py-4 font-medium text-slate-900">
                            <div class="flex items-center gap-3">
                                <div class="p-2 bg-indigo-50 text-indigo-600 rounded-lg">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                </div>
                                <div>
                                    <p class="font-semibold text-slate-800">{{ $doc->title }}</p>
                                    <p class="text-xs text-slate-400">{{ $doc->file_name }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-700 border border-slate-200">
                                {{ $doc->category }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-xs font-medium text-slate-600">
                            {{ $doc->department ?? 'Umum' }}
                        </td>
                        <td class="px-6 py-4 text-xs text-slate-500">
                            {{ $doc->user->name ?? '-' }}
                        </td>
                        <td class="px-6 py-4 text-xs text-slate-500">
                            {{ number_format($doc->file_size / 1024, 1) }} KB
                        </td>
                        <td class="px-6 py-4 text-right space-x-2">
                            <a href="{{ route('documents.download', $doc) }}" class="inline-flex items-center px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 rounded-lg text-xs font-semibold transition-all">
                                Unduh
                            </a>
                            @if(auth()->user()->isSarpras())
                            <form action="{{ route('documents.destroy', $doc) }}" method="POST" class="inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus dokumen ini?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="inline-flex items-center px-3 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-700 rounded-lg text-xs font-semibold transition-all">
                                    Hapus
                                </button>
                            </form>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-8 text-center text-slate-400">
                            Belum ada dokumen yang diunggah.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($documents->hasPages())
        <div class="p-4 border-t border-slate-200">
            {{ $documents->withQueryString()->links() }}
        </div>
        @endif
    </div>
</div>

<!-- Upload Modal -->
@if(!auth()->user()->isKepalaSekolah())
<div id="upload-modal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center hidden">
    <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-xl relative border border-slate-100">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-bold text-slate-800">Unggah Dokumen Baru</h3>
            <button onclick="document.getElementById('upload-modal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <form action="{{ route('documents.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Judul Dokumen</label>
                <input type="text" name="title" required placeholder="Contoh: BAST Pengadaan Lab RPL 2026" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Kategori</label>
                <select name="category" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    <option value="Nota">Nota / Kwitansi</option>
                    <option value="BAST">BAST (Berita Acara Serah Terima)</option>
                    <option value="Surat Bantuan">Surat Bantuan / Hibah</option>
                    <option value="Foto">Foto Fisik Barang</option>
                    <option value="Umum" selected>Umum</option>
                </select>
            </div>

            @if(auth()->user()->isSarpras())
            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Jurusan Terkait (Opsional)</label>
                <input type="text" name="department" placeholder="Kosongkan jika dokumen umum" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>
            @endif

            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Pilih Berkas File (PDF, PNG, JPG, DOCX, XLSX max 10MB)</label>
                <input type="file" name="file" required class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="document.getElementById('upload-modal').classList.add('hidden')" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-sm font-medium">Batal</button>
                <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium shadow-sm">Simpan Dokumen</button>
            </div>
        </form>
    </div>
</div>
@endif
@endsection
