<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Section 2 — Tabel unit fisik aset individual (asset_units).
 *
 * items tetap "master kode barang"; asset_units adalah 1-ke-banyak unit fisik.
 * Untuk barang individual: satu baris unit = satu benda fisik yang bisa dilacak
 * (nomor unit, serial, kondisi, status, lokasi). Barang consumable tetap tidak
 * memakai tabel ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->string('unit_inventory_number')->unique();
            $table->string('serial_number')->nullable();
            $table->enum('current_condition', ['baik', 'rusak_ringan', 'rusak_berat'])->default('baik');
            $table->enum('current_status', [
                'aktif',
                'dipinjam',
                'dalam_perbaikan',
                'tidak_aktif',
                'disposed',
            ])->default('aktif');
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            // Penanda D3: unit hasil migrasi otomatis (nomor sintetis / identitas
            // diwarisi dari baris items lama) dibedakan dari unit yang didaftarkan
            // manual dengan data lengkap.
            $table->boolean('is_legacy_migrated')->default(false);
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['item_id', 'current_status']);
            $table->index('location_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_units');
    }
};
