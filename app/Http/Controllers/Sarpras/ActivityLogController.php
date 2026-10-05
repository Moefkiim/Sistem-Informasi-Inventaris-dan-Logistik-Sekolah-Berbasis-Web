<?php

namespace App\Http\Controllers\Sarpras;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $query = ActivityLog::with('user')->orderByDesc('logged_at');

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('user_name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('auditable_label', 'like', "%{$search}%");
            });
        }

        if ($request->filled('start_date')) {
            $query->whereDate('logged_at', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('logged_at', '<=', $request->end_date);
        }

        $logs = $query->paginate(25)->withQueryString();
        $actions = ActivityLog::distinct()->orderBy('action')->pluck('action');

        return view('sarpras.activity_logs.index', compact('logs', 'actions'));
    }
}
