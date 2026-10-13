<?php

namespace App\Models;

use App\Support\AssetCondition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
        'aktif' => 'Aktif',
        'dipinjam' => 'Dipinjam',
        'dalam_perbaikan' => 'Dalam Perbaikan',
        'tidak_aktif' => 'Tidak Aktif',
        'disposed' => 'Dihapuskan',
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
        'aktif' => 'success',
        'dipinjam' => 'info',
        'dalam_perbaikan' => 'warning',
        'tidak_aktif' => 'secondary',
        'disposed' => 'danger',
    ];

    /**
     * Matriks transisi status yang diizinkan (dari status => daftar tujuan).
     * Status 'dipinjam' tidak dapat diubah langsung dan 'disposed' bersifat
     * final — bukti audit trail tidak boleh hilang.
     *
     * @var array<string, array<int,string>>
     */
    public const STATUS_TRANSITIONS = [
        'aktif' => ['dalam_perbaikan', 'tidak_aktif', 'disposed'],
        'dalam_perbaikan' => ['aktif', 'tidak_aktif', 'disposed'],
        'tidak_aktif' => ['aktif', 'disposed'],
        'dipinjam' => [],
        'disposed' => [],
    ];

    /**
     * Status yang diubah otomatis ketika kondisi unit menjadi tidak layak
     * pakai / hilang. Diterapkan hanya saat unit tidak sedang dipinjam.
     *
     * @var array<string,string>
     */
    public const AUTO_STATUS_FOR_CONDITION = [
        'tidak_layak_pakai' => 'tidak_aktif',
        'hilang' => 'tidak_aktif',
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
     * Syarat: status harus 'aktif' DAN tidak ada permohonan pinjaman aktif (menunggu, disetujui, dipinjam).
     */
    public function isAvailableForLoan(): bool
    {
        if (! in_array($this->current_status, self::LOANABLE_STATUSES, true)) {
            return false;
        }

        return ! $this->loans()
            ->whereIn('status', Loan::STATUS_ACTIVE)
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
     * Scope: unit yang dapat dipinjam (status aktif, tanpa loan aktif/pending).
     */
    public function scopeLoanable(Builder $query): Builder
    {
        return $query
            ->whereIn('current_status', self::LOANABLE_STATUSES)
            ->whereDoesntHave('loans', fn ($q) => $q->whereIn('status', Loan::STATUS_ACTIVE));
    }

    public function locationHistories(): HasMany
    {
        return $this->hasMany(LocationHistory::class)->latest('moved_at');
    }

    public function conditionHistories(): HasMany
    {
        return $this->hasMany(ConditionHistory::class)->latest('recorded_at');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(AssetUnitStatusHistory::class)->latest('recorded_at')->latest('id');
    }

    // === Status Lifecycle ===

    /**
     * Apakah transisi status dari $from ke $to diizinkan?
     */
    public static function canTransit(string $from, string $to): bool
    {
        return in_array($to, self::STATUS_TRANSITIONS[$from] ?? [], true);
    }

    /**
     * Apakah unit sedang dipinjam (status 'dipinjam')?
     */
    public function isBorrowed(): bool
    {
        return $this->current_status === 'dipinjam';
    }

    /**
     * Terapkan perubahan status unit dengan audit trail.
     *
     * Menolak transisi yang tidak diizinkan, unit yang sedang dipinjam, dan
     * status yang sama. Saat status menjadi 'disposed', stok master dikurangi 1
     * (tidak pernah di bawah 0) dan baris unit dipertahankan agar histori utuh.
     *
     *
     * @throws ValidationException
     */
    public function changeStatus(string $newStatus, ?string $reason, User $actor): AssetUnitStatusHistory
    {
        return DB::transaction(function () use ($newStatus, $reason, $actor) {
            $locked = self::query()->lockForUpdate()->find($this->getKey());

            if (! $locked) {
                throw ValidationException::withMessages([
                    'to_status' => 'Unit aset tidak ditemukan.',
                ]);
            }

            $from = (string) $locked->current_status;

            if ($from === 'disposed') {
                throw ValidationException::withMessages([
                    'to_status' => 'Unit yang sudah dihapuskan bersifat final dan tidak dapat diubah lagi.',
                ]);
            }

            if ($from === 'dipinjam') {
                throw ValidationException::withMessages([
                    'to_status' => 'Unit sedang dipinjam, selesaikan pengembalian terlebih dahulu.',
                ]);
            }

            if ($newStatus === $from) {
                throw ValidationException::withMessages([
                    'to_status' => 'Status unit sudah '.self::STATUS_LABELS[$from].'.',
                ]);
            }

            if (! self::canTransit($from, $newStatus)) {
                throw ValidationException::withMessages([
                    'to_status' => 'Transisi status tidak diizinkan: '.self::STATUS_LABELS[$from].' → '.self::STATUS_LABELS[$newStatus].'.',
                ]);
            }

            if ($newStatus === 'disposed' && trim((string) $reason) === '') {
                throw ValidationException::withMessages([
                    'reason' => 'Alasan penghapusan wajib diisi.',
                ]);
            }

            $locked->current_status = $newStatus;
            $locked->save();

            if ($newStatus === 'disposed') {
                $this->decrementStockForDisposal($locked);
            }

            $locked->syncItemAggregate();

            $history = $locked->writeStatusHistory($from, $newStatus, $reason, $actor->id);

            $this->setRawAttributes($locked->getAttributes(), true);

            return $history;
        });
    }

    /**
     * Kurangi stok master saat satu unit dihapuskan. Baris item dikunci dan
     * dibaca ulang agar dua penghapusan unit yang sama tidak mengurangi stok
     * dua kali. Stok yang tidak konsisten (0) menolak transaksi, bukan
     * disembunyikan dengan `max(0, …)`.
     */
    private function decrementStockForDisposal(AssetUnit $unit): void
    {
        $item = Item::withTrashed()->whereKey($unit->item_id)->lockForUpdate()->first();

        if (! $item) {
            return;
        }

        if ((int) $item->stock <= 0) {
            throw ValidationException::withMessages([
                'to_status' => "Stok {$item->name} (0) tidak konsisten dengan jumlah unit fisik; penghapusan unit dibatalkan.",
            ]);
        }

        $item->stock = (int) $item->stock - 1;
        $item->save();
    }

    /**
     * Ubah status unit untuk alur peminjaman (mis. aktif → dipinjam saat
     * disetujui, dipinjam → aktif/dalam_perbaikan/tidak_aktif saat kembali).
     * Transisi dari/ke `dipinjam` dikendalikan alur pinjam, bukan matriks
     * transisi manual, tetapi setiap perubahan tetap dicatat sebagai riwayat.
     */
    public function applyLoanStatus(string $newStatus, ?string $reason, User $actor): void
    {
        DB::transaction(function () use ($newStatus, $reason, $actor) {
            $locked = self::query()->lockForUpdate()->find($this->getKey());

            if (! $locked) {
                throw ValidationException::withMessages([
                    'to_status' => 'Unit aset tidak ditemukan.',
                ]);
            }

            $from = (string) $locked->current_status;

            if ($from === $newStatus) {
                $this->setRawAttributes($locked->getAttributes(), true);

                return;
            }

            $locked->current_status = $newStatus;
            $locked->save();

            $locked->syncItemAggregate();

            $locked->writeStatusHistory($from, $newStatus, $reason, $actor->id);

            $this->setRawAttributes($locked->getAttributes(), true);
        });
    }

    /**
     * Sinkronkan status & kondisi agregat item induk setelah unit berubah.
     */
    private function syncItemAggregate(): void
    {
        $item = $this->item;

        if (! $item) {
            return;
        }

        $item->current_status = $item->recalculateAggregateStatus();
        $item->current_condition = $item->recalculateAggregateCondition();
        $item->save();
    }

    private function writeStatusHistory(string $from, string $to, ?string $reason, int $userId): AssetUnitStatusHistory
    {
        return AssetUnitStatusHistory::create([
            'asset_unit_id' => $this->id,
            'item_id' => $this->item_id,
            'from_status' => $from,
            'to_status' => $to,
            'reason' => $reason,
            'user_id' => $userId,
            'recorded_at' => now(),
        ]);
    }

    /**
     * Terapkan perubahan kondisi lalu otomatis ubah status bila kondisi baru
     * tidak layak pakai / hilang. Riwayat kondisi dan status dicatat sekaligus.
     *
     *
     * @throws ValidationException
     */
    public function changeCondition(string $newCondition, ?string $notes, User $actor): ConditionHistory
    {
        return DB::transaction(function () use ($newCondition, $notes, $actor) {
            $locked = self::query()->lockForUpdate()->find($this->getKey());

            if (! $locked) {
                throw ValidationException::withMessages([
                    'to_condition' => 'Unit aset tidak ditemukan.',
                ]);
            }

            $from = (string) $locked->current_condition;

            $history = ConditionHistory::create([
                'item_id' => $locked->item_id,
                'asset_unit_id' => $locked->id,
                'from_condition' => $from,
                'to_condition' => $newCondition,
                'user_id' => $actor->id,
                'notes' => $notes,
                'recorded_at' => now(),
            ]);

            $locked->current_condition = $newCondition;
            $locked->save();

            $autoStatus = self::AUTO_STATUS_FOR_CONDITION[$newCondition] ?? null;
            if ($autoStatus && ! $locked->isBorrowed() && $autoStatus !== $locked->current_status) {
                $locked->changeStatus($autoStatus, 'Kondisi: '.(AssetCondition::label($newCondition) ?? $newCondition), $actor);
            }

            $this->setRawAttributes($locked->getAttributes(), true);

            return $history;
        });
    }
}
