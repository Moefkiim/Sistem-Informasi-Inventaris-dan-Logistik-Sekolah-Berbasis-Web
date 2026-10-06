<?php

namespace App\Http\Controllers\Sarpras;

use App\Http\Controllers\Controller;
use App\Models\Distribution;
use App\Models\IncomingItem;
use App\Models\Item;
use App\Models\Location;
use App\Models\LocationHistory;
use App\Models\OutgoingItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LogisticsController extends Controller
{
    /**
     * Log Barang Masuk
     */
    public function incomingIndex(): View
    {
        $incomingLogs = IncomingItem::with(['item', 'user'])->latest('entry_date')->paginate(15);
        $items = Item::all();

        return view('sarpras.logistics.incoming', compact('incomingLogs', 'items'));
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
            $transactionNumber = $this->generateDocumentNumber('incoming_items', 'transaction_number', 'IN');

            IncomingItem::create([
                'transaction_number' => $transactionNumber,
                'item_id' => $validated['item_id'],
                'quantity' => $validated['quantity'],
                'source' => $validated['source'],
                'source_origin' => $validated['source_origin'] ?? null,
                'entry_date' => $validated['entry_date'],
                'user_id' => $request->user()->id,
                'notes' => $validated['notes'] ?? null,
            ]);

            // Tambahkan stok barang secara konsisten
            Item::where('id', $validated['item_id'])
                ->whereNull('deleted_at')
                ->increment('stock', $validated['quantity']);
        });

        return back()->with('success', 'Transaksi barang masuk berhasil dicatat dan stok telah bertambah.');
    }

    /**
     * Log Barang Keluar
     */
    public function outgoingIndex(): View
    {
        $outgoingLogs = OutgoingItem::with(['item', 'user'])->latest('exit_date')->paginate(15);
        $items = Item::where('stock', '>', 0)->get();

        return view('sarpras.logistics.outgoing', compact('outgoingLogs', 'items'));
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

            OutgoingItem::create([
                'transaction_number' => $transactionNumber,
                'item_id' => $item->id,
                'quantity' => $validated['quantity'],
                'exit_date' => $validated['exit_date'],
                'reason' => $validated['reason'],
                'user_id' => $request->user()->id,
                'notes' => $validated['notes'] ?? null,
            ]);
        });

        return back()->with('success', 'Transaksi barang keluar berhasil dicatat dan stok telah dikurangi.');
    }

    /**
     * Log Distribusi
     */
    public function distributionIndex(): View
    {
        $distributions = Distribution::with(['item', 'toLocation', 'user'])->latest('distribution_date')->paginate(15);
        $items = Item::all();
        $locations = Location::all();

        return view('sarpras.logistics.distributions', compact('distributions', 'items', 'locations'));
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

            Distribution::create([
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

            LocationHistory::create([
                'item_id' => $item->id,
                'from_location_id' => $item->location_id,
                'to_location_id' => $validated['to_location_id'],
                'user_id' => $request->user()->id,
                'notes' => 'Distribusi barang: '.($validated['notes'] ?? $transactionNumber),
                'moved_at' => now(),
            ]);
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
