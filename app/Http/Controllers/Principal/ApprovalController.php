<?php

namespace App\Http\Controllers\Principal;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Submission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApprovalController extends Controller
{
    /**
     * Menampilkan daftar pengajuan yang telah diproses Sarpras dan menunggu persetujuan Kepala Sekolah.
     */
    public function index(Request $request): View
    {
        $status = $request->get('status', 'reviewed_sarpras');

        $query = Submission::with(['user', 'sarprasUser', 'items'])->latest();

        if ($status === 'all') {
            $query->whereIn('status', ['reviewed_sarpras', 'approved', 'rejected']);
        } else {
            $query->where('status', $status);
        }

        $submissions = $query->paginate(15);

        return view('principal.approval.index', compact('submissions', 'status'));
    }

    /**
     * Detail telaah pengajuan untuk Kepala Sekolah.
     */
    public function show(Submission $submission): View
    {
        $submission->load(['user', 'sarprasUser', 'items', 'documents']);
        return view('principal.approval.show', compact('submission'));
    }

    /**
     * Berikan keputusan akhir Approval atau Reject.
     */
    public function decide(Request $request, Submission $submission): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:approve,reject'],
            'principal_notes' => ['nullable', 'string'],
        ]);

        if ($submission->status !== 'reviewed_sarpras') {
            return back()->withErrors(['msg' => 'Pengajuan belum diverifikasi oleh Sarpras atau sudah diputuskan sebelumnya.']);
        }

        $status = $validated['action'] === 'approve' ? 'approved' : 'rejected';

        // Fields ini dikecualikan dari $fillable — set secara eksplisit
        $submission->status           = $status;
        $submission->principal_notes  = $validated['principal_notes'] ?? null;
        $submission->principal_user_id = $request->user()->id;
        $submission->decided_at       = now();
        $submission->save();

        ActivityLog::log(
            $status === 'approved' ? 'submission_approved' : 'submission_rejected',
            "Pengajuan {$submission->submission_number} " . ($status === 'approved' ? 'DISETUJUI' : 'DITOLAK') . " oleh Kepala Sekolah" . ($validated['principal_notes'] ? ": {$validated['principal_notes']}" : ''),
            $submission,
            ['status' => 'reviewed_sarpras'],
            ['status' => $status, 'principal_notes' => $validated['principal_notes'] ?? null],
            $submission->submission_number
        );

        $statusLabel = $status === 'approved' ? 'disetujui' : 'ditolak';

        return redirect()->route('kepala_sekolah.approval.index')
            ->with('success', "Pengajuan No. {$submission->submission_number} telah berhasil {$statusLabel}.");
    }
}
