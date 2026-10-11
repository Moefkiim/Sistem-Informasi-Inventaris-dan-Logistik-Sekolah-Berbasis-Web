<?php

namespace App\Http\Controllers\Sarpras;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Item;
use App\Models\Location;
use App\Models\Submission;
use App\Services\SubmissionNotifier;
use App\Services\SubmissionRealizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SubmissionController extends Controller
{
    public function __construct(
        private readonly SubmissionRealizationService $realization,
        private readonly SubmissionNotifier $notifier,
    ) {}

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
        $submission->load(['user', 'items', 'sarprasUser', 'principalUser', 'realizedBy', 'documents', 'histories.actor', 'incomingItems.item']);

        return view('sarpras.submissions.show', compact('submission'));
    }

    /**
     * Proses telaah/verifikasi oleh Sarpras untuk diteruskan ke Kepala Sekolah.
     */
    public function process(Request $request, Submission $submission): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:review,reject'],
            'sarpras_notes' => [
                Rule::requiredIf($request->input('action') === 'reject'),
                'nullable',
                'string',
                Rule::when($request->input('action') === 'reject', ['min:10']),
            ],
        ], [
            'action.required' => 'Pilih tindakan verifikasi terlebih dahulu.',
            'action.in' => 'Tindakan verifikasi tidak valid.',
            'sarpras_notes.required' => 'Alasan penolakan wajib diisi.',
            'sarpras_notes.min' => 'Alasan penolakan minimal 10 karakter.',
        ]);

        if ($submission->status !== 'submitted') {
            return back()->withErrors(['msg' => 'Pengajuan ini tidak dalam status siap diproses.']);
        }

        $status = $validated['action'] === 'review' ? 'reviewed_sarpras' : 'rejected';
        $notes = $validated['sarpras_notes'] ?? null;

        // Fields ini dikecualikan dari $fillable — set secara eksplisit
        $submission->status = $status;
        $submission->sarpras_notes = $notes;
        $submission->sarpras_user_id = $request->user()->id;
        $submission->reviewed_at = now();
        $submission->save();

        $submission->recordStatusChange('submitted', $status, $request->user(), $notes);

        ActivityLog::log(
            $status === 'reviewed_sarpras' ? 'submission_reviewed' : 'submission_rejected_sarpras',
            "Pengajuan {$submission->submission_number} ".($status === 'reviewed_sarpras' ? 'diverifikasi' : 'ditolak').' oleh Sarpras'.($notes ? ": {$notes}" : ''),
            $submission,
            ['status' => 'submitted'],
            ['status' => $status, 'sarpras_notes' => $notes],
            $submission->submission_number
        );

        $msg = $status === 'reviewed_sarpras'
            ? 'Pengajuan berhasil diproses dan diteruskan ke Kepala Sekolah untuk approval.'
            : 'Pengajuan telah ditolak oleh Sarpras.';

        if ($status === 'reviewed_sarpras') {
            $this->notifier->reviewed($submission);
        } else {
            $this->notifier->rejected($submission);
        }

        return redirect()->route('sarpras.submissions.index')->with('success', $msg);
    }

    /**
     * Antrean realisasi: daftar pengajuan yang telah disetujui Kepala Sekolah.
     */
    public function ready(): View
    {
        $submissions = Submission::with(['user', 'items'])
            ->withCount('items')
            ->where('status', 'approved')
            ->latest()
            ->get();

        return view('sarpras.realizations.index', compact('submissions'));
    }

    /**
     * Formulir realisasi: pilah satuan barang per baris pengajuan.
     */
    public function realizeForm(Submission $submission): View
    {
        abort_unless($submission->canBeRealizedBySarpras(), 403, 'Pengajuan ini tidak dalam status siap direalisasi.');

        $submission->load(['user', 'items']);
        $items = Item::withTrashed()->whereNull('deleted_at')->orderBy('name')->get(['id', 'name', 'code']);
        $locations = Location::withTrashed()->whereNull('deleted_at')->orderBy('name')->get(['id', 'name']);

        return view('sarpras.realizations.create', compact('submission', 'items', 'locations'));
    }

    /**
     * Proses realisasi pengajuan secara atomik.
     */
    public function realize(Request $request, Submission $submission): RedirectResponse
    {
        abort_unless($submission->canBeRealizedBySarpras(), 403, 'Pengajuan ini tidak dalam status siap direalisasi.');

        $validated = $request->validate([
            'source' => ['required', 'in:pembelian,bantuan'],
            'entry_date' => ['required', 'date'],
            'realization_notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.submission_item_id' => ['required', 'integer'],
            'lines.*.received_quantity' => ['required', 'integer', 'min:1'],
            'lines.*.mode' => ['required', 'in:existing,new'],
            'lines.*.item_id' => ['nullable', 'integer'],
            'lines.*.new_name' => ['nullable', 'string', 'max:255'],
            'lines.*.new_category' => ['nullable', 'string', 'max:191'],
            'lines.*.new_item_type' => ['required_if:lines.*.mode,new', 'nullable', 'in:individual,consumable'],
            'lines.*.new_unit' => ['required_if:lines.*.mode,new', 'nullable', 'string', 'max:50'],
            'lines.*.new_location_id' => ['nullable', Rule::exists('locations', 'id')->whereNull('deleted_at')],
            'lines.*.new_condition' => ['required_if:lines.*.mode,new', 'nullable', 'in:baik,rusak_ringan,rusak_berat'],
        ], [
            'source.required' => 'Sumber perolehan wajib dipilih.',
            'source.in' => 'Sumber perolehan tidak valid.',
            'entry_date.required' => 'Tanggal barang masuk wajib diisi.',
            'lines.required' => 'Minimal satu baris harus direalisasikan.',
            'lines.*.received_quantity.required' => 'Jumlah diterima per baris wajib diisi.',
            'lines.*.received_quantity.min' => 'Jumlah diterima minimal 1.',
            'lines.*.mode.in' => 'Jenis satuan barang tidak valid.',
        ]);

        $submissionItems = $submission->items()->get()->keyBy('id');
        $normalizedLines = [];

        foreach (($validated['lines'] ?? []) as $index => $line) {
            $submissionItem = $submissionItems->get((int) ($line['submission_item_id'] ?? 0));

            if (! $submissionItem) {
                throw ValidationException::withMessages([
                    'lines.'.$index.'.submission_item_id' => 'Baris pengajuan tidak valid.',
                ]);
            }

            $quantity = (int) $line['received_quantity'];
            if ($quantity > $submissionItem->quantity) {
                throw ValidationException::withMessages([
                    'lines.'.$index.'.received_quantity' => "Jumlah diterima tidak boleh melebihi permintaan ({$submissionItem->quantity}).",
                ]);
            }

            if ($line['mode'] === 'existing' && empty($line['item_id'])) {
                throw ValidationException::withMessages([
                    'lines.'.$index.'.item_id' => 'Pilih barang inventaris untuk baris ini.',
                ]);
            }

            $normalizedLines[] = [
                'submission_item_id' => $submissionItem->id,
                'received_quantity' => $quantity,
                'item_id' => $line['mode'] === 'existing' ? $line['item_id'] : null,
                'new' => $line['mode'] === 'new' ? [
                    'name' => $line['new_name'],
                    'category' => $line['new_category'],
                    'item_type' => $line['new_item_type'],
                    'unit' => $line['new_unit'],
                    'location_id' => $line['new_location_id'] ?? null,
                    'condition' => $line['new_condition'],
                ] : null,
            ];
        }

        $this->realization->realize($submission, $normalizedLines, [
            'source' => $validated['source'],
            'entry_date' => $validated['entry_date'],
            'realization_notes' => $validated['realization_notes'] ?? null,
        ], $request->user());

        return redirect()
            ->route('sarpras.submissions.show', $submission)
            ->with('success', 'Pengajuan berhasil direalisasikan. Barang masuk tercatat dan inventaris telah diperbarui.');
    }
}
