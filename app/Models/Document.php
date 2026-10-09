<?php

namespace App\Models;

use App\Support\Departments;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use SoftDeletes;

    /**
     * documentable_type / documentable_id dikecualikan dari mass-assignment —
     * set via relasi Eloquent (morphMany/associate) agar kepemilikan morph
     * selalu diverifikasi secara eksplisit.
     */
    protected $fillable = [
        'title',
        'file_path',
        'file_name',
        'file_type',
        'file_size',
        'category',
        'department',
        'user_id',
    ];

    /**
     * Normalisasi jurusan ke kode kanonik saat ditulis (bila dikenal).
     */
    protected function department(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => Departments::normalizeOrKeep($value),
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }
}
