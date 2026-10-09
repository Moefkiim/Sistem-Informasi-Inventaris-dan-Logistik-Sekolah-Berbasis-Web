<?php

namespace App\Models;

use App\Support\Departments;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Submission extends Model
{
    use HasFactory;

    /**
     * Hanya field yang boleh diisi via mass-assignment dari form biasa.
     * Field state-transition (status, sarpras_*, principal_*, *_at) dikecualikan —
     * field tersebut hanya boleh diubah secara eksplisit melalui method controller
     * yang menangani transisi status, bukan dari input form sembarang.
     */
    protected $fillable = [
        'submission_number',
        'user_id',
        'department',
        'title',
        'purpose',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    /**
     * Normalisasi jurusan ke kode kanonik (config/departments.php) saat ditulis.
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

    public function sarprasUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sarpras_user_id');
    }

    public function principalUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'principal_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SubmissionItem::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function canBeProcessedBySarpras(): bool
    {
        return $this->status === 'submitted';
    }

    public function canBeDecidedByPrincipal(): bool
    {
        return $this->status === 'reviewed_sarpras';
    }

    public static function statusLabel(?string $status): string
    {
        return match ($status) {
            'draft' => 'Draft',
            'submitted' => 'Diajukan',
            'reviewed_sarpras' => 'Diverifikasi Sarpras',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            'cancelled' => 'Dibatalkan',
            default => ucfirst((string) $status),
        };
    }

    public function histories(): HasMany
    {
        return $this->hasMany(SubmissionHistory::class)->latest('recorded_at');
    }

    public function recordStatusChange(
        ?string $fromStatus,
        string $toStatus,
        ?User $actor,
        ?string $notes = null,
    ): SubmissionHistory {
        return $this->histories()->create([
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'actor_user_id' => $actor?->id,
            'notes' => $notes,
            'recorded_at' => now(),
        ]);
    }

    protected static function booted(): void
    {
        static::creating(function ($submission) {
            if ($submission->status === null) {
                $submission->status = 'draft';
            }
        });
    }
}
