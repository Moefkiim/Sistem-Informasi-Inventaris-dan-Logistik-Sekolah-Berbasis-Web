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
        'name',
        'category',
        'unit',
        'stock',
        'source',
        'department',
        'location_id',
        'current_condition',
        'description',
    ];

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
}
