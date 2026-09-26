<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutgoingItem extends Model
{
    protected $fillable = [
        'transaction_number',
        'item_id',
        'quantity',
        'exit_date',
        'reason',
        'user_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'exit_date' => 'date',
            'quantity' => 'integer',
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
