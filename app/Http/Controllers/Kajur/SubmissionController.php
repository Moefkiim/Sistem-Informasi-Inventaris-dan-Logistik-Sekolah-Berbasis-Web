<?php

namespace App\Http\Controllers\Kajur;

use App\Http\Controllers\Controller;
use App\Models\Submission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SubmissionController extends Controller
{
    /**
     * Menampilkan daftar permohonan pengajuan milik jurusan Kajur yang login.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $submissions = Submission::where('user_id', $user->id)
            ->with(['items'])
            ->latest()
            ->paginate(10);

        return view('kajur.submissions.index', compact('submissions'));
    }

    /**
     * Form pembuatan pengajuan baru.
     */
    public function create(): View
    {
        return view('kajur.submissions.create');
    }

    /**
     * Simpan pengajuan baru (Status: draft atau submitted).
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'purpose' => ['nullable', 'string'],
            'action' => ['required', 'in:draft,submit'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_name' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit' => ['required', 'string', 'max:50'],
            'items.*.estimated_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.specification' => ['nullable', 'string'],
        ]);

        $user = $request->user();

        DB::transaction(function () use ($validated, $user) {
            $submissionNumber = 'REQ-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
            $status = $validated['action'] === 'submit' ? 'submitted' : 'draft';

            $submission = Submission::create([
                'submission_number' => $submissionNumber,
                'user_id' => $user->id,
                'department' => $user->department ?? 'Umum',
                'title' => $validated['title'],
                'purpose' => $validated['purpose'] ?? null,
                'status' => $status,
            ]);

            foreach ($validated['items'] as $itemData) {
                $submission->items()->create([
                    'item_name' => $itemData['item_name'],
                    'quantity' => $itemData['quantity'],
                    'unit' => $itemData['unit'],
                    'estimated_price' => $itemData['estimated_price'] ?? 0,
                    'specification' => $itemData['specification'] ?? null,
                ]);
            }
        });

        return redirect()->route('kajur.submissions.index')
            ->with('success', 'Pengajuan inventaris berhasil disimpan.');
    }

    /**
     * Detail pengajuan.
     */
    public function show(Submission $submission): View
    {
        $this->authorizeKajur($submission);
        $submission->load(['items', 'sarprasUser', 'principalUser', 'documents']);

        return view('kajur.submissions.show', compact('submission'));
    }

    /**
     * Form edit pengajuan (Hanya jika status masih draft).
     */
    public function edit(Submission $submission): View|RedirectResponse
    {
        $this->authorizeKajur($submission);

        if ($submission->status !== 'draft') {
            return redirect()->route('kajur.submissions.show', $submission)
                ->withErrors(['msg' => 'Hanya pengajuan berstatus draft yang dapat diubah.']);
        }

        $submission->load('items');
        return view('kajur.submissions.edit', compact('submission'));
    }

    /**
     * Update pengajuan draft.
     */
    public function update(Request $request, Submission $submission): RedirectResponse
    {
        $this->authorizeKajur($submission);

        if ($submission->status !== 'draft') {
            return back()->withErrors(['msg' => 'Hanya pengajuan berstatus draft yang dapat diubah.']);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'purpose' => ['nullable', 'string'],
            'action' => ['required', 'in:draft,submit'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_name' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit' => ['required', 'string', 'max:50'],
            'items.*.estimated_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.specification' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($submission, $validated) {
            $status = $validated['action'] === 'submit' ? 'submitted' : 'draft';

            $submission->update([
                'title' => $validated['title'],
                'purpose' => $validated['purpose'] ?? null,
                'status' => $status,
            ]);

            $submission->items()->delete();

            foreach ($validated['items'] as $itemData) {
                $submission->items()->create([
                    'item_name' => $itemData['item_name'],
                    'quantity' => $itemData['quantity'],
                    'unit' => $itemData['unit'],
                    'estimated_price' => $itemData['estimated_price'] ?? 0,
                    'specification' => $itemData['specification'] ?? null,
                ]);
            }
        });

        return redirect()->route('kajur.submissions.show', $submission)
            ->with('success', 'Pengajuan draft berhasil diperbarui.');
    }

    /**
     * Batalkan pengajuan (Kajur hanya bisa membatalkan saat masih draft).
     */
    public function cancel(Submission $submission): RedirectResponse
    {
        $this->authorizeKajur($submission);

        if ($submission->status !== 'draft') {
            return back()->withErrors(['msg' => 'Hanya pengajuan berstatus draft yang dapat dibatalkan.']);
        }

        $submission->update(['status' => 'cancelled']);

        return redirect()->route('kajur.submissions.index')
            ->with('success', 'Pengajuan draft telah berhasil dibatalkan.');
    }

    /**
     * Ajukan draft ke Sarpras.
     */
    public function submitDraft(Submission $submission): RedirectResponse
    {
        $this->authorizeKajur($submission);

        if ($submission->status !== 'draft') {
            return back()->withErrors(['msg' => 'Pengajuan ini sudah pernah diajukan.']);
        }

        $submission->update(['status' => 'submitted']);

        return redirect()->route('kajur.submissions.index')
            ->with('success', 'Pengajuan berhasil dikirimkan ke pihak Sarpras untuk ditinjau.');
    }

    protected function authorizeKajur(Submission $submission): void
    {
        if ($submission->user_id !== auth()->id()) {
            abort(403, 'Akses ditolak: Anda hanya dapat mengakses pengajuan jurusan Anda sendiri.');
        }
    }
}
