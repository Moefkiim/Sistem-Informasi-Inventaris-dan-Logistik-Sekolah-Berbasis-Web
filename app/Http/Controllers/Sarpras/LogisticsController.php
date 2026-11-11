<?php

namespace App\Http\Controllers\Sarpras;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Distribution;
use App\Models\IncomingItem;
use App\Models\Item;
use App\Models\Location;
use App\Models\LocationHistory;
use App\Models\OutgoingItem;
use App\Services\IncomingStockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LogisticsController extends Controller
{
    public function __construct(private readonly IncomingStockService $incomingStock) {}

    /**
     * Log Barang Masuk
     */
    public function incomingIndex(Request $request): View
    {
        $search = $request->query('search');
        $source = $request->query('source');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $query = IncomingItem::with(['item', 'user'])->latest('entry_date');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('transaction_number', 'like', "%{$search}%")
                    ->orWhere('source_origin', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('item', fn ($iq) => $iq->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
            });
        }

        if ($source) {
            $query->where('source', $source);
        }

        if ($startDate) {
            $query->whereDate('entry_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('entry_date', '<=', $endDate);
        }

        $incomingLogs = $query->paginate(15)->withQueryString();
        $items = Item::query()->orderBy('name')->get(['id', 'name', 'stock', 'unit']);

        return view('sarpras.logistics.incoming', compact('incomingLogs', 'items', 'search', 'source', 'startDate', 'endDate'));
    }

    public function storeIncoming(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'item_id' => ['required', Rule::exists('items', 'id')->whereNull('deleted_at')],
            'quantity' => ['required', 'integer', 'min:1'],
            'source' => ['required', 'in:pembelian,bantuan'],
            'source_origin' => ['nullable', 'string', 'max:255'],
            'entry_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($validated, $request) {
            $incoming = $this->incomingStock->record([
                'item_id' => $validated['item_id'],
                'quantity' => $validated['quantity'],
                'source' => $validated['source'],
                'source_origin' => $validated['source_origin'] ?? null,
                'entry_date' => $validated['entry_date'],
                'user_id' => $request->user()->id,
                'notes' => $validated['notes'] ?? null,
            ]);

            $item = Item::find($validated['item_id']);

            ActivityLog::log(
                'incoming_recorded',
                "Barang masuk: {$item?->name} (x{$validated['quantity']}) dicatat [{$incoming->transaction_number}]",
                $incoming,
                [],
                ['item_id' => $validated['item_id'], 'quantity' => $validated['quantity'], 'source' => $validated['source']],
                $incoming->transaction_number,
            );
        });

        return back()->with('success', 'Transaksi barang masuk berhasil dicatat dan stok telah bertambah.');
    }

    /**
     * Log Barang Keluar
     */
    public function outgoingIndex(Request $request): View
    {
        $search = $request->query('search');
        $reason = $request->query('reason');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $query = OutgoingItem::with(['item', 'user'])->latest('exit_date');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('transaction_number', 'like', "%{$search}%")
                    ->orWhere('reason', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('item', fn ($iq) => $iq->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
            });
        }

        if ($reason) {
            $query->where('reason', $reason);
        }

        if ($startDate) {
            $query->whereDate('exit_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('exit_date', '<=', $endDate);
        }

        $outgoingLogs = $query->paginate(15)->withQueryString();
        $items = Item::query()->where('stock', '>', 0)->orderBy('name')->get(['id', 'name', 'stock', 'unit']);

        return view('sarpras.logistics.outgoing', compact('outgoingLogs', 'items', 'search', 'reason', 'startDate', 'endDate'));
    }

    public function storeOutgoing(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'item_id' => ['required', Rule::exists('items', 'id')->whereNull('deleted_at')],
            'quantity' => ['required', 'integer', 'min:1'],
            'exit_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($validated, $request) {
            $item = Item::where('id', $validated['item_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($item->stock < $validated['quantity']) {
                throw ValidationException::withMessages([
                    'quantity' => 'Stok tidak mencukupi. Stok saat ini: '.$item->stock,
                ]);
            }

            $affected = Item::where('id', $validated['item_id'])
                ->where('stock', '>=', $validated['quantity'])
                ->decrement('stock', $validated['quantity']);

            if ($affected === 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'Stok tidak mencukupi.',
                ]);
            }

            $transactionNumber = $this->generateDocumentNumber('outgoing_items', 'transaction_number', 'OUT');

            $outgoing = OutgoingItem::create([
                'transaction_number' => $transactionNumber,
                'item_id' => $item->id,
                'quantity' => $validated['quantity'],
                'exit_date' => $validated['exit_date'],
                'reason' => $validated['reason'],
                'user_id' => $request->user()->id,
                'notes' => $validated['notes'] ?? null,
            ]);

            ActivityLog::log(
                'outgoing_recorded',
                "Barang keluar: {$item->name} (x{$validated['quantity']}) dicatat [{$transactionNumber}] - {$validated['reason']}",
                $outgoing,
                [],
                ['item_id' => $item->id, 'quantity' => $validated['quantity'], 'reason' => $validated['reason']],
                $transactionNumber,
            );
        });

        return back()->with('success', 'Transaksi barang keluar berhasil dicatat dan stok telah dikurangi.');
    }

    /**
     * Log Distribusi
     */
    public function distributionIndex(Request $request): View
    {
        $search = $request->query('search');
        $toLocationId = $request->query('to_location_id');
        $recipientDepartment = $request->query('recipient_department');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $query = Distribution::with(['item', 'toLocation', 'user'])->latest('distribution_date');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('distribution_number', 'like', "%{$search}%")
                    ->orWhere('recipient_name', 'like', "%{$search}%")
                    ->orWhere('recipient_department', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('item', fn ($iq) => $iq->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
            });
        }

        if ($toLocationId) {
            $query->where('to_location_id', $toLocationId);
        }

        if ($recipientDepartment) {
            $query->where('recipient_department', $recipientDepartment);
        }

        if ($startDate) {
            $query->whereDate('distribution_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('distribution_date', '<=', $endDate);
        }

        $distributions = $query->paginate(15)->withQueryString();
        $items = Item::query()->orderBy('name')->get(['id', 'name', 'stock', 'unit']);
        $locations = Location::query()->orderBy('name')->get(['id', 'name', 'code']);

        return view('sarpras.logistics.distributions', compact('distributions', 'items', 'locations', 'search', 'toLocationId', 'recipientDepartment', 'startDate', 'endDate'));
    }

    public function storeDistribution(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'item_id' => ['required', Rule::exists('items', 'id')->whereNull('deleted_at')],
            'quantity' => ['required', 'integer', 'min:1'],
            'to_location_id' => ['required', Rule::exists('locations', 'id')->whereNull('deleted_at')],
            'recipient_department' => ['nullable', 'string', 'max:100'],
            'recipient_name' => ['nullable', 'string', 'max:100'],
            'distribution_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($validated, $request) {
            $item = Item::where('id', $validated['item_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($item->stock < $validated['quantity']) {
                throw ValidationException::withMessages([
                    'quantity' => 'Stok tidak mencukupi. Stok saat ini: '.$item->stock,
                ]);
            }

            $affected = Item::where('id', $validated['item_id'])
                ->where('stock', '>=', $validated['quantity'])
                ->decrement('stock', $validated['quantity']);

            if ($affected === 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'Stok tidak mencukupi.',
                ]);
            }

            $transactionNumber = $this->generateDocumentNumber('distributions', 'distribution_number', 'DIST');

            $distribution = Distribution::create([
                'distribution_number' => $transactionNumber,
                'item_id' => $validated['item_id'],
                'quantity' => $validated['quantity'],
                'to_location_id' => $validated['to_location_id'],
                'recipient_department' => $validated['recipient_department'] ?? null,
                'recipient_name' => $validated['recipient_name'] ?? null,
                'distribution_date' => $validated['distribution_date'],
                'user_id' => $request->user()->id,
                'notes' => $validated['notes'] ?? null,
            ]);

            // F2: Catat perpindahan lokasi dengan from = lokasi SEBELUM distribusi.
            LocationHistory::create([
                'item_id' => $item->id,
                'from_location_id' => $item->location_id,
                'to_location_id' => $validated['to_location_id'],
                'user_id' => $request->user()->id,
                'notes' => 'Distribusi barang: '.($validated['notes'] ?? $transactionNumber),
                'moved_at' => now(),
            ]);

            // F2: Perbarui lokasi utama item (consumable: satu lokasi utama).
            // Untuk item individual, lokasi unit masing-masing dikelola lewat
            // alur registrasi dan pemindahan unit di modul Inventaris.
            Item::where('id', $item->id)->update(['location_id' => $validated['to_location_id']]);

            $toLocation = Location::find($validated['to_location_id']);

            ActivityLog::log(
                'distribution_recorded',
                "Distribusi barang: {$item->name} (x{$validated['quantity']}) ke ".($toLocation?->name ?? 'lokasi tujuan')." [{$transactionNumber}]",
                $distribution,
                [],
                ['item_id' => $item->id, 'quantity' => $validated['quantity'], 'to_location_id' => $validated['to_location_id']],
                $transactionNumber,
            );
        });

        return back()->with('success', 'Data distribusi berhasil dicatat ke dalam log sistem dan stok telah disesuaikan.');
    }

    /**
     * Nomor dokumen transaksi berurutan dengan pola PREFIX-YYYYMMDD-XXXX.
     *
     * Pola yang sama dengan generateLoanNumber: baca nomor terbesar dari
     * tabel dengan lockForUpdate (di dalam transaksi pemanggil), tambah
     * satu, lalu str_pad ke 4 digit. Data lama yang memakai uniqid()
     * (4 karakter acak, ada yang non-numerik) di-parse ke integer 0,
     * sehingga nomor baru mulai dari 0001 tanpa menyentuh data lama.
     */
    private function generateDocumentNumber(string $table, string $column, string $prefix): string
    {
        $fullPrefix = $prefix.'-'.date('Ymd').'-';

        $last = DB::table($table)
            ->where($column, 'like', $fullPrefix.'%')
            ->lockForUpdate()
            ->orderByDesc($column)
            ->first();

        $next = $last ? ((int) substr((string) $last->{$column}, -4) + 1) : 1;

        return $fullPrefix.str_pad($next, 4, '0', STR_PAD_LEFT);
    }
}
