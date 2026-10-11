<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubmissionItem extends Model
{
    protected $fillable = [
        'submission_id',
        'item_id',
        'item_name',
        'quantity',
        'received_quantity',
        'unit',
        'estimated_price',
        'specification',
    ];

    protected function casts(): array
    {
        return [
            'estimated_price' => 'decimal:2',
            'quantity' => 'integer',
            'received_quantity' => 'integer',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class)->withTrashed();
    }
}
