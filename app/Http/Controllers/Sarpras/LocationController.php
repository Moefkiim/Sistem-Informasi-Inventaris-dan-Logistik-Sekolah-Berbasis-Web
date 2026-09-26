<?php

namespace App\Http\Controllers\Sarpras;

use App\Http\Controllers\Controller;
use App\Models\Location;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LocationController extends Controller
{
    public function index(): View
    {
        $locations = Location::withCount('items')->latest()->paginate(15);
        return view('sarpras.locations.index', compact('locations'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:locations,code'],
            'name' => ['required', 'string', 'max:255'],
            'building' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
        ]);

        Location::create($validated);

        return back()->with('success', 'Master lokasi berhasil ditambahkan.');
    }

    public function destroy(Location $location): RedirectResponse
    {
        if ($location->items()->count() > 0) {
            return back()->withErrors(['msg' => 'Lokasi tidak dapat dihapus karena masih menampung barang inventaris.']);
        }

        $location->delete();

        return back()->with('success', 'Lokasi berhasil dihapus/diarsipkan.');
    }
}
