<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P1: Modul Peminjaman dan Pengembalian Barang
 *
 * Tabel loans: mencatat peminjaman barang/aset
 * Status: menunggu → disetujui → dipinjam → dikembalikan
 *         (dapat juga: terlambat, ditolak)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();

            // Nomor peminjaman unik (format: LN-YYYYMMDD-XXXX)
            $table->string('loan_number')->unique();

            // Peminjam
            $table->foreignId('borrower_user_id')->nullable()->constrained('users')->nullOnDelete(); // User terdaftar
            $table->string('borrower_name');        // Nama peminjam (bisa dari luar sistem)
            $table->string('borrower_department')->nullable(); // Jurusan/unit

            // Barang yang dipinjam
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->integer('quantity')->default(1); // Untuk consumable; individual = 1

            // Tanggal
            $table->date('loan_date');              // Tanggal pinjam
            $table->date('due_date');               // Tanggal rencana kembali
            $table->date('return_date')->nullable(); // Tanggal pengembalian aktual

            // Keperluan
            $table->string('purpose');

            // Status alur peminjaman
            $table->enum('status', [
                'menunggu',    // Menunggu persetujuan Sarpras
                'disetujui',   // Disetujui, belum diambil
                'dipinjam',    // Sedang dipinjam
                'dikembalikan', // Sudah dikembalikan
                'terlambat',   // Melewati due_date belum dikembalikan
                'ditolak',     // Ditolak oleh Sarpras
            ])->default('menunggu');

            // Kondisi
            $table->enum('condition_on_loan', ['baik', 'rusak_ringan', 'rusak_berat'])->nullable(); // Kondisi saat dipinjam
            $table->enum('condition_on_return', ['baik', 'rusak_ringan', 'rusak_berat'])->nullable(); // Kondisi saat kembali

            // Pengelola
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete(); // Sarpras yang menyetujui
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('returned_to')->nullable()->constrained('users')->nullOnDelete(); // Sarpras yang menerima pengembalian
            $table->timestamp('returned_at')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Index untuk performa query
            $table->index(['item_id', 'status']);
            $table->index('loan_date');
            $table->index('due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
