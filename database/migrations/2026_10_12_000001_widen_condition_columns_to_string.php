<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Perluas kolom kondisi dari enum 3 nilai (baik, rusak_ringan, rusak_berat)
     * menjadi string(20) agar nilai tidak_layak_pakai dan hilang dapat dipakai
     * tanpa migrasi skema lagi. Status aset TIDAK diubah (tetap enum).
     */
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->string('current_condition', 20)->default('baik')->change();
        });

        Schema::table('asset_units', function (Blueprint $table) {
            $table->string('current_condition', 20)->default('baik')->change();
        });

        Schema::table('condition_histories', function (Blueprint $table) {
            $table->string('from_condition', 20)->change();
            $table->string('to_condition', 20)->change();
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->string('condition_on_loan', 20)->nullable()->change();
            $table->string('condition_on_return', 20)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Downgrade non-destruktif: kolom dibiarkan string agar data yang
        // sudah memakai nilai baru (tidak_layak_pakai/hilang) tidak rusak.
    }
};
