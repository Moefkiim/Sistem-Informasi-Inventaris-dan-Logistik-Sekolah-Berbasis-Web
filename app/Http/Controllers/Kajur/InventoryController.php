<?php

namespace App\Http\Controllers\Kajur;

use App\Http\Controllers\Controller;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryController extends Controller
{
    /**
     * Menampilkan daftar inventaris yang terikat dengan jurusan Kajur.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        if (empty($user->department)) {
            abort(403, 'Jurusan akun Kajur belum diatur.');
        }

        $department = $user->department;

        $search = $request->query('search');
        $category = $request->query('category');
        $condition = $request->query('condition');
        $status = $request->query('status');
        $stok = $request->query('stok');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $query = Item::where('department', $department)
            ->with(['location', 'assetUnits.location']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%")
                    ->orWhere('inventory_number', 'like', "%{$search}%")
                    ->orWhere('serial_number', 'like', "%{$search}%")
                    ->orWhereHas('assetUnits', fn ($u) => $u->where('unit_inventory_number', 'like', "%{$search}%")->orWhere('serial_number', 'like', "%{$search}%"));
            });
        }

        if ($category) {
            $query->where('category', $category);
        }

        if ($condition) {
            $query->where(function ($q) use ($condition) {
                $q->where('current_condition', $condition)
                    ->orWhereHas('assetUnits', fn ($u) => $u->where('current_condition', $condition));
            });
        }

        if ($status) {
            $query->where(function ($q) use ($status) {
                $q->where('current_status', $status)
                    ->orWhereHas('assetUnits', fn ($u) => $u->where('current_status', $status));
            });
        }

        if ($stok) {
            if ($stok === 'menipis') {
                $query->where('item_type', 'consumable')
                    ->where('stock', '>', 0)
                    ->where('minimum_stock', '>', 0)
                    ->whereColumn('stock', '<=', 'minimum_stock');
            } elseif ($stok === 'habis') {
                $query->where('item_type', 'consumable')
                    ->where('stock', '<=', 0);
            }
        }

        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        $items = $query->latest('created_at')->paginate(10)->withQueryString();
        $categories = Item::where('department', $department)->whereNotNull('category')->distinct()->pluck('category');

        return view('kajur.inventory.index', compact('items', 'department', 'search', 'category', 'condition', 'status', 'stok', 'startDate', 'endDate', 'categories'));
    }

    /**
     * Detail barang inventaris jurusan.
     */
    public function show(Item $item): View
    {
        $user = auth()->user();
        if (empty($user->department)) {
            abort(403, 'Jurusan akun Kajur belum diatur.');
        }

        if (empty($item->department) || $item->department !== $user->department) {
            abort(403, 'Akses ditolak: Anda hanya berhak melihat inventaris jurusan Anda sendiri.');
        }

        $item->load([
            'location',
            'locationHistories.assetUnit',
            'locationHistories.fromLocation',
            'locationHistories.toLocation',
            'conditionHistories.assetUnit',
            'activeLoans.borrower',
            'activeLoans.assetUnit',
            'assetUnits.location',
        ]);

        return view('kajur.inventory.show', compact('item'));
    }
}
