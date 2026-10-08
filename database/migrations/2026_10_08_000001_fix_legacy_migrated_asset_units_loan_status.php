<?php

use App\Models\Loan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Migration korektif Section 2:
 * Memperbaiki unit legacy hasil migrasi (is_legacy_migrated = true) yang
 * berstatus 'dipinjam' padahal tidak memiliki peminjaman aktif ('dipinjam' / 'terlambat').
 *
 * Mengubah status unit-unit tersebut kembali menjadi 'aktif'.
 * Idempotent, konservatif, aman pada DB kosong, dan down() non-destruktif.
 */
return new class extends Migration
{
    public function up(): void
    {
        $activeStatuses = Loan::STATUS_BORROWED;

        $affected = DB::table('asset_units')
            ->where('is_legacy_migrated', true)
            ->where('current_status', 'dipinjam')
            ->whereNotExists(function ($query) use ($activeStatuses) {
                $query->select(DB::raw(1))
                    ->from('loans')
                    ->whereColumn('loans.asset_unit_id', 'asset_units.id')
                    ->whereIn('loans.status', $activeStatuses);
            })
            ->update(['current_status' => 'aktif']);

        echo sprintf(
            '  > Koreksi status asset_units legacy: %d unit diubah dari dipinjam ke aktif'.PHP_EOL,
            $affected
        );
    }

    public function down(): void
    {
        // Data migration korektif: tidak mengembalikan status unit ke status tidak konsisten saat rollback.
    }
};
