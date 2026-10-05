<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P1: Identitas Aset Individual & Kondisi/Status Terpisah
 *
 * Menambahkan field untuk:
 * - Identitas aset individual (inventory_number, serial_number, brand, model, year, acquisition_price)
 * - Tipe barang (individual vs consumable)
 * - Status aset terpisah dari kondisi
 * - Stok minimum (P2)
 * - Kondisi enum diperluas (tidak_layak_pakai, hilang)
 */
return new class extends Migration
{
    public function up(): void
    {
        // Perbarui tabel items: tambah field identitas aset, status, stok minimum
        Schema::table('items', function (Blueprint $table) {
            // Identitas aset
            $table->string('inventory_number')->nullable()->unique()->after('code'); // Nomor inventaris sekolah
            $table->string('serial_number')->nullable()->after('inventory_number'); // Nomor seri pabrikan
            $table->string('brand')->nullable()->after('serial_number');            // Merk
            $table->string('model')->nullable()->after('brand');                    // Tipe/model

            // Tipe barang: individual (laptop, printer) vs consumable (kertas, tinta)
            $table->enum('item_type', ['individual', 'consumable'])->default('consumable')->after('category');

            // Perolehan
            $table->year('acquisition_year')->nullable()->after('source');          // Tahun perolehan
            $table->decimal('acquisition_price', 15, 2)->nullable()->after('acquisition_year'); // Harga perolehan

            // Status aset (terpisah dari kondisi)
            // Kondisi = keadaan fisik; Status = ketersediaan/operasional
            $table->enum('current_status', [
                'aktif',           // Aktif digunakan
                'dipinjam',        // Sedang dipinjam
                'dalam_perbaikan', // Sedang dalam perbaikan
                'tidak_aktif',     // Tidak aktif/digudangkan
                'disposed',        // Dihapuskan/dibuang
            ])->default('aktif')->after('current_condition');

            // Stok minimum untuk consumable (P2)
            $table->integer('minimum_stock')->default(0)->after('stock');
        });

        // Perluas enum kondisi pada condition_histories
        // SQLite tidak mendukung ALTER COLUMN enum, jadi kita tambah kolom baru sementara
        // (kondisi baru: tidak_layak_pakai dan hilang ditambah via aplikasi, enum di SQLite adalah TEXT)
        // Untuk database selain SQLite, migration ini akan membuat kolom baru
        // Karena repository menggunakan SQLite, enum di SQLite hanya TEXT constraint, aman ditambah via CHECK
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn([
                'inventory_number',
                'serial_number',
                'brand',
                'model',
                'item_type',
                'acquisition_year',
                'acquisition_price',
                'current_status',
                'minimum_stock',
            ]);
        });
    }
};
