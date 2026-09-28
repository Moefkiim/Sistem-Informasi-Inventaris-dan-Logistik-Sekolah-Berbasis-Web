<?php

namespace App\Http\Controllers;

use App\Models\Distribution;
use App\Models\IncomingItem;
use App\Models\Item;
use App\Models\OutgoingItem;
use App\Models\Submission;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    /**
     * Halaman laporan inventaris dan transaksi logistik dengan filter date range.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $type = $request->get('type', 'inventory'); // inventory, incoming, outgoing, distribution, submission
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        $department = $request->get('department');

        // Jika user adalah Kajur, filter departemen otomatis terkunci ke jurusannya
        if ($user->isKajur()) {
            $department = $user->department;
        }

        $data = null;

        $isPrint = $request->get('export') === 'print';

        switch ($type) {
            case 'incoming':
                $query = IncomingItem::with(['item', 'user'])->latest('entry_date');
                if ($startDate && $endDate) {
                    $query->whereBetween('entry_date', [$startDate, $endDate]);
                }
                $data = $isPrint ? $query->get() : $query->paginate(20);
                break;

            case 'outgoing':
                $query = OutgoingItem::with(['item', 'user'])->latest('exit_date');
                if ($startDate && $endDate) {
                    $query->whereBetween('exit_date', [$startDate, $endDate]);
                }
                $data = $isPrint ? $query->get() : $query->paginate(20);
                break;

            case 'distribution':
                $query = Distribution::with(['item', 'toLocation', 'user'])->latest('distribution_date');
                if ($startDate && $endDate) {
                    $query->whereBetween('distribution_date', [$startDate, $endDate]);
                }
                if ($department) {
                    $query->where('recipient_department', $department);
                }
                $data = $isPrint ? $query->get() : $query->paginate(20);
                break;

            case 'submission':
                $query = Submission::with(['user', 'items'])->latest();
                if ($startDate && $endDate) {
                    $query->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
                }
                if ($department) {
                    $query->where('department', $department);
                }
                $data = $isPrint ? $query->get() : $query->paginate(20);
                break;

            case 'inventory':
            default:
                $query = Item::with(['location'])->latest();
                if ($department) {
                    $query->where('department', $department);
                }
                $data = $isPrint ? $query->get() : $query->paginate(20);
                break;
        }

        if ($isPrint) {
            return view('reports.print', compact('data', 'type', 'startDate', 'endDate', 'department'));
        }

        return view('reports.index', compact('data', 'type', 'startDate', 'endDate', 'department'));
    }
}
