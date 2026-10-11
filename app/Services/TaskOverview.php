<?php

namespace App\Services;

use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Menghitung badge penghitung sidebar dan daftar "Tugas Anda" per peran.
 *
 * Badge dihitung lewat satu query agregat per peran (tanpa N+1) dan
 * memperhatikan cakupan jurusan untuk Kajur.
 */
class TaskOverview
{
    /**
     * @return array<string, int>
     */
    public function badges(User $user): array
    {
        if ($user->isSarpras()) {
            $counts = Submission::query()
                ->whereIn('status', ['submitted', 'approved'])
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status');

            return [
                'review' => (int) ($counts['submitted'] ?? 0),
                'realize' => (int) ($counts['approved'] ?? 0),
            ];
        }

        if ($user->isKepalaSekolah()) {
            return [
                'approval' => (int) Submission::query()
                    ->where('status', 'reviewed_sarpras')
                    ->count(),
            ];
        }

        if ($user->isKajur()) {
            return [
                'draft' => (int) Submission::query()
                    ->where('user_id', $user->id)
                    ->where('status', 'draft')
                    ->count(),
            ];
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
            return $this->map(
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
            return $this->map(
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
