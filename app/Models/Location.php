<?php

namespace App\Models;

use App\Support\Departments;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Location extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'building',
        'department',
        'description',
    ];

    /**
     * Normalisasi jurusan ke kode kanonik (config/departments.php) saat ditulis.
     */
    protected function department(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => Departments::normalizeOrKeep($value),
        );
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }
}
