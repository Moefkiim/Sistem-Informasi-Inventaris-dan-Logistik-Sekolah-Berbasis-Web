<?php

namespace App\View\Composers;

use App\Services\TaskOverview;
use Illuminate\View\View;

/**
 * Menyediakan data header (lonceng notifikasi) dan badge sidebar untuk
 * seluruh halaman yang memakai layout aplikasi.
 */
class NavigationComposer
{
    public function __construct(private TaskOverview $taskOverview) {}

    public function compose(View $view): void
    {
        $user = auth()->user();

        if (! $user) {
            return;
        }

        $view->with([
            'navNotifications' => $user->notifications()->latest()->limit(8)->get(),
            'navUnreadCount' => $user->notifications()->whereNull('read_at')->count(),
            'sidebarBadges' => $this->taskOverview->badges($user),
        ]);
    }
}
