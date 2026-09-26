<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
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

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }
}
