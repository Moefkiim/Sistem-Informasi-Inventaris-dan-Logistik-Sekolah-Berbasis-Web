<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Kajur\InventoryController;
use App\Http\Controllers\Kajur\SubmissionController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Principal\ApprovalController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\Sarpras\ActivityLogController;
use App\Http\Controllers\Sarpras\LoanController;
use App\Http\Controllers\Sarpras\LocationController;
use App\Http\Controllers\Sarpras\LogisticsController;
use App\Http\Controllers\Sarpras\UserController;
use Illuminate\Support\Facades\Route;

// Redirect root ke halaman login jika guest, atau ke home jika sudah login
Route::get('/', function () {
    return auth()->check() ? redirect()->route('home') : redirect()->route('login');
});

// Autentikasi Guest
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
});

// Autentikasi User (Wajib Login & Status Aktif)
Route::middleware(['auth', 'role', 'password.changed'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Halaman Akun Saya & Ubah Kata Sandi (Semua Role)
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->middleware('throttle:6,1')->name('profile.password');

    // Home / Landing status setelah login
    Route::get('/home', [HomeController::class, 'index'])->name('home');

    // Modul Notifikasi Dalam Aplikasi (Semua Role)
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/{notification}', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read_all');

    // Modul Laporan (Dapat diakses seluruh role dengan filter otomatis sesuai wewenang)
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/pdf', [ReportController::class, 'exportPdf'])->name('reports.pdf');
    Route::get('/reports/excel', [ReportController::class, 'exportExcel'])->name('reports.excel');

    // Modul Dokumen (Dapat diakses seluruh role sesuai hak akses)
    Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::get('/documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');

    // ==========================================
    // AREA KAJUR (Kepala Kejuruan)
    // ==========================================
    Route::middleware('role:kajur')->prefix('kajur')->name('kajur.')->group(function () {
        // Pengajuan Barang
        Route::get('/submissions', [SubmissionController::class, 'index'])->name('submissions.index');
        Route::get('/submissions/create', [SubmissionController::class, 'create'])->name('submissions.create');
        Route::post('/submissions', [SubmissionController::class, 'store'])->name('submissions.store');
        Route::get('/submissions/{submission}', [SubmissionController::class, 'show'])->name('submissions.show');
        Route::get('/submissions/{submission}/edit', [SubmissionController::class, 'edit'])->name('submissions.edit');
        Route::put('/submissions/{submission}', [SubmissionController::class, 'update'])->name('submissions.update');
        Route::post('/submissions/{submission}/cancel', [SubmissionController::class, 'cancel'])->name('submissions.cancel');
        Route::post('/submissions/{submission}/submit', [SubmissionController::class, 'submitDraft'])->name('submissions.submitDraft');
        Route::post('/submissions/{submission}/revise', [SubmissionController::class, 'revise'])->name('submissions.revise');

        // Inventaris Jurusan
        Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
        Route::get('/inventory/{item}', [InventoryController::class, 'show'])->name('inventory.show');

        // Alias area untuk backward compatibility
        Route::get('/area', function () {
            return redirect()->route('kajur.submissions.index');
        })->name('area');
    });

    // ==========================================
    // AREA SARPRAS (Operator & Logistik)
    // ==========================================
    Route::middleware('role:sarpras')->prefix('sarpras')->name('sarpras.')->group(function () {
        // Master Inventaris & Mutasi
        Route::get('/inventory', [App\Http\Controllers\Sarpras\InventoryController::class, 'index'])->name('inventory.index');
        Route::get('/inventory/create', [App\Http\Controllers\Sarpras\InventoryController::class, 'create'])->name('inventory.create');
        Route::post('/inventory', [App\Http\Controllers\Sarpras\InventoryController::class, 'store'])->name('inventory.store');

        // Impor Inventaris Awal (Excel/CSV) — didaftarkan sebelum rute {item}
        Route::get('/inventory/import', [App\Http\Controllers\Sarpras\InventoryImportController::class, 'create'])->name('inventory.import.create');
        Route::get('/inventory/import/template', [App\Http\Controllers\Sarpras\InventoryImportController::class, 'template'])->name('inventory.import.template');
        Route::post('/inventory/import/preview', [App\Http\Controllers\Sarpras\InventoryImportController::class, 'preview'])->name('inventory.import.preview');
        Route::post('/inventory/import/confirm', [App\Http\Controllers\Sarpras\InventoryImportController::class, 'confirm'])->name('inventory.import.confirm');
        Route::get('/inventory/import/errors/{token}', [App\Http\Controllers\Sarpras\InventoryImportController::class, 'errors'])->name('inventory.import.errors');

        Route::get('/inventory/{item}', [App\Http\Controllers\Sarpras\InventoryController::class, 'show'])->name('inventory.show');
        Route::post('/inventory/{item}/location', [App\Http\Controllers\Sarpras\InventoryController::class, 'updateLocation'])->name('inventory.updateLocation');
        Route::post('/inventory/{item}/condition', [App\Http\Controllers\Sarpras\InventoryController::class, 'updateCondition'])->name('inventory.updateCondition');
        Route::post('/inventory/{item}/status', [App\Http\Controllers\Sarpras\InventoryController::class, 'updateStatus'])->name('inventory.updateStatus');

        // Verifikasi & Proses Pengajuan Kajur
        Route::get('/submissions', [App\Http\Controllers\Sarpras\SubmissionController::class, 'index'])->name('submissions.index');
        Route::get('/submissions/{submission}', [App\Http\Controllers\Sarpras\SubmissionController::class, 'show'])->name('submissions.show');
        Route::post('/submissions/{submission}/process', [App\Http\Controllers\Sarpras\SubmissionController::class, 'process'])->name('submissions.process');

        // Realisasi Pengajuan yang Telah Disetujui
        Route::get('/realizations', [App\Http\Controllers\Sarpras\SubmissionController::class, 'ready'])->name('realizations.index');
        Route::get('/submissions/{submission}/realize', [App\Http\Controllers\Sarpras\SubmissionController::class, 'realizeForm'])->name('submissions.realize_form');
        Route::post('/submissions/{submission}/realize', [App\Http\Controllers\Sarpras\SubmissionController::class, 'realize'])->name('submissions.realize');

        // Logistik: Barang Masuk
        Route::get('/logistics/incoming', [LogisticsController::class, 'incomingIndex'])->name('logistics.incoming');
        Route::post('/logistics/incoming', [LogisticsController::class, 'storeIncoming'])->name('logistics.incoming.store');

        // Logistik: Barang Keluar
        Route::get('/logistics/outgoing', [LogisticsController::class, 'outgoingIndex'])->name('logistics.outgoing');
        Route::post('/logistics/outgoing', [LogisticsController::class, 'storeOutgoing'])->name('logistics.outgoing.store');

        // Logistik: Penyaluran / Distribusi
        Route::get('/logistics/distributions', [LogisticsController::class, 'distributionIndex'])->name('logistics.distributions');
        Route::post('/logistics/distributions', [LogisticsController::class, 'storeDistribution'])->name('logistics.distributions.store');

        // Master Data Lokasi
        Route::get('/locations', [LocationController::class, 'index'])->name('locations.index');
        Route::post('/locations', [LocationController::class, 'store'])->name('locations.store');
        Route::delete('/locations/{location}', [LocationController::class, 'destroy'])->name('locations.destroy');

        // Manajemen Pengguna
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset_password');
        Route::post('/users/{user}/toggle', [UserController::class, 'toggleStatus'])->name('users.toggle');

        // Modul Peminjaman & Pengembalian
        Route::get('/loans', [LoanController::class, 'index'])->name('loans.index');
        Route::get('/loans/create', [LoanController::class, 'create'])->name('loans.create');
        Route::post('/loans', [LoanController::class, 'store'])->name('loans.store');
        Route::get('/loans/{loan}', [LoanController::class, 'show'])->name('loans.show');
        Route::post('/loans/{loan}/approve', [LoanController::class, 'approve'])->name('loans.approve');
        Route::post('/loans/{loan}/return', [LoanController::class, 'returnLoan'])->name('loans.return');
        Route::post('/loans/{loan}/reject', [LoanController::class, 'reject'])->name('loans.reject');

        // Audit Trail / Activity Log (Hanya baca, tidak bisa dihapus via UI)
        Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity_logs.index');

        // Alias area untuk backward compatibility
        Route::get('/area', function () {
            return redirect()->route('sarpras.inventory.index');
        })->name('area');
    });

    // ==========================================
    // AREA KEPALA SEKOLAH (Approval & Review)
    // ==========================================
    Route::middleware('role:kepala_sekolah')->prefix('kepala-sekolah')->name('kepala_sekolah.')->group(function () {
        Route::get('/approval', [ApprovalController::class, 'index'])->name('approval.index');
        Route::get('/approval/{submission}', [ApprovalController::class, 'show'])->name('approval.show');
        Route::post('/approval/{submission}/decide', [ApprovalController::class, 'decide'])->name('approval.decide');

        // Alias area untuk backward compatibility
        Route::get('/area', function () {
            return redirect()->route('kepala_sekolah.approval.index');
        })->name('area');
    });
});
