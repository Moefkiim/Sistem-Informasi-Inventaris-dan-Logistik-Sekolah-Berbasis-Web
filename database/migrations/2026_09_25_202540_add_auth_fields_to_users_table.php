<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->unique()->after('name');
            $table->string('role')->default('kajur')->after('email'); // 'kajur', 'sarpras', 'kepala_sekolah'
            $table->string('department')->nullable()->after('role'); // jurusan untuk Kajur (misal: RPL, TKJ, dll)
            $table->boolean('is_active')->default(true)->after('department');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['username', 'role', 'department', 'is_active']);
        });
    }
};
