<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocationHistory extends Model
{
    protected $fillable = [
        'item_id',
        'from_location_id',
        'to_location_id',
        'user_id',
        'notes',
        'moved_at',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new \RuntimeException('Riwayat lokasi bersifat append-only dan tidak boleh diubah.');
        });

        static::deleting(function () {
            throw new \RuntimeException('Riwayat lokasi bersifat append-only dan tidak boleh dihapus.');
        });
    }

    protected function casts(): array
    {
        return [
            'moved_at' => 'datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
