<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class IncomingItem extends Model
{
    protected $fillable = [
        'transaction_number',
        'item_id',
        'quantity',
        'source',
        'source_origin',
        'entry_date',
        'user_id',
        'submission_id',
        'notes',
        'reverses_id',
        'reversal_reason',
        'reversed_at',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'quantity' => 'integer',
            'reversed_at' => 'datetime',
        ];
    }

    public function isReversed(): bool
    {
        return ! is_null($this->reversed_at);
    }

    public function isReversal(): bool
    {
        return ! is_null($this->reverses_id);
    }

    public function reverses(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_id');
    }

    public function reversedBy(): HasOne
    {
        return $this->hasOne(self::class, 'reverses_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }
}
