<?php

use App\Http\Controllers\Auth\AuthController;
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
Route::middleware(['auth', 'role'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Home / Landing status setelah login
    Route::get('/home', [\App\Http\Controllers\HomeController::class, 'index'])->name('home');

    // Modul Laporan (Dapat diakses seluruh role dengan filter otomatis sesuai wewenang)
    Route::get('/reports', [\App\Http\Controllers\ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/pdf', [\App\Http\Controllers\ReportController::class, 'exportPdf'])->name('reports.pdf');
    Route::get('/reports/excel', [\App\Http\Controllers\ReportController::class, 'exportExcel'])->name('reports.excel');

    // Modul Dokumen (Dapat diakses seluruh role sesuai hak akses)
    Route::get('/documents', [\App\Http\Controllers\DocumentController::class, 'index'])->name('documents.index');
    Route::post('/documents', [\App\Http\Controllers\DocumentController::class, 'store'])->name('documents.store');
    Route::get('/documents/{document}/download', [\App\Http\Controllers\DocumentController::class, 'download'])->name('documents.download');
    Route::delete('/documents/{document}', [\App\Http\Controllers\DocumentController::class, 'destroy'])->name('documents.destroy');

    // ==========================================
    // AREA KAJUR (Kepala Kejuruan)
    // ==========================================
    Route::middleware('role:kajur')->prefix('kajur')->name('kajur.')->group(function () {
        // Pengajuan Barang
        Route::get('/submissions', [\App\Http\Controllers\Kajur\SubmissionController::class, 'index'])->name('submissions.index');
        Route::get('/submissions/create', [\App\Http\Controllers\Kajur\SubmissionController::class, 'create'])->name('submissions.create');
        Route::post('/submissions', [\App\Http\Controllers\Kajur\SubmissionController::class, 'store'])->name('submissions.store');
        Route::get('/submissions/{submission}', [\App\Http\Controllers\Kajur\SubmissionController::class, 'show'])->name('submissions.show');
        Route::get('/submissions/{submission}/edit', [\App\Http\Controllers\Kajur\SubmissionController::class, 'edit'])->name('submissions.edit');
        Route::put('/submissions/{submission}', [\App\Http\Controllers\Kajur\SubmissionController::class, 'update'])->name('submissions.update');
        Route::post('/submissions/{submission}/cancel', [\App\Http\Controllers\Kajur\SubmissionController::class, 'cancel'])->name('submissions.cancel');
        Route::post('/submissions/{submission}/submit', [\App\Http\Controllers\Kajur\SubmissionController::class, 'submitDraft'])->name('submissions.submitDraft');

        // Inventaris Jurusan
        Route::get('/inventory', [\App\Http\Controllers\Kajur\InventoryController::class, 'index'])->name('inventory.index');
        Route::get('/inventory/{item}', [\App\Http\Controllers\Kajur\InventoryController::class, 'show'])->name('inventory.show');

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
        Route::get('/inventory', [\App\Http\Controllers\Sarpras\InventoryController::class, 'index'])->name('inventory.index');
        Route::get('/inventory/create', [\App\Http\Controllers\Sarpras\InventoryController::class, 'create'])->name('inventory.create');
        Route::post('/inventory', [\App\Http\Controllers\Sarpras\InventoryController::class, 'store'])->name('inventory.store');
        Route::get('/inventory/{item}', [\App\Http\Controllers\Sarpras\InventoryController::class, 'show'])->name('inventory.show');
        Route::post('/inventory/{item}/location', [\App\Http\Controllers\Sarpras\InventoryController::class, 'updateLocation'])->name('inventory.updateLocation');
        Route::post('/inventory/{item}/condition', [\App\Http\Controllers\Sarpras\InventoryController::class, 'updateCondition'])->name('inventory.updateCondition');

        // Verifikasi & Proses Pengajuan Kajur
        Route::get('/submissions', [\App\Http\Controllers\Sarpras\SubmissionController::class, 'index'])->name('submissions.index');
        Route::get('/submissions/{submission}', [\App\Http\Controllers\Sarpras\SubmissionController::class, 'show'])->name('submissions.show');
        Route::post('/submissions/{submission}/process', [\App\Http\Controllers\Sarpras\SubmissionController::class, 'process'])->name('submissions.process');

        // Logistik: Barang Masuk
        Route::get('/logistics/incoming', [\App\Http\Controllers\Sarpras\LogisticsController::class, 'incomingIndex'])->name('logistics.incoming');
        Route::post('/logistics/incoming', [\App\Http\Controllers\Sarpras\LogisticsController::class, 'storeIncoming'])->name('logistics.incoming.store');

        // Logistik: Barang Keluar
        Route::get('/logistics/outgoing', [\App\Http\Controllers\Sarpras\LogisticsController::class, 'outgoingIndex'])->name('logistics.outgoing');
        Route::post('/logistics/outgoing', [\App\Http\Controllers\Sarpras\LogisticsController::class, 'storeOutgoing'])->name('logistics.outgoing.store');

        // Logistik: Penyaluran / Distribusi
        Route::get('/logistics/distributions', [\App\Http\Controllers\Sarpras\LogisticsController::class, 'distributionIndex'])->name('logistics.distributions');
        Route::post('/logistics/distributions', [\App\Http\Controllers\Sarpras\LogisticsController::class, 'storeDistribution'])->name('logistics.distributions.store');

        // Master Data Lokasi
        Route::get('/locations', [\App\Http\Controllers\Sarpras\LocationController::class, 'index'])->name('locations.index');
        Route::post('/locations', [\App\Http\Controllers\Sarpras\LocationController::class, 'store'])->name('locations.store');
        Route::delete('/locations/{location}', [\App\Http\Controllers\Sarpras\LocationController::class, 'destroy'])->name('locations.destroy');

        // Manajemen Pengguna
        Route::get('/users', [\App\Http\Controllers\Sarpras\UserController::class, 'index'])->name('users.index');
        Route::post('/users', [\App\Http\Controllers\Sarpras\UserController::class, 'store'])->name('users.store');
        Route::post('/users/{user}/toggle', [\App\Http\Controllers\Sarpras\UserController::class, 'toggleStatus'])->name('users.toggle');

        // Modul Peminjaman & Pengembalian
        Route::get('/loans', [\App\Http\Controllers\Sarpras\LoanController::class, 'index'])->name('loans.index');
        Route::get('/loans/create', [\App\Http\Controllers\Sarpras\LoanController::class, 'create'])->name('loans.create');
        Route::post('/loans', [\App\Http\Controllers\Sarpras\LoanController::class, 'store'])->name('loans.store');
        Route::get('/loans/{loan}', [\App\Http\Controllers\Sarpras\LoanController::class, 'show'])->name('loans.show');
        Route::post('/loans/{loan}/approve', [\App\Http\Controllers\Sarpras\LoanController::class, 'approve'])->name('loans.approve');
        Route::post('/loans/{loan}/return', [\App\Http\Controllers\Sarpras\LoanController::class, 'returnLoan'])->name('loans.return');
        Route::post('/loans/{loan}/reject', [\App\Http\Controllers\Sarpras\LoanController::class, 'reject'])->name('loans.reject');

        // Audit Trail / Activity Log (Hanya baca, tidak bisa dihapus via UI)
        Route::get('/activity-logs', [\App\Http\Controllers\Sarpras\ActivityLogController::class, 'index'])->name('activity_logs.index');

        // Alias area untuk backward compatibility
        Route::get('/area', function () {
            return redirect()->route('sarpras.inventory.index');
        })->name('area');
    });

    // ==========================================
    // AREA KEPALA SEKOLAH (Approval & Review)
    // ==========================================
    Route::middleware('role:kepala_sekolah')->prefix('kepala-sekolah')->name('kepala_sekolah.')->group(function () {
        Route::get('/approval', [\App\Http\Controllers\Principal\ApprovalController::class, 'index'])->name('approval.index');
        Route::get('/approval/{submission}', [\App\Http\Controllers\Principal\ApprovalController::class, 'show'])->name('approval.show');
        Route::post('/approval/{submission}/decide', [\App\Http\Controllers\Principal\ApprovalController::class, 'decide'])->name('approval.decide');

        // Alias area untuk backward compatibility
        Route::get('/area', function () {
            return redirect()->route('kepala_sekolah.approval.index');
        })->name('area');
    });
});
