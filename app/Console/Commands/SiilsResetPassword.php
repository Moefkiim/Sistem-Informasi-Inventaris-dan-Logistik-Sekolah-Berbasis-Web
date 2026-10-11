<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\User;
use App\Support\TemporaryPassword;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Reset kata sandi akun lewat terminal server.
 *
 * Ditujukan untuk Sarpras/Kepala Sekolah yang lupa kata sandi ketika tidak ada
 * Sarpras lain yang bisa mereset lewat antarmuka. Kata sandi sementara hanya
 * ditampilkan sekali dan wajib diganti saat login berikutnya.
 */
class SiilsResetPassword extends Command
{
    protected $signature = 'siils:reset-password {username : Username akun yang akan direset}';

    protected $description = 'Mereset kata sandi akun menjadi kata sandi sementara (wajib ganti saat login)';

    public function handle(): int
    {
        $username = (string) $this->argument('username');

        $user = User::where('username', $username)->first();

        if (! $user) {
            $this->components->error("Pengguna dengan username '{$username}' tidak ditemukan.");

            return self::FAILURE;
        }

        $temporary = TemporaryPassword::generate();

        // password di-cast 'hashed' sehingga nilai mentah otomatis di-hash saat disimpan
        $user->password = $temporary;
        $user->must_change_password = true;
        $user->save();

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->delete();
        }

        ActivityLog::log(
            action: 'user_password_reset',
            description: "Kata sandi akun {$user->name} ({$user->username}) direset lewat terminal; wajib diganti saat login berikutnya.",
            auditable: $user,
            label: $user->username,
        );

        $this->newLine();
        $this->components->info("Kata sandi sementara untuk '{$user->username}' ({$user->name}):");
        $this->line("  <fg=bright-white;options=bold>{$temporary}</>");
        $this->newLine();
        $this->components->warn('Kata sandi ini hanya ditampilkan sekali. Pengguna wajib menggantinya saat login berikutnya.');

        return self::SUCCESS;
    }
}
