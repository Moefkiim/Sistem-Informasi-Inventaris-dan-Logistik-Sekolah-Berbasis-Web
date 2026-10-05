<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    /**
     * Activity Log tidak boleh diubah setelah dibuat (immutable audit record).
     * Gunakan log() static method untuk mencatat.
     */
    protected $fillable = [
        'user_id',
        'user_name',
        'user_role',
        'action',
        'description',
        'auditable_type',
        'auditable_id',
        'auditable_label',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'logged_at',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'logged_at'  => 'datetime',
        ];
    }

    // === Relationships ===

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // === Static Helper ===

    /**
     * Catat aktivitas ke audit log.
     *
     * @param  string       $action       Aksi (create, update, delete, approve, reject, loan, return, dll.)
     * @param  string       $description  Deskripsi bebas
     * @param  Model|null   $auditable    Object model yang terpengaruh
     * @param  array        $oldValues    Nilai sebelum perubahan
     * @param  array        $newValues    Nilai sesudah perubahan
     * @param  string|null  $label        Label/kode untuk identifikasi mudah
     */
    public static function log(
        string $action,
        string $description,
        ?Model $auditable = null,
        array $oldValues = [],
        array $newValues = [],
        ?string $label = null
    ): self {
        $user = auth()->user();
        $request = request();

        return self::create([
            'user_id'         => $user?->id,
            'user_name'       => $user?->name,
            'user_role'       => $user?->role,
            'action'          => $action,
            'description'     => $description,
            'auditable_type'  => $auditable ? get_class($auditable) : null,
            'auditable_id'    => $auditable?->getKey(),
            'auditable_label' => $label ?? ($auditable ? ($auditable->code ?? $auditable->submission_number ?? $auditable->loan_number ?? null) : null),
            'old_values'      => !empty($oldValues) ? $oldValues : null,
            'new_values'      => !empty($newValues) ? $newValues : null,
            'ip_address'      => $request?->ip(),
            'user_agent'      => $request ? substr($request->userAgent() ?? '', 0, 255) : null,
            'logged_at'       => now(),
        ]);
    }
}
