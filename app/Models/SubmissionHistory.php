<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubmissionHistory extends Model
{
    use HasFactory;

    /**
     * Histori status adalah catatan audit — tidak boleh diedit/dihapus bebas.
     */
    protected $fillable = [
        'submission_id',
        'from_status',
        'to_status',
        'actor_user_id',
        'notes',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
