<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Loan extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Sumber tunggal untuk label dan warna badge status peminjaman.
     * Dipakai oleh view (x-loan-status-badge, ringkasan index) agar
     * istilah bahasa Indonesia konsisten di seluruh modul.
     */
    public const STATUS_META = [
        'menunggu' => ['label' => 'Menunggu',     'color' => 'warning'],
        'disetujui' => ['label' => 'Disetujui',    'color' => 'primary'],
        'dipinjam' => ['label' => 'Dipinjam',     'color' => 'info'],
        'dikembalikan' => ['label' => 'Dikembalikan', 'color' => 'success'],
        'terlambat' => ['label' => 'Terlambat',    'color' => 'danger'],
        'ditolak' => ['label' => 'Ditolak',      'color' => 'secondary'],
    ];

    /**
     * Status yang masih berjalan dan belum selesai (belum kembali / belum diputuskan).
     */
    public const STATUS_ACTIVE = ['menunggu', 'disetujui', 'dipinjam'];

    protected $fillable = [
        'loan_number',
        'borrower_user_id',
        'borrower_name',
        'borrower_department',
        'item_id',
        'quantity',
        'loan_date',
        'due_date',
        'return_date',
        'purpose',
        'status',
        'condition_on_loan',
        'condition_on_return',
        'approved_by',
        'approved_at',
        'returned_to',
        'returned_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'loan_date' => 'date',
            'due_date' => 'date',
            'return_date' => 'date',
            'approved_at' => 'datetime',
            'returned_at' => 'datetime',
        ];
    }

    // === Relationships ===

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class)->withTrashed();
    }

    public function borrower(): BelongsTo
    {
        return $this->belongsTo(User::class, 'borrower_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function returnedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_to');
    }

    // === Helper Methods ===

    public function isPending(): bool
    {
        return $this->status === 'menunggu';
    }

    public function isApproved(): bool
    {
        return $this->status === 'disetujui';
    }

    public function isOnLoan(): bool
    {
        return $this->status === 'dipinjam';
    }

    public function isReturned(): bool
    {
        return $this->status === 'dikembalikan';
    }

    public function isOverdue(): bool
    {
        return $this->status === 'terlambat'
            || ($this->isOnLoan() && $this->due_date && $this->due_date->isPast());
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::STATUS_ACTIVE, true);
    }

    /**
     * Status yang masih dapat disetujui / diserahkan barang.
     */
    public function isApprovable(): bool
    {
        return in_array($this->status, ['menunggu', 'disetujui'], true);
    }

    /**
     * Status yang masih dapat underwent pengembalian barang.
     */
    public function isReturnable(): bool
    {
        return in_array($this->status, ['dipinjam', 'terlambat'], true);
    }

    /**
     * Jumlah hari keterlambatan pengembalian (0 bila belum terlambat).
     */
    public function overdueDays(): int
    {
        if (! $this->isOverdue() || ! $this->due_date) {
            return 0;
        }

        return (int) $this->due_date->startOfDay()->diffInDays(now()->startOfDay(), false);
    }
}
