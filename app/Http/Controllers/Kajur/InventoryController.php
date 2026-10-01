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

        $items = Item::where('department', $department)
            ->with(['location'])
            ->latest()
            ->paginate(10);

        return view('kajur.inventory.index', compact('items', 'department'));
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

        $item->load(['location', 'locationHistories.fromLocation', 'locationHistories.toLocation', 'conditionHistories']);

        return view('kajur.inventory.show', compact('item'));
    }
}
