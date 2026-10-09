<?php

namespace App\Models;

use App\Support\Departments;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Item extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'inventory_number',
        'serial_number',
        'brand',
        'model',
        'name',
        'category',
        'item_type',
        'unit',
        'minimum_stock',
        'source',
        'acquisition_year',
        'acquisition_price',
        'department',
        'location_id',
        'current_condition',
        'current_status',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'acquisition_price' => 'decimal:2',
            'stock' => 'integer',
            'minimum_stock' => 'integer',
        ];
    }

    // === Helper: Tipe Barang ===

    /**
     * Normalisasi jurusan ke kode kanonik (config/departments.php) saat ditulis.
     */
    protected function department(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => Departments::normalizeOrKeep($value),
        );
    }

    /**
     * Label jurusan lengkap untuk tampilan.
     */
    public function departmentLabel(): ?string
    {
        return Departments::label($this->department);
    }

    public function isIndividual(): bool
    {
        return $this->item_type === 'individual';
    }

    public function isConsumable(): bool
    {
        return $this->item_type === 'consumable';
    }

    // === Helper: Status Stok ===

    public function stockStatus(): string
    {
        if ($this->stock <= 0) {
            return 'habis';
        }
        if ($this->minimum_stock > 0 && $this->stock <= $this->minimum_stock) {
            return 'menipis';
        }

        return 'aman';
    }

    public function isStockLow(): bool
    {
        return $this->stockStatus() === 'menipis';
    }

    public function isStockEmpty(): bool
    {
        return $this->stock <= 0;
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function locationHistories(): HasMany
    {
        return $this->hasMany(LocationHistory::class)->latest('moved_at');
    }

    public function conditionHistories(): HasMany
    {
        return $this->hasMany(ConditionHistory::class)->latest('recorded_at');
    }

    public function incomingItems(): HasMany
    {
        return $this->hasMany(IncomingItem::class)->latest('entry_date');
    }

    public function outgoingItems(): HasMany
    {
        return $this->hasMany(OutgoingItem::class)->latest('exit_date');
    }

    public function distributions(): HasMany
    {
        return $this->hasMany(Distribution::class)->latest('distribution_date');
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class)->latest();
    }

    public function activeLoans(): HasMany
    {
        return $this->hasMany(Loan::class)->whereIn('status', ['dipinjam', 'disetujui', 'menunggu']);
    }

    public function assetUnits(): HasMany
    {
        return $this->hasMany(AssetUnit::class)->latest('id');
    }

    /**
     * Jumlah aset efektif untuk agregasi laporan/dashboard:
     * consumable = 1 entri (stok), individual = jumlah unit fisik.
     */
    public function assetCount(): int
    {
        if ($this->isConsumable()) {
            return 1;
        }

        return $this->assetUnits()->count();
    }

    /**
     * Hitung status operasional agregat untuk item master berbasis unit fisik.
     */
    public function recalculateAggregateStatus(): string
    {
        if (! $this->isIndividual() || ! $this->assetUnits()->exists()) {
            return $this->current_status;
        }

        $units = $this->assetUnits()->get(['current_status']);
        $total = $units->count();
        if ($total === 0) {
            return $this->current_status;
        }

        $statuses = $units->pluck('current_status')->all();

        // 1. Jika ada setidaknya 1 unit yang aktif (tersedia), master item berstatus aktif.
        if (in_array('aktif', $statuses, true)) {
            return 'aktif';
        }

        // 2. Jika tidak ada yang aktif, dan ada yang sedang dipinjam.
        if (in_array('dipinjam', $statuses, true)) {
            return 'dipinjam';
        }

        // 3. Jika seluruh unit sedang dalam perbaikan.
        if (count(array_filter($statuses, fn ($s) => $s === 'dalam_perbaikan')) === $total) {
            return 'dalam_perbaikan';
        }

        // 4. Jika seluruh unit tidak aktif.
        if (count(array_filter($statuses, fn ($s) => $s === 'tidak_aktif')) === $total) {
            return 'tidak_aktif';
        }

        // 5. Jika seluruh unit dihapuskan/disposed.
        if (count(array_filter($statuses, fn ($s) => $s === 'disposed')) === $total) {
            return 'disposed';
        }

        return $statuses[0] ?? 'aktif';
    }

    /**
     * Hitung kondisi fisik agregat item (kondisi terburuk antar unit).
     */
    public function recalculateAggregateCondition(): string
    {
        if (! $this->isIndividual() || ! $this->assetUnits()->exists()) {
            return $this->current_condition;
        }

        $conditions = $this->assetUnits()->pluck('current_condition')->all();

        if (in_array('rusak_berat', $conditions, true)) {
            return 'rusak_berat';
        }

        if (in_array('rusak_ringan', $conditions, true)) {
            return 'rusak_ringan';
        }

        return 'baik';
    }

    /**
     * Ringkasan ketersediaan unit untuk tampilan deskriptif UI.
     */
    public function aggregateStatusSummary(): string
    {
        if (! $this->isIndividual() || ! $this->assetUnits()->exists()) {
            return ucfirst(str_replace('_', ' ', $this->current_status));
        }

        $total = $this->assetUnits()->count();
        $active = $this->assetUnits()->where('current_status', 'aktif')->count();
        $borrowed = $this->assetUnits()->where('current_status', 'dipinjam')->count();

        if ($borrowed === 0 && $active === $total) {
            return 'Semua Unit Aktif';
        }

        if ($borrowed === $total) {
            return 'Semua Unit Dipinjam';
        }

        return "{$active}/{$total} Unit Tersedia ({$borrowed} Dipinjam)";
    }
}
