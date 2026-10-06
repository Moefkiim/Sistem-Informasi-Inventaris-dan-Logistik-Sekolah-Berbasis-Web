<?php

namespace App\Http\Controllers;

use App\Models\IncomingItem;
use App\Models\Item;
use App\Models\Location;
use App\Models\OutgoingItem;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * Label baku kondisi/status aset (urutan penampilan grafik).
     */
    private const STATUS_LABELS = [
        'aktif' => 'Aktif',
        'dipinjam' => 'Dipinjam',
        'dalam_perbaikan' => 'Dalam Perbaikan',
        'tidak_aktif' => 'Tidak Aktif',
        'disposed' => 'Dihapuskan',
    ];

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
            ->when($department !== null, fn ($q) => $q->where('items.department', $department))
            ->count();

        $totalLocations = Location::query()
            ->when($department !== null, fn ($q) => $q->where('department', $department))
            ->count();

        $pendingSubmissions = Submission::query()
            ->when($department !== null, fn ($q) => $q->where('department', $department))
            ->whereIn('status', ['submitted', 'reviewed_sarpras'])
            ->count();

        $charts = $this->chartData($department);

        return view('home', compact('user', 'totalItems', 'totalLocations', 'pendingSubmissions', 'charts'));
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

    /**
     * Data agregat untuk grafik dashboard; mengikuti scoping department yang sama.
     */
    private function chartData(?string $department): array
    {
        $items = Item::query()
            ->when($department !== null, fn ($q) => $q->where('items.department', $department));

        $conditionCounts = (clone $items)
            ->selectRaw('current_status as key, count(*) as total')
            ->groupBy('current_status')
            ->pluck('total', 'key');

        $conditionsLabels = [];
        $conditionsData = [];
        foreach (self::STATUS_LABELS as $key => $label) {
            if (! $conditionCounts->has($key)) {
                continue;
            }
            $conditionsLabels[] = $label;
            $conditionsData[] = (int) $conditionCounts->get($key);
        }

        $locationCounts = (clone $items)
            ->selectRaw("COALESCE(locations.name, 'Tanpa Lokasi') as key, count(*) as total")
            ->leftJoin('locations', 'items.location_id', '=', 'locations.id')
            ->groupBy('key')
            ->pluck('total', 'key');

        $departmentCounts = $department === null
            ? (clone $items)->selectRaw("COALESCE(items.department, 'Umum') as key, count(*) as total")
                ->groupBy('key')
                ->pluck('total', 'key')
            : collect();

        return [
            'conditions' => [
                'labels' => $conditionsLabels,
                'data' => $conditionsData,
            ],
            'locations' => [
                'labels' => array_values($locationCounts->keys()->all()),
                'data' => array_values($locationCounts->map(fn ($v) => (int) $v)->all()),
            ],
            'departments' => $department === null ? [
                'labels' => array_values($departmentCounts->keys()->all()),
                'data' => array_values($departmentCounts->map(fn ($v) => (int) $v)->all()),
            ] : null,
            'trend' => $this->monthlyTrend($department),
        ];
    }

    /**
     * Tren barang masuk/keluar 12 bulan terakhir (sum quantity per bulan).
     */
    private function monthlyTrend(?string $department): array
    {
        $months = [];
        $labels = [];
        for ($offset = 11; $offset >= 0; $offset--) {
            $date = now()->startOfMonth()->subMonths($offset);
            $months[] = $date->format('Y-m');
            $labels[] = $date->translatedFormat('M y');
        }

        $start = $months[0].'-01';
        $end = $months[11].'-31';

        $incoming = IncomingItem::query()
            ->when($department !== null, fn ($q) => $q->whereHas('item', fn ($i) => $i->where('department', $department)))
            ->whereBetween('entry_date', [$start, $end])
            ->selectRaw("strftime('%Y-%m', entry_date) as month, sum(quantity) as total")
            ->groupBy('month')
            ->pluck('total', 'month');

        $outgoing = OutgoingItem::query()
            ->when($department !== null, fn ($q) => $q->whereHas('item', fn ($i) => $i->where('department', $department)))
            ->whereBetween('exit_date', [$start, $end])
            ->selectRaw("strftime('%Y-%m', exit_date) as month, sum(quantity) as total")
            ->groupBy('month')
            ->pluck('total', 'month');

        return [
            'labels' => $labels,
            'incoming' => array_map(fn ($month) => (int) $incoming->get($month, 0), $months),
            'outgoing' => array_map(fn ($month) => (int) $outgoing->get($month, 0), $months),
        ];
    }
}
