<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P1: Audit Trail (Activity Log)
 *
 * Memperkuat audit trail dengan mencatat semua aktivitas penting:
 * - user, role, action, model, ID, waktu, nilai sebelum, nilai sesudah, IP
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();

            // Siapa yang melakukan
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name')->nullable();  // Snapshot nama (tahan hapus user)
            $table->string('user_role')->nullable();  // Snapshot role saat tindakan dilakukan

            // Apa yang dilakukan
            $table->string('action');                 // Contoh: create, update, delete, approve, reject, loan, return
            $table->string('description')->nullable(); // Keterangan bebas

            // Data yang terpengaruh
            $table->string('auditable_type')->nullable(); // Nama model (App\Models\Item, dll.)
            $table->unsignedBigInteger('auditable_id')->nullable(); // ID record
            $table->string('auditable_label')->nullable(); // Label/kode record (INV-001, REQ-001)

            // Perubahan nilai
            $table->json('old_values')->nullable();   // Nilai sebelum perubahan
            $table->json('new_values')->nullable();   // Nilai sesudah perubahan

            // Context
            $table->string('ip_address', 45)->nullable(); // IPv4 atau IPv6
            $table->string('user_agent')->nullable();

            $table->timestamp('logged_at')->useCurrent();
            $table->timestamps();

            // Index
            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['user_id', 'logged_at']);
            $table->index('action');
            $table->index('logged_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
