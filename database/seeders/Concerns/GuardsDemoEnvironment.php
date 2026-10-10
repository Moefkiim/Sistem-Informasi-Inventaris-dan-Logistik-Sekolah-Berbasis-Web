<?php

namespace Database\Seeders\Concerns;

use App\Models\User;
use Closure;
use Illuminate\Support\Facades\App;

/**
 * Penjaga agar data demo tidak pernah masuk ke lingkungan produksi tanpa
 * persetujuan eksplisit. Set SIILS_ALLOW_DEMO_SEED=true bila memang ingin
 * menanam data demo di produksi (tidak disarankan).
 */
trait GuardsDemoEnvironment
{
    protected function demoDataAllowed(): bool
    {
        if (! App::environment('production')) {
            return true;
        }

        return filter_var(env('SIILS_ALLOW_DEMO_SEED', false), FILTER_VALIDATE_BOOL);
    }

    /**
     * Jalankan aksi seeder seolah-olah dilakukan oleh $user tanpa merusak
     * status autentikasi yang sedang berjalan (penting saat seeding di dalam
     * test yang memakai guard yang sama).
     *
     * @template TReturn
     *
     * @param  Closure():TReturn  $callback
     * @return TReturn
     */
    protected function runAsUser(User $user, Closure $callback): mixed
    {
        $previous = auth()->user();
        auth()->setUser($user);

        try {
            return $callback();
        } finally {
            if ($previous instanceof User) {
                auth()->setUser($previous);
            } else {
                auth()->forgetUser();
            }
        }
    }
}
