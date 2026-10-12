<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Riwayat perubahan status unit aset. Bersifat append-only: aplikasi tidak
 * menyediakan halaman edit/hapus untuk data ini agar audit trail terjaga.
 */
class AssetUnitStatusHistory extends Model
{
    protected $fillable = [
        'asset_unit_id',
        'item_id',
        'from_status',
        'to_status',
        'reason',
        'user_id',
        'recorded_at',
    ];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'from_status' => 'string',
            'to_status' => 'string',
            'recorded_at' => 'datetime',
        ];
    }

    public function assetUnit(): BelongsTo
    {
        return $this->belongsTo(AssetUnit::class)->withTrashed();
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
