<?php

namespace App\Http\Controllers\Sarpras;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Support\Departments;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LocationController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->query('search');
        $department = $request->query('department');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $query = Location::withCount(['items', 'assetUnits'])->latest('created_at');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('building', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($department) {
            $query->where('department', $department);
        }

        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        $locations = $query->paginate(15)->withQueryString();

        return view('sarpras.locations.index', compact('locations', 'search', 'department', 'startDate', 'endDate'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:locations,code'],
            'name' => ['required', 'string', 'max:255'],
            'building' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', Rule::in(Departments::codes())],
            'description' => ['nullable', 'string'],
        ]);

        Location::create($validated);

        return back()->with('success', 'Master lokasi berhasil ditambahkan.');
    }

    public function destroy(Location $location): RedirectResponse
    {
        if ($location->items()->count() > 0 || $location->assetUnits()->count() > 0) {
            return back()->withErrors(['msg' => 'Lokasi tidak dapat dihapus karena masih menampung barang inventaris atau unit aset.']);
        }

        $location->delete();

        return back()->with('success', 'Lokasi berhasil dihapus/diarsipkan.');
    }
}
