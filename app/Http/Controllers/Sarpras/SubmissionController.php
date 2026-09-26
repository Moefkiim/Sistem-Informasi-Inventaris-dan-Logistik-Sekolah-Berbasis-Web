<?php

namespace App\Http\Controllers\Sarpras;

use App\Http\Controllers\Controller;
use App\Models\Submission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubmissionController extends Controller
{
    /**
     * Menampilkan semua pengajuan dari seluruh Kajur.
     */
    public function index(Request $request): View
    {
        $status = $request->get('status');
        $query = Submission::with(['user', 'items'])->latest();

        if ($status) {
            $query->where('status', $status);
        }

        $submissions = $query->paginate(15);

        return view('sarpras.submissions.index', compact('submissions', 'status'));
    }

    /**
     * Tinjau detail pengajuan.
     */
    public function show(Submission $submission): View
    {
        $submission->load(['user', 'items', 'sarprasUser', 'principalUser', 'documents']);

        return view('sarpras.submissions.show', compact('submission'));
    }

    /**
     * Proses telaah/verifikasi oleh Sarpras untuk diteruskan ke Kepala Sekolah.
     */
    public function process(Request $request, Submission $submission): RedirectResponse
    {
        $validated = $request->validate([
            'sarpras_notes' => ['required', 'string'],
            'action' => ['required', 'in:review,reject'],
        ]);

        if ($submission->status !== 'submitted') {
            return back()->withErrors(['msg' => 'Pengajuan ini tidak dalam status siap diproses.']);
        }

        $status = $validated['action'] === 'review' ? 'reviewed_sarpras' : 'rejected';

        $submission->update([
            'status' => $status,
            'sarpras_notes' => $validated['sarpras_notes'],
            'sarpras_user_id' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        $msg = $status === 'reviewed_sarpras'
            ? 'Pengajuan berhasil diproses dan diteruskan ke Kepala Sekolah untuk approval.'
            : 'Pengajuan telah ditolak oleh Sarpras.';

        return redirect()->route('sarpras.submissions.index')->with('success', $msg);
    }
}
