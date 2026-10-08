<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class AssetUnit extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'item_id',
        'unit_inventory_number',
        'serial_number',
        'current_condition',
        'current_status',
        'location_id',
        'is_legacy_migrated',
        'notes',
    ];

    /**
     * Label baku status unit aset (urutan penampilan sama seperti items).
     */
    public const STATUS_LABELS = [
        'aktif'          => 'Aktif',
        'dipinjam'       => 'Dipinjam',
        'dalam_perbaikan'=> 'Dalam Perbaikan',
        'tidak_aktif'    => 'Tidak Aktif',
        'disposed'       => 'Dihapuskan',
    ];

    /**
     * Status unit yang mengizinkan peminjaman baru.
     * Unit dengan status lain TIDAK boleh dipinjam.
     */
    public const LOANABLE_STATUSES = ['aktif'];

    /**
     * Status warna badge untuk tampilan UI.
     */
    public const STATUS_BADGE_COLORS = [
        'aktif'          => 'success',
        'dipinjam'       => 'info',
        'dalam_perbaikan'=> 'warning',
        'tidak_aktif'    => 'secondary',
        'disposed'       => 'danger',
    ];

    protected function casts(): array
    {
        return [
            'is_legacy_migrated' => 'boolean',
            'current_condition' => 'string',
            'current_status' => 'string',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class)->withTrashed();
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class)->latest();
    }

    public function activeLoans(): HasMany
    {
        return $this->hasMany(Loan::class)->whereIn('status', ['dipinjam', 'disetujui', 'menunggu']);
    }

    // === Helper Methods ===

    /**
     * Apakah unit ini tersedia untuk dipinjam?
     * Syarat: status harus 'aktif' DAN tidak ada loan aktif (dipinjam/disetujui).
     */
    public function isAvailableForLoan(): bool
    {
        if (! in_array($this->current_status, self::LOANABLE_STATUSES, true)) {
            return false;
        }

        return ! $this->loans()
            ->whereIn('status', ['dipinjam', 'disetujui'])
            ->exists();
    }

    /**
     * Apakah unit sedang aktif (status 'aktif')?
     */
    public function isActive(): bool
    {
        return $this->current_status === 'aktif';
    }

    /**
     * Scope: unit yang dapat dipinjam (status aktif, tanpa loan aktif).
     */
    public function scopeLoanable(Builder $query): Builder
    {
        return $query
            ->whereIn('current_status', self::LOANABLE_STATUSES)
            ->whereDoesntHave('loans', fn ($q) => $q->whereIn('status', ['dipinjam', 'disetujui']));
    }

    public function locationHistories(): HasMany
    {
        return $this->hasMany(LocationHistory::class)->latest('moved_at');
    }

    public function conditionHistories(): HasMany
    {
        return $this->hasMany(ConditionHistory::class)->latest('recorded_at');
    }
}
