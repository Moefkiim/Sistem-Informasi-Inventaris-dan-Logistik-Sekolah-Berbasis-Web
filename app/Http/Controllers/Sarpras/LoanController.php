<?php

namespace App\Http\Controllers\Sarpras;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ConditionHistory;
use App\Models\Item;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoanController extends Controller
{
    /**
     * Daftar semua peminjaman.
     */
    public function index(Request $request): View
    {
        // Tandai otomatis pinjaman yang lewat tanggal kembali
        Loan::where('status', 'dipinjam')
            ->whereDate('due_date', '<', now())
            ->update(['status' => 'terlambat']);

        $query = Loan::with(['item', 'borrower', 'approvedBy'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('loan_number', 'like', "%{$search}%")
                    ->orWhere('borrower_name', 'like', "%{$search}%")
                    ->orWhere('borrower_department', 'like', "%{$search}%")
                    ->orWhereHas('item', fn ($i) => $i->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('inventory_number', 'like', "%{$search}%")
                        ->orWhere('serial_number', 'like', "%{$search}%"));
            });
        }

        match ($request->string('due')->toString()) {
            'overdue' => $query->where('status', 'terlambat'),
            'today' => $query->whereIn('status', ['dipinjam', 'terlambat'])
                ->whereDate('due_date', now()),
            'week' => $query->whereIn('status', ['dipinjam', 'terlambat'])
                ->whereDate('due_date', '>=', now())
                ->whereDate('due_date', '<=', now()->addDays(7)),
            default => null,
        };

        $loans = $query->paginate(15)->withQueryString();
        $statusCounts = Loan::selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('sarpras.loans.index', compact('loans', 'statusCounts'));
    }

    /**
     * Form peminjaman baru.
     */
    public function create(): View
    {
        $items = Item::with('location')
            ->withSum(['activeLoans as active_loan_quantity'], 'quantity')
            ->whereNotIn('current_status', ['disposed', 'tidak_aktif'])
            ->orderBy('name')
            ->get()
            ->filter(function (Item $item) {
                if ($item->isIndividual()) {
                    return (int) $item->active_loan_quantity < $item->stock;
                }

                return $item->stock > 0;
            })
            ->values();

        $borrowers = User::where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('sarpras.loans.create', compact('items', 'borrowers'));
    }

    /**
     * Simpan peminjaman baru.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'borrower_name' => ['required', 'string', 'max:255'],
            'borrower_department' => ['nullable', 'string', 'max:100'],
            'borrower_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'item_id' => ['required', Rule::exists('items', 'id')->whereNull('deleted_at')],
            'quantity' => ['required', 'integer', 'min:1'],
            'loan_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:loan_date'],
            'purpose' => ['required', 'string', 'max:500'],
            'notes' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($validated, $request) {
            $item = Item::where('id', $validated['item_id'])
                ->lockForUpdate()
                ->firstOrFail();

            // Validasi ketersediaan stok/aset
            if ($item->isIndividual()) {
                $activeLoanCount = Loan::where('item_id', $item->id)
                    ->whereIn('status', ['dipinjam', 'disetujui'])
                    ->sum('quantity');

                if ($activeLoanCount >= $item->stock) {
                    throw ValidationException::withMessages([
                        'item_id' => 'Aset sedang dipinjam atau tidak tersedia.',
                    ]);
                }
            } else {
                // Consumable: cek stok cukup (tidak dikurangi dulu, baru dikurangi saat dikembalikan/status dipinjam)
                if ($validated['quantity'] > $item->stock) {
                    throw ValidationException::withMessages([
                        'quantity' => 'Stok tidak mencukupi. Stok saat ini: '.$item->stock,
                    ]);
                }
            }

            $loanNumber = $this->generateLoanNumber();

            $loan = Loan::create([
                'loan_number' => $loanNumber,
                'borrower_user_id' => $validated['borrower_user_id'] ?? null, // Peminjam terdaftar (opsional)
                'borrower_name' => $validated['borrower_name'],
                'borrower_department' => $validated['borrower_department'] ?? null,
                'recorded_by' => $request->user()->id, // Sarpras yang mencatat
                'item_id' => $item->id,
                'quantity' => $validated['quantity'],
                'loan_date' => $validated['loan_date'],
                'due_date' => $validated['due_date'],
                'purpose' => $validated['purpose'],
                'status' => 'menunggu',
                'condition_on_loan' => $item->current_condition,
                'notes' => $validated['notes'] ?? null,
            ]);

            ActivityLog::log(
                'loan_created',
                "Peminjaman baru: {$loan->borrower_name} meminjam {$item->name} (x{$loan->quantity})",
                $loan,
                [],
                ['status' => 'menunggu', 'item' => $item->code],
                $loan->loan_number
            );
        });

        return redirect()->route('sarpras.loans.index')
            ->with('success', 'Permohonan peminjaman berhasil dicatat.');
    }

    /**
     * Detail peminjaman.
     */
    public function show(Loan $loan): View
    {
        $loan->load(['item.location', 'borrower', 'recordedBy', 'approvedBy', 'returnedTo']);

        return view('sarpras.loans.show', compact('loan'));
    }

    /**
     * Setujui peminjaman (menunggu → disetujui → dipinjam).
     */
    public function approve(Request $request, Loan $loan): RedirectResponse
    {
        if (! $loan->isApprovable()) {
            return back()->withErrors(['msg' => 'Peminjaman tidak dalam status yang dapat disetujui.']);
        }

        DB::transaction(function () use ($loan, $request) {
            $item = Item::where('id', $loan->item_id)->lockForUpdate()->firstOrFail();
            $oldStatus = $loan->status;

            // Kurangi stok saat disetujui (untuk consumable)
            if ($item->isConsumable() && $loan->status === 'menunggu') {
                if ($item->stock < $loan->quantity) {
                    throw ValidationException::withMessages([
                        'quantity' => 'Stok tidak mencukupi saat konfirmasi: '.$item->stock,
                    ]);
                }
                Item::where('id', $item->id)
                    ->where('stock', '>=', $loan->quantity)
                    ->decrement('stock', $loan->quantity);
            }

            // Update status aset individual
            if ($item->isIndividual()) {
                $item->update(['current_status' => 'dipinjam']);
            }

            $loan->status = 'dipinjam';
            $loan->approved_by = $request->user()->id;
            $loan->approved_at = now();
            $loan->save();

            ActivityLog::log(
                'loan_approved',
                "Peminjaman {$loan->loan_number} disetujui dan barang diserahkan ke {$loan->borrower_name}",
                $loan,
                ['status' => $oldStatus],
                ['status' => 'dipinjam'],
                $loan->loan_number
            );
        });

        return back()->with('success', 'Peminjaman disetujui dan barang telah diserahkan.');
    }

    /**
     * Proses pengembalian barang.
     */
    public function returnLoan(Request $request, Loan $loan): RedirectResponse
    {
        if (! $loan->isReturnable()) {
            return back()->withErrors(['msg' => 'Peminjaman tidak dalam status yang dapat dikembalikan.']);
        }

        $validated = $request->validate([
            'condition_on_return' => ['required', 'in:baik,rusak_ringan,rusak_berat'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($loan, $validated, $request) {
            $item = Item::where('id', $loan->item_id)->lockForUpdate()->firstOrFail();
            $oldStatus = $loan->status;

            // Kembalikan stok untuk consumable
            if ($item->isConsumable()) {
                $item->increment('stock', $loan->quantity);
            }

            // Update status aset individual
            if ($item->isIndividual()) {
                $item->update(['current_status' => 'aktif']);
            }

            // Catat kondisi fisik jika berubah
            if ($validated['condition_on_return'] !== $item->current_condition) {
                ConditionHistory::create([
                    'item_id' => $item->id,
                    'from_condition' => $item->current_condition,
                    'to_condition' => $validated['condition_on_return'],
                    'user_id' => $request->user()->id,
                    'notes' => 'Kondisi setelah pengembalian peminjaman '.$loan->loan_number,
                    'recorded_at' => now(),
                ]);
                $item->update(['current_condition' => $validated['condition_on_return']]);
            }

            $loan->status = 'dikembalikan';
            $loan->condition_on_return = $validated['condition_on_return'];
            $loan->return_date = now()->toDateString();
            $loan->returned_to = $request->user()->id;
            $loan->returned_at = now();

            $returnNotes = trim((string) ($validated['notes'] ?? ''));
            if ($returnNotes !== '') {
                $loan->notes = trim(($loan->notes ?? '').' | Catatan kembali: '.$returnNotes);
            }

            $loan->save();

            ActivityLog::log(
                'loan_returned',
                "Barang {$item->name} dikembalikan dari {$loan->borrower_name} (Kondisi: {$validated['condition_on_return']})",
                $loan,
                ['status' => $oldStatus],
                ['status' => 'dikembalikan', 'condition_on_return' => $validated['condition_on_return']],
                $loan->loan_number
            );
        });

        return back()->with('success', 'Pengembalian barang berhasil dicatat.');
    }

    /**
     * Tolak permohonan peminjaman.
     */
    public function reject(Request $request, Loan $loan): RedirectResponse
    {
        if ($loan->status !== 'menunggu') {
            return back()->withErrors(['msg' => 'Hanya permohonan berstatus "menunggu" yang dapat ditolak.']);
        }

        $validated = $request->validate([
            'notes' => ['required', 'string', 'max:500'],
        ]);

        $loan->status = 'ditolak';
        $loan->notes = $validated['notes'];
        $loan->approved_by = auth()->id();
        $loan->approved_at = now();
        $loan->save();

        ActivityLog::log(
            'loan_rejected',
            "Peminjaman {$loan->loan_number} ditolak: {$validated['notes']}",
            $loan,
            ['status' => 'menunggu'],
            ['status' => 'ditolak'],
            $loan->loan_number
        );

        return back()->with('success', 'Permohonan peminjaman ditolak.');
    }

    /**
     * Generate nomor peminjaman yang aman dari race condition.
     */
    private function generateLoanNumber(): string
    {
        $prefix = 'LN-'.date('Ymd').'-';

        $last = Loan::withTrashed()
            ->where('loan_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('loan_number')
            ->first();

        $next = $last
            ? (intval(substr($last->loan_number, -4)) + 1)
            : 1;

        return $prefix.str_pad($next, 4, '0', STR_PAD_LEFT);
    }
}
