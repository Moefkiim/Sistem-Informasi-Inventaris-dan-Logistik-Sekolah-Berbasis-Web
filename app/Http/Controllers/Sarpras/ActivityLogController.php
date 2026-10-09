<?php

namespace App\Http\Controllers\Sarpras;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    private const PER_PAGE_OPTIONS = [25, 50, 100];

    public function index(Request $request): View
    {
        $query = ActivityLog::with('user')->orderByDesc('logged_at');

        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('user_name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('auditable_label', 'like', "%{$search}%");
            });
        }

        if ($request->filled('start_date')) {
            $query->whereDate('logged_at', '>=', $request->input('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->whereDate('logged_at', '<=', $request->input('end_date'));
        }

        $perPage = (int) $request->input('per_page', 25);
        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = 25;
        }

        $logs = $query->paginate($perPage)->withQueryString();
        $actionGroups = ActivityLog::groupedActionOptions(
            ActivityLog::distinct()->orderBy('action')->pluck('action')
        );

        $hasFilters = $request->filled('search')
            || $request->filled('action')
            || $request->filled('start_date')
            || $request->filled('end_date');

        return view('sarpras.activity_logs.index', [
            'logs' => $logs,
            'actionGroups' => $actionGroups,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'perPage' => $perPage,
            'hasFilters' => $hasFilters,
        ]);
    }
}
