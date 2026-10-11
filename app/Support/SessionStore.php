<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Pembatalan sesi pengguna pada driver sesi database.
 *
 * Driver sesi selain database tidak menyimpan sesi sebagai baris yang bisa
 * dicabut, sehingga tidak bisa membublikasi sesi lain secara menyeluruh;
 * pada kasus itu metodenya tidak melakukan apa-apa (keterbatasan terdokumentasi).
 */
class SessionStore
{
    public static function forgetForUser(User $user, ?string $keepSessionId = null): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->when($keepSessionId !== null, fn ($query) => $query->where('id', '!=', $keepSessionId))
            ->delete();
    }
}
