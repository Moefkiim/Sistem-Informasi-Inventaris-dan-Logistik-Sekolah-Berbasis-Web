<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubmissionItem extends Model
{
    protected $fillable = [
        'submission_id',
        'item_name',
        'quantity',
        'unit',
        'estimated_price',
        'specification',
    ];

    protected function casts(): array
    {
        return [
            'estimated_price' => 'decimal:2',
            'quantity' => 'integer',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }
}
