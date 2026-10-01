<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConditionHistory extends Model
{
    protected $fillable = [
        'item_id',
        'from_condition',
        'to_condition',
        'user_id',
        'notes',
        'recorded_at',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new \RuntimeException('Riwayat kondisi bersifat append-only dan tidak boleh diubah.');
        });

        static::deleting(function () {
            throw new \RuntimeException('Riwayat kondisi bersifat append-only dan tidak boleh dihapus.');
        });
    }

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
