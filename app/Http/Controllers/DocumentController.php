<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    /**
     * Menampilkan daftar dokumen terlampir.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $query = Document::with('user')->latest();

        // Kajur hanya dapat melihat dokumen umum atau dokumen sesuai jurusannya
        if ($user->isKajur()) {
            $query->where(function ($q) use ($user) {
                $q->whereNull('department')
                  ->orWhere('department', $user->department);
            });
        }

        if ($category = $request->get('category')) {
            $query->where('category', $category);
        }

        $documents = $query->paginate(15);

        return view('documents.index', compact('documents'));
    }

    /**
     * Mengunggah dokumen baru (Sarpras & Kajur).
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->isKepalaSekolah()) {
            abort(403, 'Kepala Sekolah tidak memiliki akses mengunggah dokumen.');
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'in:Nota,BAST,Surat Bantuan,Foto,Umum'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx', 'max:10240'], // maks 10MB
            'department' => ['nullable', 'string', 'max:100'],
        ]);

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $fileSize = $file->getSize();

        // Simpan file ke storage
        $path = $file->store('documents', 'public');

        Document::create([
            'title' => $validated['title'],
            'file_path' => $path,
            'file_name' => $originalName,
            'file_type' => $extension,
            'file_size' => $fileSize,
            'category' => $validated['category'],
            'department' => $user->isKajur() ? $user->department : ($validated['department'] ?? null),
            'user_id' => $user->id,
        ]);

        return redirect()->route('documents.index')
            ->with('success', 'Dokumen berhasil diunggah.');
    }

    /**
     * Mengunduh dokumen.
     */
    public function download(Document $document): StreamedResponse|RedirectResponse
    {
        $user = auth()->user();

        // Filter akses jurusan untuk Kajur
        if ($user->isKajur() && $document->department && $document->department !== $user->department) {
            abort(403, 'Akses ditolak.');
        }

        if (!Storage::disk('public')->exists($document->file_path)) {
            return back()->withErrors(['msg' => 'File dokumen tidak ditemukan di penyimpanan server.']);
        }

        return Storage::disk('public')->download($document->file_path, $document->file_name);
    }

    /**
     * Menghapus dokumen (Hanya Sarpras).
     */
    public function destroy(Document $document): RedirectResponse
    {
        $user = auth()->user();

        if (!$user->isSarpras()) {
            abort(403, 'Hanya pihak Sarpras yang berhak menghapus dokumen.');
        }

        if (Storage::disk('public')->exists($document->file_path)) {
            Storage::disk('public')->delete($document->file_path);
        }

        $document->delete();

        return redirect()->route('documents.index')
            ->with('success', 'Dokumen berhasil dihapus.');
    }
}
