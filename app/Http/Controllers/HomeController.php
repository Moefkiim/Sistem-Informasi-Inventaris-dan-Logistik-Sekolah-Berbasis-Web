<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Location;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $user = auth()->user();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Selamat datang di Sistem Informasi Inventaris dan Logistik Sekolah',
                'user' => [
                    'name' => $user->name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'role' => $user->role,
                    'department' => $user->department,
                ],
            ]);
        }

        $department = $this->resolveDepartment($user);

        $totalItems = Item::query()
            ->when($department !== null, fn ($q) => $q->where('department', $department))
            ->count();

        $totalLocations = Location::query()
            ->when($department !== null, fn ($q) => $q->where('department', $department))
            ->count();

        $pendingSubmissions = Submission::query()
            ->when($department !== null, fn ($q) => $q->where('department', $department))
            ->whereIn('status', ['submitted', 'reviewed_sarpras'])
            ->count();

        return view('home', compact('user', 'totalItems', 'totalLocations', 'pendingSubmissions'));
    }

    /**
     * Kajur dikunci ke jurusannya; Sarpras & Kepala Sekolah bersifat school-wide.
     */
    private function resolveDepartment(User $user): ?string
    {
        if (! $user->isKajur()) {
            return null;
        }

        if (empty($user->department)) {
            abort(403, 'Akses ditolak: Jurusan akun Kajur belum diatur.');
        }

        return $user->department;
    }
}
