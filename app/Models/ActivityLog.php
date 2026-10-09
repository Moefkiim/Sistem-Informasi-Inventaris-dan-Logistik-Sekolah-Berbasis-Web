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
            'logged_at' => 'datetime',
        ];
    }

    // === Label Mapping (single source of truth) ===

    public const ACTION_LABELS = [
        'item_created' => 'Barang Ditambahkan',
        'item_location_updated' => 'Lokasi Barang Diubah',
        'item_condition_updated' => 'Kondisi Barang Diubah',
        'submission_created' => 'Pengajuan Dibuat',
        'submission_submitted' => 'Pengajuan Dikirim',
        'submission_cancelled' => 'Pengajuan Dibatalkan',
        'submission_reviewed' => 'Pengajuan Direview Sarpras',
        'submission_rejected_sarpras' => 'Pengajuan Ditolak Sarpras',
        'submission_approved' => 'Pengajuan Disetujui',
        'submission_rejected' => 'Pengajuan Ditolak',
        'loan_created' => 'Peminjaman Dibuat',
        'loan_approved' => 'Peminjaman Disetujui',
        'loan_returned' => 'Peminjaman Dikembalikan',
        'loan_rejected' => 'Peminjaman Ditolak',
        // Aksi ringkas (dipakai sebagian alur/test lama).
        'draft' => 'Disimpan sebagai Draft',
        'submit' => 'Dikirim',
        'review' => 'Direview',
        'approve' => 'Disetujui',
        'reject' => 'Ditolak',
    ];

    /**
     * Pemetaan aksi ke grup. Bila tidak ada, grup diturunkan dari prefix aksi.
     */
    public const ACTION_GROUPS = [
        'item_created' => 'Inventaris',
        'item_location_updated' => 'Inventaris',
        'item_condition_updated' => 'Inventaris',
        'submission_created' => 'Pengajuan',
        'submission_submitted' => 'Pengajuan',
        'submission_cancelled' => 'Pengajuan',
        'submission_reviewed' => 'Pengajuan',
        'submission_rejected_sarpras' => 'Pengajuan',
        'submission_approved' => 'Pengajuan',
        'submission_rejected' => 'Pengajuan',
        'loan_created' => 'Peminjaman',
        'loan_approved' => 'Peminjaman',
        'loan_returned' => 'Peminjaman',
        'loan_rejected' => 'Peminjaman',
    ];

    /**
     * Warna badge (Bootstrap/Metronic) per grup aktivitas.
     */
    public const ACTION_GROUP_COLORS = [
        'Inventaris' => 'primary',
        'Pengajuan' => 'info',
        'Peminjaman' => 'warning',
        'Distribusi' => 'success',
        'Dokumen' => 'dark',
        'Akun' => 'secondary',
        'Lainnya' => 'secondary',
    ];

    public const ACTION_GROUP_ORDER = [
        'Inventaris', 'Pengajuan', 'Peminjaman', 'Distribusi', 'Dokumen', 'Akun', 'Lainnya',
    ];

    private const GROUP_PREFIXES = [
        'item' => 'Inventaris',
        'loan' => 'Peminjaman',
        'submission' => 'Pengajuan',
        'distribution' => 'Distribusi',
        'document' => 'Dokumen',
        'user' => 'Akun',
        'account' => 'Akun',
    ];

    public const FIELD_LABELS = [
        'code' => 'Kode Barang',
        'name' => 'Nama',
        'stock' => 'Stok',
        'quantity' => 'Jumlah',
        'unit' => 'Satuan',
        'category' => 'Kategori',
        'location_id' => 'Lokasi',
        'current_condition' => 'Kondisi',
        'condition_on_return' => 'Kondisi Saat Dikembalikan',
        'serial_number' => 'Nomor Seri',
        'inventory_number' => 'Nomor Inventaris',
        'unit_inventory_number' => 'Nomor Unit',
        'department' => 'Jurusan',
        'status' => 'Status',
        'item' => 'Barang',
        'principal_notes' => 'Catatan Kepala Sekolah',
        'sarpras_notes' => 'Catatan Sarpras',
    ];

    public const VALUE_LABELS = [
        'draft' => 'Draft',
        'submitted' => 'Diajukan',
        'reviewed_sarpras' => 'Direview Sarpras',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak',
        'cancelled' => 'Dibatalkan',
        'menunggu' => 'Menunggu',
        'dipinjam' => 'Dipinjam',
        'dikembalikan' => 'Dikembalikan',
        'ditolak' => 'Ditolak',
        'baik' => 'Baik',
        'rusak_ringan' => 'Rusak Ringan',
        'rusak_berat' => 'Rusak Berat',
        'aktif' => 'Aktif',
    ];

    // === Relationships ===

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // === Static Helper ===

    public static function actionLabel(?string $action): string
    {
        if ($action === null || $action === '') {
            return '-';
        }

        return self::ACTION_LABELS[$action]
            ?? ucwords(str_replace('_', ' ', $action));
    }

    public static function actionGroup(?string $action): string
    {
        if ($action === null || $action === '') {
            return 'Lainnya';
        }

        if (isset(self::ACTION_GROUPS[$action])) {
            return self::ACTION_GROUPS[$action];
        }

        $prefix = explode('_', $action)[0];

        return self::GROUP_PREFIXES[$prefix] ?? 'Lainnya';
    }

    public static function actionColor(?string $action): string
    {
        return self::ACTION_GROUP_COLORS[self::actionGroup($action)] ?? 'secondary';
    }

    /**
     * Kelompokkan daftar aksi (mis. hasil distinct DB) untuk dropdown filter.
     *
     * @param  iterable<string>  $actions
     * @return array<string, array<string, string>>
     */
    public static function groupedActionOptions(iterable $actions): array
    {
        $groups = [];

        foreach ($actions as $action) {
            $groups[self::actionGroup($action)][$action] = self::actionLabel($action);
        }

        foreach ($groups as &$options) {
            asort($options);
        }
        unset($options);

        uksort($groups, function (string $a, string $b): int {
            $order = array_search($a, self::ACTION_GROUP_ORDER, true);
            $order = $order === false ? PHP_INT_MAX : $order;
            $orderB = array_search($b, self::ACTION_GROUP_ORDER, true);
            $orderB = $orderB === false ? PHP_INT_MAX : $orderB;

            return $order <=> $orderB;
        });

        return $groups;
    }

    public static function fieldLabel(string $field): string
    {
        return self::FIELD_LABELS[$field] ?? ucwords(str_replace('_', ' ', $field));
    }

    /**
     * URL detail data terkait bila route tersedia dan datanya masih ada.
     * Halaman ini hanya diakses Sarpras, sehingga semua route detail relevan.
     */
    public function relatedUrl(): ?string
    {
        if (! $this->auditable_id || ! $this->auditable_type) {
            return null;
        }

        return match (class_basename($this->auditable_type)) {
            'Item' => Item::whereKey($this->auditable_id)->exists()
                ? route('sarpras.inventory.show', $this->auditable_id)
                : null,
            'AssetUnit' => ($itemId = AssetUnit::whereKey($this->auditable_id)->value('item_id'))
                ? route('sarpras.inventory.show', $itemId)
                : null,
            'Loan' => Loan::whereKey($this->auditable_id)->exists()
                ? route('sarpras.loans.show', $this->auditable_id)
                : null,
            'Submission' => Submission::whereKey($this->auditable_id)->exists()
                ? route('sarpras.submissions.show', $this->auditable_id)
                : null,
            default => null,
        };
    }

    /**
     * Daftar kolom yang berubah (hanya field dengan nilai berbeda).
     *
     * @return array<int, string>
     */
    public function changedFields(): array
    {
        $old = $this->old_values ?? [];
        $new = $this->new_values ?? [];

        return array_values(array_filter(
            array_unique(array_merge(array_keys($old), array_keys($new))),
            fn ($field) => ($old[$field] ?? null) != ($new[$field] ?? null)
        ));
    }

    /**
     * Nilai manusiawi: map status/kondisi, resolve lokasi, dan kosong jadi em dash.
     */
    public static function displayValue(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (in_array($field, ['status', 'current_condition', 'condition_on_return'], true)
            && is_string($value) && isset(self::VALUE_LABELS[$value])) {
            return self::VALUE_LABELS[$value];
        }

        if ($field === 'location_id' && is_numeric($value)) {
            return Location::find($value)?->name ?? (string) $value;
        }

        if (is_bool($value)) {
            return $value ? 'Ya' : 'Tidak';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return (string) $value;
    }

    /**
     * Catat aktivitas ke audit log.
     *
     * @param  string  $action  Aksi (create, update, delete, approve, reject, loan, return, dll.)
     * @param  string  $description  Deskripsi bebas
     * @param  Model|null  $auditable  Object model yang terpengaruh
     * @param  array  $oldValues  Nilai sebelum perubahan
     * @param  array  $newValues  Nilai sesudah perubahan
     * @param  string|null  $label  Label/kode untuk identifikasi mudah
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
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'user_role' => $user?->role,
            'action' => $action,
            'description' => $description,
            'auditable_type' => $auditable ? get_class($auditable) : null,
            'auditable_id' => $auditable?->getKey(),
            'auditable_label' => $label ?? ($auditable ? ($auditable->code ?? $auditable->submission_number ?? $auditable->loan_number ?? null) : null),
            'old_values' => ! empty($oldValues) ? $oldValues : null,
            'new_values' => ! empty($newValues) ? $newValues : null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? substr($request->userAgent() ?? '', 0, 255) : null,
            'logged_at' => now(),
        ]);
    }
}
