<?php

namespace App\Models;

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
}
