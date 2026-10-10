<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Section 2 — Kolom asset_unit_id nullable pada tabel yang menyimpan riwayat
 * per-unit: loans, condition_histories, location_histories.
 *
 * Nullable supaya data lama tetap utuh; baris consumable dan histori "pra
 * per-unit" yang ambigu tetap memakai granularitas item_id (kode barang).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->foreignId('asset_unit_id')->nullable()->after('item_id')->constrained('asset_units')->nullOnDelete();
        });
        $this->indexColumn('loans', 'asset_unit_id');

        Schema::table('condition_histories', function (Blueprint $table) {
            $table->foreignId('asset_unit_id')->nullable()->after('item_id')->constrained('asset_units')->nullOnDelete();
        });
        $this->indexColumn('condition_histories', 'asset_unit_id');

        Schema::table('location_histories', function (Blueprint $table) {
            $table->foreignId('asset_unit_id')->nullable()->after('item_id')->constrained('asset_units')->nullOnDelete();
        });
        $this->indexColumn('location_histories', 'asset_unit_id');
    }

    public function down(): void
    {
        foreach (['loans', 'condition_histories', 'location_histories'] as $table) {
            // Indeks eksplisit harus dilepas sebelum kolomnya, jika tidak
            // rollback gagal di SQLite ("error in index ... after drop column").
            if (Schema::hasIndex($table, ['asset_unit_id'])) {
                Schema::table($table, fn (Blueprint $t) => $t->dropIndex(['asset_unit_id']));
            }
        }

        Schema::table('loans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('asset_unit_id');
        });
        Schema::table('condition_histories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('asset_unit_id');
        });
        Schema::table('location_histories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('asset_unit_id');
        });
    }

    private function indexColumn(string $table, string $column): void
    {
        if (! Schema::hasIndex($table, [$column])) {
            Schema::table($table, fn (Blueprint $t) => $t->index($column));
        }
    }
};
