<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BUG-4: pisahkan "siapa yang mencatat" dari "siapa yang meminjam".
 *
 * Sejak awal migration create_loans merancang borrower_user_id sebagai
 * "user terdaftar yang meminjam" (nullable), sedangkan borrower_name
 * mencatat nama bebas termasuk pihak luar sistem. Namun implementasi
 * LoanController::store selama ini mengisi borrower_user_id dengan ID
 * Sarpras yang MENCATAT — semantiknya salah.
 *
 * Kolom baru recorded_by menampung pencatat (konsisten dengan pola
 * approved_by / returned_to pada tabel yang sama). Data lama yang pernah
 * terlanjur menaruh pencatat di borrower_user_id dipindahkan ke
 * recorded_by, lalu borrower_user_id dikosongkan kembali (datanya memang
 * tidak pernah menyimpan peminjam asli, hanya nama).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->foreignId('recorded_by')->nullable()->after('borrower_user_id')
                ->constrained('users')->nullOnDelete();
        });

        DB::statement('UPDATE loans SET recorded_by = borrower_user_id WHERE borrower_user_id IS NOT NULL');
        DB::statement('UPDATE loans SET borrower_user_id = NULL');
    }

    public function down(): void
    {
        DB::statement('UPDATE loans SET borrower_user_id = recorded_by WHERE recorded_by IS NOT NULL');

        Schema::table('loans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recorded_by');
        });
    }
};
