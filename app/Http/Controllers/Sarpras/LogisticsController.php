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
            $transactionNumber = 'IN-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

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
                    'quantity' => 'Stok tidak mencukupi. Stok saat ini: ' . $item->stock,
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

            $transactionNumber = 'OUT-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

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
            'item_id' => ['required', \Illuminate\Validation\Rule::exists('items', 'id')->whereNull('deleted_at')],
            'quantity' => ['required', 'integer', 'min:1'],
            'to_location_id' => ['required', \Illuminate\Validation\Rule::exists('locations', 'id')->whereNull('deleted_at')],
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
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'quantity' => 'Stok tidak mencukupi. Stok saat ini: ' . $item->stock,
                ]);
            }

            $affected = Item::where('id', $validated['item_id'])
                ->where('stock', '>=', $validated['quantity'])
                ->decrement('stock', $validated['quantity']);

            if ($affected === 0) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'quantity' => 'Stok tidak mencukupi.',
                ]);
            }

            $transactionNumber = 'DIST-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

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
                'notes' => 'Distribusi barang: ' . ($validated['notes'] ?? $transactionNumber),
                'moved_at' => now(),
            ]);
        });

        return back()->with('success', 'Data distribusi berhasil dicatat ke dalam log sistem dan stok telah disesuaikan.');
    }
}
