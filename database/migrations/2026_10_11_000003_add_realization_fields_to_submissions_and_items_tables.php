<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Realisasi pengajuan:
 * - status pengajuan diperluas menjadi string agar nilai baru `realized`
 *   (Terealisasi) dapat ditambahkan tanpa mendefinisikan ulang enum per driver
 *   (SQLite, MySQL, MariaDB). Validasi nilai tetap dijaga di lapisan aplikasi.
 * - kolom realisasi pada submissions dan tautan barang pada submission_items.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->string('status', 20)->default('draft')->change();
            $table->foreignId('realized_by')->nullable()->after('principal_user_id')->constrained('users')->nullOnDelete();
            $table->timestamp('realized_at')->nullable()->after('decided_at');
            $table->text('realization_notes')->nullable()->after('realized_at');
        });

        Schema::table('submission_items', function (Blueprint $table) {
            $table->foreignId('item_id')->nullable()->after('submission_id')->constrained('items')->nullOnDelete();
            $table->unsignedInteger('received_quantity')->nullable()->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('submission_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('item_id');
            $table->dropColumn('received_quantity');
        });

        Schema::table('submissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('realized_by');
            $table->dropColumn(['realized_at', 'realization_notes']);
        });
    }
};
