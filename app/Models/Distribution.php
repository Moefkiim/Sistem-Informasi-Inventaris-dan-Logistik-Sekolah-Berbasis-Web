<?php

namespace App\Models;

use App\Support\Departments;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Distribution extends Model
{
    protected $fillable = [
        'distribution_number',
        'item_id',
        'quantity',
        'to_location_id',
        'recipient_department',
        'recipient_name',
        'distribution_date',
        'user_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'distribution_date' => 'date',
            'quantity' => 'integer',
        ];
    }

    /**
     * Normalisasi jurusan penerima ke kode kanonik saat ditulis (bila dikenal).
     */
    protected function recipientDepartment(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => Departments::normalizeOrKeep($value),
        );
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
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
