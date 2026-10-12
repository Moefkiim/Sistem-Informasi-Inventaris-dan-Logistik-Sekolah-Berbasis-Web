<?php

namespace App\Support;

/**
 * Helper terpusat untuk kondisi aset.
 *
 * Kolom kondisi disimpan sebagai string kanonik (bukan enum) agar turunan
 * nilai dapat ditambah tanpa migrasi skema. Urutan severity dipakai untuk
 * agregasi kondisi master (terburuk menang).
 */
class AssetCondition
{
    public const BAIK = 'baik';

    public const RUSAK_RINGAN = 'rusak_ringan';

    public const RUSAK_BERAT = 'rusak_berat';

    public const TIDAK_LAYAK_PAKAI = 'tidak_layak_pakai';

    public const HILANG = 'hilang';

    /**
     * Nilai kanonik, urut dari paling baik sampai paling buruk (severity naik).
     *
     * @var array<int,string>
     */
    public const ALL = [
        self::BAIK,
        self::RUSAK_RINGAN,
        self::RUSAK_BERAT,
        self::TIDAK_LAYAK_PAKAI,
        self::HILANG,
    ];

    /**
     * Label tampilan (kondisi => label).
     *
     * @var array<string,string>
     */
    public const LABELS = [
        self::BAIK => 'Baik',
        self::RUSAK_RINGAN => 'Rusak Ringan',
        self::RUSAK_BERAT => 'Rusak Berat',
        self::TIDAK_LAYAK_PAKAI => 'Tidak Layak Pakai',
        self::HILANG => 'Hilang',
    ];

    /**
     * Warna badge Bootstrap per kondisi.
     *
     * @var array<string,string>
     */
    public const BADGE_COLORS = [
        self::BAIK => 'light-success',
        self::RUSAK_RINGAN => 'light-warning',
        self::RUSAK_BERAT => 'light-danger',
        self::TIDAK_LAYAK_PAKAI => 'light-primary',
        self::HILANG => 'light-dark',
    ];

    /**
     * Tingkat keparahan numerik (semakin besar semakin buruk).
     *
     * @var array<string,int>
     */
    public const SEVERITY = [
        self::BAIK => 0,
        self::RUSAK_RINGAN => 1,
        self::RUSAK_BERAT => 2,
        self::TIDAK_LAYAK_PAKAI => 3,
        self::HILANG => 4,
    ];

    /**
     * Seluruh kondisi (nilai => label).
     *
     * @return array<string,string>
     */
    public static function all(): array
    {
        return self::LABELS;
    }

    /**
     * Daftar nilai kanonik kondisi.
     *
     * @return array<int,string>
     */
    public static function values(): array
    {
        return array_keys(self::LABELS);
    }

    /**
     * Label untuk sebuah nilai; mengembalikan nilai asli bila tak dikenal.
     */
    public static function label(?string $condition): ?string
    {
        if ($condition === null || $condition === '') {
            return null;
        }

        return self::LABELS[$condition] ?? $condition;
    }

    /**
     * Warna badge untuk sebuah nilai; fallback 'light' bila tak dikenal.
     */
    public static function color(?string $condition): string
    {
        return self::BADGE_COLORS[$condition] ?? 'light';
    }

    /**
     * Keparahan numerik; nilai tak dikenal dianggap lebih buruk daripada
     * kondisi valid agar tidak tertimpa saat agregasi.
     */
    public static function severity(?string $condition): int
    {
        return self::SEVERITY[$condition] ?? 999;
    }

    /**
     * True bila nilai termasuk kondisi kanonik.
     */
    public static function isValid(?string $condition): bool
    {
        return $condition !== null && isset(self::LABELS[$condition]);
    }

    /**
     * Kunci aturan validasi Laravel "in:...".
     */
    public static function rule(): string
    {
        return 'in:'.implode(',', self::values());
    }
}
