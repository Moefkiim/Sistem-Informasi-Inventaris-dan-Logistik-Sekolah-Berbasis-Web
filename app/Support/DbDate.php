<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Helper ekspresi tanggal yang sadar-driver.
 *
 * SQLite, MySQL, dan MariaDB memakai fungsi format tanggal yang berbeda.
 * Semua pemakaian ekspresi tanggal mentah di query harus lewat helper ini
 * agar kode berjalan sama di ketiga driver (dan di CI yang menguji MySQL).
 *
 * Nama kolom WAJIB berasal dari daftar putih (bukan input pengguna) untuk
 * mencegah injeksi SQL, karena ekspresi ini disisipkan ke selectRaw().
 */
class DbDate
{
    /**
     * Daftar kolom tanggal/timestamp yang diizinkan.
     *
     * @var array<int,string>
     */
    private const ALLOWED_COLUMNS = [
        'entry_date',
        'exit_date',
        'distribution_date',
        'moved_at',
        'recorded_at',
        'loan_date',
        'due_date',
        'return_date',
        'created_at',
        'updated_at',
        'reviewed_at',
        'approved_at',
    ];

    /**
     * Ekspresi SQL yang menghasilkan kunci bulan 'YYYY-MM' untuk sebuah kolom.
     */
    public static function monthKey(string $column): string
    {
        self::assertColumn($column);

        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', {$column})",
            'pgsql' => "to_char({$column}, 'YYYY-MM')",
            default => "DATE_FORMAT({$column}, '%Y-%m')",
        };
    }

    /**
     * Pastikan kolom berasal dari daftar putih.
     */
    private static function assertColumn(string $column): void
    {
        if (! in_array($column, self::ALLOWED_COLUMNS, true)) {
            throw new \InvalidArgumentException("Kolom tanggal tidak diizinkan: {$column}");
        }
    }
}
