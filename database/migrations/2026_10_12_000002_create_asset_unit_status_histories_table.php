<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Riwayat perubahan status unit aset (append-only, audit trail).
     * Data tidak diubah/dihapus melalui aplikasi.
     */
    public function up(): void
    {
        Schema::create('asset_unit_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_unit_id')->constrained('asset_units')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items');
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->text('reason')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('recorded_at')->useCurrent();

            $table->index(['asset_unit_id', 'recorded_at']);
            $table->index(['item_id', 'recorded_at']);
            $table->index('to_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_unit_status_histories');
    }
};
