<?php

namespace App\Http\Controllers\Sarpras;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AssetUnit;
use App\Models\ConditionHistory;
use App\Models\Item;
use App\Models\Location;
use App\Models\LocationHistory;
use App\Services\InventoryRegistrationService;
use App\Support\AssetCondition;
use App\Support\Departments;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function __construct(private readonly InventoryRegistrationService $registration) {}

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
            'department' => ['nullable', Rule::in(Departments::codes())],
            'location_id' => ['nullable', Rule::exists('locations', 'id')->whereNull('deleted_at')],
            'current_condition' => ['required', AssetCondition::rule()],
            'description' => ['nullable', 'string'],
        ]);

        $this->registration->register($validated, $request->user());

        return redirect()->route('sarpras.inventory.index')
            ->with('success', 'Master data barang inventaris berhasil ditambahkan.');
    }

    public function show(Item $item): View
    {
        $item->load([
            'location',
            'assetUnits',
            'locationHistories.fromLocation',
            'locationHistories.toLocation',
            'locationHistories.assetUnit',
            'locationHistories.user',
            'conditionHistories.assetUnit',
            'conditionHistories.user',
            'statusHistories.assetUnit',
            'statusHistories.user',
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
            'asset_unit_id' => ['nullable', 'integer', Rule::exists('asset_units', 'id')],
            'to_location_id' => ['required', Rule::exists('locations', 'id')->whereNull('deleted_at')],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $hasUnits = $item->isIndividual() && $item->assetUnits()->exists();

        if ($hasUnits) {
            return $this->updateUnitLocation($item, $validated, $request);
        }

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

    /**
     * Pemindahan lokasi per unit aset individual.
     */
    private function updateUnitLocation(Item $item, array $validated, Request $request): RedirectResponse
    {
        if (empty($validated['asset_unit_id'])) {
            throw ValidationException::withMessages([
                'asset_unit_id' => 'Silakan pilih unit aset yang dipindahkan lokasinya.',
            ]);
        }

        $unit = $item->assetUnits()->find((int) $validated['asset_unit_id']);

        if (! $unit) {
            throw ValidationException::withMessages([
                'asset_unit_id' => 'Unit aset tidak valid untuk barang ini.',
            ]);
        }

        if ($unit->location_id == $validated['to_location_id']) {
            return back()->withErrors(['msg' => 'Lokasi tujuan sama dengan lokasi unit saat ini.']);
        }

        DB::transaction(function () use ($item, $unit, $validated, $request) {
            LocationHistory::create([
                'item_id' => $item->id,
                'asset_unit_id' => $unit->id,
                'from_location_id' => $unit->location_id,
                'to_location_id' => $validated['to_location_id'],
                'user_id' => $request->user()->id,
                'notes' => $validated['notes'] ?? 'Pemindahan lokasi unit aset',
                'moved_at' => now(),
            ]);

            $unit->update(['location_id' => $validated['to_location_id']]);

            // Lokasi agregat item mengikuti unit pertama agar master tetap bermakna.
            $primaryLocationId = $item->assetUnits()
                ->orderBy('id')
                ->value('location_id');
            $item->update(['location_id' => $primaryLocationId]);

            ActivityLog::log(
                'item_location_updated',
                "Lokasi unit {$unit->unit_inventory_number} ({$item->name}) dipindahkan",
                $unit,
                ['location_id' => $unit->getOriginal('location_id')],
                ['location_id' => $validated['to_location_id']],
                $item->code
            );
        });

        return back()->with('success', 'Lokasi unit berhasil diperbarui dan histori mutasi tercatat.');
    }

    public function updateCondition(Request $request, Item $item): RedirectResponse
    {
        $validated = $request->validate([
            'asset_unit_id' => ['nullable', 'integer', Rule::exists('asset_units', 'id')],
            'to_condition' => ['required', AssetCondition::rule()],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $hasUnits = $item->isIndividual() && $item->assetUnits()->exists();

        if ($hasUnits) {
            return $this->updateUnitCondition($item, $validated, $request);
        }

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

    /**
     * Ubah status operasional unit aset (hanya Sarpras).
     */
    public function updateStatus(Request $request, Item $item): RedirectResponse
    {
        $validated = $request->validate([
            'asset_unit_id' => ['required', 'integer', Rule::exists('asset_units', 'id')],
            'to_status' => ['required', 'in:'.implode(',', array_keys(AssetUnit::STATUS_LABELS))],
            'reason' => ['required', 'string', 'max:500'],
            'result_condition' => ['nullable', AssetCondition::rule()],
            'confirmed' => ['nullable', 'in:1,0,on'],
        ]);

        $toStatus = $validated['to_status'];

        if ($toStatus === 'disposed' && $request->boolean('confirmed') !== true) {
            return back()->withErrors(['confirmed' => 'Wajib mencentang konfirmasi penghapusan aset di halaman inventaris.']);
        }

        DB::transaction(function () use ($item, $toStatus, $validated, $request) {
            $unit = AssetUnit::where('item_id', $item->id)
                ->whereKey((int) $validated['asset_unit_id'])
                ->lockForUpdate()
                ->first();

            if (! $unit) {
                throw ValidationException::withMessages([
                    'asset_unit_id' => 'Unit aset tidak valid untuk barang ini.',
                ]);
            }

            $from = (string) $unit->current_status;

            // Reaktivasi dari perbaikan mewajibkan pemilihan kondisi hasil
            // perbaikan (bawaan baik); dicatat sebagai riwayat kondisi.
            if ($from === 'dalam_perbaikan' && $toStatus === 'aktif') {
                $resultCondition = $validated['result_condition'] ?? AssetCondition::BAIK;

                if ($resultCondition !== $unit->current_condition) {
                    ConditionHistory::create([
                        'item_id' => $item->id,
                        'asset_unit_id' => $unit->id,
                        'from_condition' => $unit->current_condition,
                        'to_condition' => $resultCondition,
                        'user_id' => $request->user()->id,
                        'notes' => 'Kondisi hasil perbaikan unit '.$unit->unit_inventory_number,
                        'recorded_at' => now(),
                    ]);

                    $unit->current_condition = $resultCondition;
                    $unit->save();
                }
            }

            $history = $unit->changeStatus($toStatus, $validated['reason'] ?? null, $request->user());

            ActivityLog::log(
                'unit_status_changed',
                "Status unit {$unit->unit_inventory_number} ({$item->name}) diubah: "
                    .(AssetUnit::STATUS_LABELS[$from] ?? $from).' → '.(AssetUnit::STATUS_LABELS[$toStatus] ?? $toStatus)
                    .($history->reason ? ' ('.$history->reason.')' : ''),
                $unit,
                ['current_status' => $from],
                ['current_status' => $toStatus, 'asset_unit_status_history_id' => $history->id],
                $item->code
            );
        });

        return back()->with('success', 'Status unit aset berhasil diperbarui (riwayat otomatis tercatat).');
    }

    /**
     * Perubahan kondisi per unit aset individual.
     */
    private function updateUnitCondition(Item $item, array $validated, Request $request): RedirectResponse
    {
        if (empty($validated['asset_unit_id'])) {
            throw ValidationException::withMessages([
                'asset_unit_id' => 'Silakan pilih unit aset yang diubah kondisinya.',
            ]);
        }

        DB::transaction(function () use ($item, $validated, $request) {
            $unit = AssetUnit::where('item_id', $item->id)
                ->whereKey((int) $validated['asset_unit_id'])
                ->lockForUpdate()
                ->first();

            if (! $unit) {
                throw ValidationException::withMessages([
                    'asset_unit_id' => 'Unit aset tidak valid untuk barang ini.',
                ]);
            }

            if ($unit->current_condition === $validated['to_condition']) {
                throw ValidationException::withMessages([
                    'msg' => 'Kondisi unit aset sama dengan kondisi saat ini.',
                ]);
            }

            $oldCondition = (string) $unit->current_condition;
            $history = $unit->changeCondition(
                $validated['to_condition'],
                $validated['notes'] ?? 'Pembaruan kondisi fisik unit aset',
                $request->user()
            );

            // Kondisi agregat item mengikuti kondisi terburuk antar unit agar
            // tampilan master tetap bermakna.
            $item->current_condition = $item->recalculateAggregateCondition();
            $item->current_status = $item->recalculateAggregateStatus();
            $item->save();

            ActivityLog::log(
                'item_condition_updated',
                "Kondisi unit {$unit->unit_inventory_number} ({$item->name}) diubah: "
                    ."{$oldCondition} → {$validated['to_condition']}"
                    .($history->to_condition === 'hilang' || $history->to_condition === 'tidak_layak_pakai' ? ' (status unit otomatis diperbarui)' : ''),
                $unit,
                ['current_condition' => $oldCondition],
                ['current_condition' => $validated['to_condition'], 'condition_history_id' => $history->id],
                $item->code
            );
        });

        return back()->with('success', 'Riwayat perubahan kondisi unit berhasil dicatat.');
    }
}
