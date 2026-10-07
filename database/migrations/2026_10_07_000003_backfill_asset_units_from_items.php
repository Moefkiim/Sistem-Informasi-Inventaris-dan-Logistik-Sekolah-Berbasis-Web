<?php

use App\Services\AssetUnitBackfiller;
use Illuminate\Database\Migrations\Migration;

/**
 * Section 2 — Backfill data lama items (individual) menjadi baris asset_units.
 *
 * Idempotent: item yang sudah punya unit dilewati. Verifikasi count:
 * jika jumlah unit ter-backfill tidak sama dengan jumlah yang diharapkan,
 * transaksi di-batalkan dan migrasi gagal transparan (tidak menulis data
 * sebagian). Aman pada DB kosong (fresh -> no-op) maupun DB berisi data.
 */
return new class extends Migration
{
    public function up(): void
    {
        $result = app(AssetUnitBackfiller::class)->run();

        echo sprintf(
            '  > Backfill asset_units: expected=%d created=%d backfilled_loans=%d'.PHP_EOL,
            $result['expected'],
            $result['created'],
            $result['backfilled_loans']
        );
    }

    public function down(): void
    {
        // Data migration: tidak menghapus unit secara destruktif saat rollback.
    }
};
