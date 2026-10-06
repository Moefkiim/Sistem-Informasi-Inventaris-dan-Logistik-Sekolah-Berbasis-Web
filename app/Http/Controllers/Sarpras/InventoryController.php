<?php

namespace App\Http\Controllers\Sarpras;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ConditionHistory;
use App\Models\Item;
use App\Models\Location;
use App\Models\LocationHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(Request $request): View
    {
        $query = Item::with(['location']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        if ($request->filled('department')) {
            $query->where('department', $request->department);
        }

        $items = $query->latest()->paginate(15);
        $locations = Location::all();

        return view('sarpras.inventory.index', compact('items', 'locations'));
    }

    public function create(): View
    {
        $locations = Location::all();

        return view('sarpras.inventory.create', compact('locations'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:items,code'],
            'inventory_number' => ['nullable', 'string', 'max:100', 'unique:items,inventory_number'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'brand' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string'],
            'item_type' => ['required', 'in:individual,consumable'],
            'unit' => ['required', 'string', 'max:30'],
            'stock' => ['required', 'integer', 'min:0'],
            'minimum_stock' => ['nullable', 'integer', 'min:0'],
            'source' => ['required', 'in:pembelian,bantuan'],
            'acquisition_year' => ['nullable', 'integer', 'min:1900', 'max:'.(date('Y') + 1)],
            'acquisition_price' => ['nullable', 'numeric', 'min:0'],
            'department' => ['nullable', 'string'],
            'location_id' => ['nullable', Rule::exists('locations', 'id')->whereNull('deleted_at')],
            'current_condition' => ['required', 'in:baik,rusak_ringan,rusak_berat'],
            'description' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($validated, $request) {
            $initial_stock = $validated['stock'];
            unset($validated['stock']);

            $item = new Item($validated);
            $item->stock = $initial_stock;
            $item->save();

            // Jika ada lokasi awal, buat histori mutasi lokasi perdana
            if (! empty($validated['location_id'])) {
                LocationHistory::create([
                    'item_id' => $item->id,
                    'from_location_id' => null,
                    'to_location_id' => $validated['location_id'],
                    'user_id' => $request->user()->id,
                    'notes' => 'Pencatatan inventaris awal',
                    'moved_at' => now(),
                ]);
            }

            // Buat entri awal riwayat kondisi fisik
            ConditionHistory::create([
                'item_id' => $item->id,
                'from_condition' => $validated['current_condition'],
                'to_condition' => $validated['current_condition'],
                'user_id' => $request->user()->id,
                'notes' => 'Kondisi fisik saat registrasi aset',
                'recorded_at' => now(),
            ]);

            ActivityLog::log(
                'item_created',
                "Barang baru didaftarkan: {$item->name} ({$item->code})",
                $item,
                [],
                ['code' => $item->code, 'name' => $item->name, 'stock' => $item->stock],
                $item->code
            );
        });

        return redirect()->route('sarpras.inventory.index')
            ->with('success', 'Master data barang inventaris berhasil ditambahkan.');
    }

    public function show(Item $item): View
    {
        $item->load([
            'location',
            'locationHistories.fromLocation',
            'locationHistories.toLocation',
            'locationHistories.user',
            'conditionHistories.user',
            'incomingItems.user',
            'outgoingItems.user',
            'distributions.toLocation',
            'activeLoans.borrower',
        ]);
        $locations = Location::all();

        return view('sarpras.inventory.show', compact('item', 'locations'));
    }

    public function updateLocation(Request $request, Item $item): RedirectResponse
    {
        $validated = $request->validate([
            'to_location_id' => ['required', Rule::exists('locations', 'id')->whereNull('deleted_at')],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        if ($item->location_id == $validated['to_location_id']) {
            return back()->withErrors(['msg' => 'Lokasi tujuan sama dengan lokasi saat ini.']);
        }

        DB::transaction(function () use ($item, $validated, $request) {
            LocationHistory::create([
                'item_id' => $item->id,
                'from_location_id' => $item->location_id,
                'to_location_id' => $validated['to_location_id'],
                'user_id' => $request->user()->id,
                'notes' => $validated['notes'] ?? 'Pemindahan lokasi barang',
                'moved_at' => now(),
            ]);

            $item->update(['location_id' => $validated['to_location_id']]);

            ActivityLog::log(
                'item_location_updated',
                "Lokasi {$item->name} ({$item->code}) dipindahkan",
                $item,
                ['location_id' => $item->getOriginal('location_id')],
                ['location_id' => $validated['to_location_id']],
                $item->code
            );
        });

        return back()->with('success', 'Lokasi barang berhasil diperbarui dan histori mutasi tercatat.');
    }

    public function updateCondition(Request $request, Item $item): RedirectResponse
    {
        $validated = $request->validate([
            'to_condition' => ['required', 'in:baik,rusak_ringan,rusak_berat'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        if ($item->current_condition === $validated['to_condition']) {
            return back()->withErrors(['msg' => 'Status kondisi fisik sama dengan kondisi saat ini.']);
        }

        DB::transaction(function () use ($item, $validated, $request) {
            ConditionHistory::create([
                'item_id' => $item->id,
                'from_condition' => $item->current_condition,
                'to_condition' => $validated['to_condition'],
                'user_id' => $request->user()->id,
                'notes' => $validated['notes'] ?? 'Pembaruan kondisi fisik barang',
                'recorded_at' => now(),
            ]);

            $item->update(['current_condition' => $validated['to_condition']]);

            ActivityLog::log(
                'item_condition_updated',
                "Kondisi {$item->name} ({$item->code}) diubah: {$item->getOriginal('current_condition')} → {$validated['to_condition']}",
                $item,
                ['current_condition' => $item->getOriginal('current_condition')],
                ['current_condition' => $validated['to_condition']],
                $item->code
            );
        });

        return back()->with('success', 'Riwayat perubahan kondisi berhasil dicatat.');
    }
}
