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
        // 1. Tabel Lokasi (Gedung, Ruangan, Lab Jurusan)
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // Contoh: LAB-RPL-01, GDG-A-102
            $table->string('name');
            $table->string('building')->nullable();
            $table->string('department')->nullable(); // Terikat jurusan (misal RPL) atau Umum
            $table->text('description')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        // 2. Tabel Inventaris Barang (Master: 1 kode barang = 1 jenis barang, agregat stok)
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // Kode unik barang
            $table->string('name');
            $table->string('category')->default('Umum'); // Elektronik, Alat Praktik, Mebel, dll.
            $table->string('unit')->default('Unit'); // Unit, Pcs, Set, Box
            $table->integer('stock')->default(0);
            $table->enum('source', ['pembelian', 'bantuan'])->default('pembelian');
            $table->string('department')->nullable(); // Jurusan pemilik (misal RPL) atau null jika inventaris umum/sarpras
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->enum('current_condition', ['baik', 'rusak_ringan', 'rusak_berat'])->default('baik');
            $table->text('description')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        // 3. Tabel Riwayat Mutasi Lokasi Barang
        Schema::create('location_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('from_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('to_location_id')->constrained('locations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('notes')->nullable();
            $table->timestamp('moved_at')->useCurrent();
            $table->timestamps();
        });

        // 4. Tabel Riwayat Kondisi Barang (History Tracker)
        Schema::create('condition_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->enum('from_condition', ['baik', 'rusak_ringan', 'rusak_berat']);
            $table->enum('to_condition', ['baik', 'rusak_ringan', 'rusak_berat']);
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('recorded_at')->useCurrent();
            $table->timestamps();
        });

        // 5. Tabel Pengajuan Barang (Permohonan kebutuhan oleh Kajur)
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->string('submission_number')->unique(); // Format: REQ-YYYYMMDD-XXXX
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // Pembuat (Kajur)
            $table->string('department'); // Jurusan pengaju
            $table->string('title');
            $table->text('purpose')->nullable();
            $table->enum('status', [
                'draft',             // Masih dirancang Kajur (bisa diedit/dibatalkan)
                'submitted',         // Diajukan oleh Kajur ke Sarpras
                'reviewed_sarpras',  // Diverifikasi/diproses Sarpras, siap diajukan ke Kepsek
                'approved',          // Disetujui Kepala Sekolah
                'rejected',          // Ditolak Kepala Sekolah / Sarpras
                'cancelled'          // Dibatalkan oleh Kajur
            ])->default('draft');
            $table->text('sarpras_notes')->nullable();
            $table->foreignId('sarpras_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('principal_notes')->nullable();
            $table->foreignId('principal_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });

        // 6. Tabel Item Pengajuan (Banyak item dalam satu pengajuan)
        Schema::create('submission_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained('submissions')->cascadeOnDelete();
            $table->string('item_name');
            $table->integer('quantity');
            $table->string('unit')->default('Unit');
            $table->decimal('estimated_price', 15, 2)->default(0);
            $table->text('specification')->nullable();
            $table->timestamps();
        });

        // 7. Tabel Barang Masuk (Log: Transaksi Masuk Menambah Stok)
        Schema::create('incoming_items', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_number')->unique(); // IN-YYYYMMDD-XXXX
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->integer('quantity');
            $table->enum('source', ['pembelian', 'bantuan'])->default('pembelian');
            $table->string('source_origin')->nullable(); // Toko/Pemberi Bantuan
            $table->date('entry_date');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // Pencatat (Sarpras)
            $table->foreignId('submission_id')->nullable()->constrained('submissions')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 8. Tabel Barang Keluar (Log: Transaksi Mengurangi Stok)
        Schema::create('outgoing_items', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_number')->unique(); // OUT-YYYYMMDD-XXXX
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->integer('quantity');
            $table->date('exit_date');
            $table->string('reason'); // Rusak total, Hibah, Pemusnahan, dll.
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // Pencatat (Sarpras)
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 9. Tabel Distribusi (Penyaluran/Alokasi ke Ruang atau Jurusan)
        Schema::create('distributions', function (Blueprint $table) {
            $table->id();
            $table->string('distribution_number')->unique(); // DIST-YYYYMMDD-XXXX
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->integer('quantity');
            $table->foreignId('to_location_id')->constrained('locations')->cascadeOnDelete();
            $table->string('recipient_department')->nullable(); // Jurusan penerima
            $table->string('recipient_name')->nullable(); // Guru/Kajur penerima
            $table->date('distribution_date');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // Staf Sarpras
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 10. Tabel Dokumen (Upload bukti nota, BAST, foto fisik)
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('file_path');
            $table->string('file_name');
            $table->string('file_type')->nullable(); // pdf, jpg, png, docx
            $table->integer('file_size')->nullable();
            $table->string('category')->default('Umum'); // Nota, BAST, Surat Bantuan, Foto
            $table->string('department')->nullable();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // Pengunggah
            $table->nullableMorphs('documentable'); // Relasi polimorfik opsional ke Submission, Item, dll
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
        Schema::dropIfExists('distributions');
        Schema::dropIfExists('outgoing_items');
        Schema::dropIfExists('incoming_items');
        Schema::dropIfExists('submission_items');
        Schema::dropIfExists('submissions');
        Schema::dropIfExists('condition_histories');
        Schema::dropIfExists('location_histories');
        Schema::dropIfExists('items');
        Schema::dropIfExists('locations');
    }
};
