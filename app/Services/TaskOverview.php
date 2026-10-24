<?php

namespace App\Services;

use App\Models\AssetUnit;
use App\Models\Item;
use App\Models\Loan;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Menghitung badge penghitung sidebar, kartu tindakan dashboard, dan daftar "Tugas Anda" per peran.
 *
 * Badge dan statistik tindakan dihitung lewat query agregat per peran (tanpa N+1)
 * dan memperhatikan cakupan jurusan untuk Kajur.
 */
class TaskOverview
{
    /**
     * Statistik tindakan yang membutuhkan perhatian (stok, keterlambatan, perbaikan).
     *
     * @return array{low_stock: int, out_of_stock: int, overdue_loans: int, in_repair: int}
     */
    public function actionCounts(User $user): array
    {
        if ($user->isKajur()) {
            $department = $user->department;
            if (empty($department)) {
                return [
                    'low_stock' => 0,
                    'out_of_stock' => 0,
                    'overdue_loans' => 0,
                    'in_repair' => 0,
                ];
            }

            $lowStock = Item::query()
                ->where('department', $department)
                ->where('item_type', 'consumable')
                ->where('stock', '>', 0)
                ->where('minimum_stock', '>', 0)
                ->whereColumn('stock', '<=', 'minimum_stock')
                ->count();

            $outOfStock = Item::query()
                ->where('department', $department)
                ->where('item_type', 'consumable')
                ->where('stock', '<=', 0)
                ->count();

            $overdueLoans = Loan::query()
                ->overdue()
                ->where('borrower_department', $department)
                ->count();

            return [
                'low_stock' => $lowStock,
                'out_of_stock' => $outOfStock,
                'overdue_loans' => $overdueLoans,
                'in_repair' => 0,
            ];
        }

        $lowStock = Item::query()
            ->where('item_type', 'consumable')
            ->where('stock', '>', 0)
            ->where('minimum_stock', '>', 0)
            ->whereColumn('stock', '<=', 'minimum_stock')
            ->count();

        $outOfStock = Item::query()
            ->where('item_type', 'consumable')
            ->where('stock', '<=', 0)
            ->count();

        $overdueLoans = Loan::query()
            ->overdue()
            ->count();

        $inRepair = AssetUnit::query()
            ->where('current_status', 'dalam_perbaikan')
            ->count();

        return [
            'low_stock' => $lowStock,
            'out_of_stock' => $outOfStock,
            'overdue_loans' => $overdueLoans,
            'in_repair' => $inRepair,
        ];
    }

    /**
     * @return array<string, int>
     */
    public function badges(User $user): array
    {
        $actions = $this->actionCounts($user);

        if ($user->isSarpras()) {
            $counts = Submission::query()
                ->whereIn('status', ['submitted', 'approved'])
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status');

            $badges = [
                'review' => (int) ($counts['submitted'] ?? 0),
                'realize' => (int) ($counts['approved'] ?? 0),
            ];

            if ($actions['overdue_loans'] > 0) {
                $badges['overdue_loans'] = $actions['overdue_loans'];
            }
            if ($actions['low_stock'] > 0) {
                $badges['low_stock'] = $actions['low_stock'];
            }
            if ($actions['out_of_stock'] > 0) {
                $badges['out_of_stock'] = $actions['out_of_stock'];
            }
            if ($actions['in_repair'] > 0) {
                $badges['in_repair'] = $actions['in_repair'];
            }

            return $badges;
        }

        if ($user->isKepalaSekolah()) {
            $badges = [
                'approval' => (int) Submission::query()
                    ->where('status', 'reviewed_sarpras')
                    ->count(),
            ];

            if ($actions['overdue_loans'] > 0) {
                $badges['overdue_loans'] = $actions['overdue_loans'];
            }
            if ($actions['low_stock'] > 0) {
                $badges['low_stock'] = $actions['low_stock'];
            }
            if ($actions['out_of_stock'] > 0) {
                $badges['out_of_stock'] = $actions['out_of_stock'];
            }

            return $badges;
        }

        if ($user->isKajur()) {
            $badges = [
                'draft' => (int) Submission::query()
                    ->where('user_id', $user->id)
                    ->where('status', 'draft')
                    ->count(),
            ];

            if ($actions['low_stock'] > 0) {
                $badges['low_stock'] = $actions['low_stock'];
            }
            if ($actions['out_of_stock'] > 0) {
                $badges['out_of_stock'] = $actions['out_of_stock'];
            }
            if ($actions['overdue_loans'] > 0) {
                $badges['overdue_loans'] = $actions['overdue_loans'];
            }

            return $badges;
        }

        return [];
    }

    /**
     * Daftar tugas singkat (maksimal $limit) dengan tautan langsung.
     *
     * @return array<int, array{title: string, meta: string, badge: string, color: string, url: string}>
     */
    public function tasks(User $user, int $limit = 5): array
    {
        if ($user->isSarpras()) {
            $tasks = [];
            $actions = $this->actionCounts($user);

            if ($actions['overdue_loans'] > 0) {
                $tasks[] = [
                    'title' => 'Peminjaman Terlambat',
                    'meta' => "{$actions['overdue_loans']} transaksi peminjaman telah melewati batas tanggal kembali",
                    'badge' => 'Perlu Ditagih',
                    'color' => 'danger',
                    'url' => route('sarpras.loans.index', ['status' => 'terlambat']),
                ];
            }

            if ($actions['out_of_stock'] > 0) {
                $tasks[] = [
                    'title' => 'Stok Habis',
                    'meta' => "{$actions['out_of_stock']} jenis barang habis pakai memiliki stok 0",
                    'badge' => 'Habis',
                    'color' => 'danger',
                    'url' => route('sarpras.inventory.index', ['stok' => 'habis']),
                ];
            }

            if ($actions['low_stock'] > 0) {
                $tasks[] = [
                    'title' => 'Stok Menipis',
                    'meta' => "{$actions['low_stock']} jenis barang habis pakai berada di bawah batas minimum",
                    'badge' => 'Menipis',
                    'color' => 'warning',
                    'url' => route('sarpras.inventory.index', ['stok' => 'menipis']),
                ];
            }

            if ($actions['in_repair'] > 0) {
                $tasks[] = [
                    'title' => 'Unit Dalam Perbaikan',
                    'meta' => "{$actions['in_repair']} unit aset sedang dalam proses perbaikan",
                    'badge' => 'Servis',
                    'color' => 'info',
                    'url' => route('sarpras.inventory.index', ['status' => 'dalam_perbaikan']),
                ];
            }

            $submissions = $this->map(
                Submission::query()
                    ->whereIn('status', ['submitted', 'approved'])
                    ->orderByRaw("CASE status WHEN 'submitted' THEN 0 WHEN 'approved' THEN 1 ELSE 2 END")
                    ->latest('id')
                    ->limit($limit)
                    ->get(),
                fn (Submission $s) => [
                    'meta' => $s->submission_number.' · '.($s->department ?: 'Umum'),
                    'color' => $s->status === 'submitted' ? 'warning' : 'primary',
                    'url' => route('sarpras.submissions.show', $s),
                ]
            );

            return array_merge($tasks, $submissions);
        }

        if ($user->isKepalaSekolah()) {
            return $this->map(
                Submission::query()
                    ->where('status', 'reviewed_sarpras')
                    ->latest('id')
                    ->limit($limit)
                    ->get(),
                fn (Submission $s) => [
                    'meta' => $s->submission_number.' · '.($s->department ?: 'Umum'),
                    'color' => 'info',
                    'url' => route('kepala_sekolah.approval.show', $s),
                ]
            );
        }

        if ($user->isKajur()) {
            $tasks = [];
            $actions = $this->actionCounts($user);

            if ($actions['overdue_loans'] > 0) {
                $tasks[] = [
                    'title' => 'Peminjaman Terlambat Jurusan',
                    'meta' => "{$actions['overdue_loans']} peminjaman melewati batas tanggal kembali",
                    'badge' => 'Terlambat',
                    'color' => 'danger',
                    'url' => route('kajur.inventory.index'),
                ];
            }

            if ($actions['out_of_stock'] > 0) {
                $tasks[] = [
                    'title' => 'Stok Habis di Jurusan',
                    'meta' => "{$actions['out_of_stock']} jenis barang habis pakai habis",
                    'badge' => 'Habis',
                    'color' => 'danger',
                    'url' => route('kajur.inventory.index', ['stok' => 'habis']),
                ];
            }

            if ($actions['low_stock'] > 0) {
                $tasks[] = [
                    'title' => 'Stok Menipis di Jurusan',
                    'meta' => "{$actions['low_stock']} jenis barang habis pakai mendekati stok minimum",
                    'badge' => 'Menipis',
                    'color' => 'warning',
                    'url' => route('kajur.inventory.index', ['stok' => 'menipis']),
                ];
            }

            $submissions = $this->map(
                Submission::query()
                    ->where('user_id', $user->id)
                    ->where('status', 'draft')
                    ->latest('id')
                    ->limit($limit)
                    ->get(),
                fn (Submission $s) => [
                    'meta' => $s->submission_number,
                    'color' => 'secondary',
                    'url' => route('kajur.submissions.show', $s),
                ]
            );

            return array_merge($tasks, $submissions);
        }

        return [];
    }

    /**
     * @param  Collection<int, Submission>  $submissions
     * @param  callable(Submission): array{meta: string, color: string, url: string}  $extra
     * @return array<int, array{title: string, meta: string, badge: string, color: string, url: string}>
     */
    private function map($submissions, callable $extra): array
    {
        return $submissions->map(function (Submission $s) use ($extra) {
            return array_merge([
                'title' => $s->title,
                'badge' => Submission::statusLabel($s->status),
            ], $extra($s));
        })->all();
    }
}
