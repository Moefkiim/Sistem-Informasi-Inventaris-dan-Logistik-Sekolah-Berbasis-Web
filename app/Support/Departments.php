<?php

namespace App\Support;

/**
 * Helper terpusat untuk jurusan.
 *
 * Sumber daftar: config/departments.php (KODE => NAMA LENGKAP).
 * Semua penulisan nilai jurusan ke database harus melewati normalize()/
 * normalizeOrKeep() agar tersimpan sebagai kode kanonik.
 */
class Departments
{
    /**
     * Alias penulisan lama/kasual => kode kanonik.
     * Kunci dibandingkan setelah lowercase + spasi dinormalisasi.
     *
     * @var array<string,string>
     */
    private const ALIASES = [
        'rpl' => 'RPL',
        'rekayasa perangkat lunak' => 'RPL',
        'rekayasa perangkat lunak (rpl)' => 'RPL',
        'tkj' => 'TKJ',
        'teknik komputer jaringan' => 'TKJ',
        'teknik komputer dan jaringan' => 'TKJ',
        'tkro' => 'TKRO',
        'teknik kendaraan ringan otomotif' => 'TKRO',
        'teknik kendaraan ringan' => 'TKRO',
        'tbsm' => 'TBSM',
        'teknik bisnis sepeda motor' => 'TBSM',
        'teknik dan bisnis sepeda motor' => 'TBSM',
        'akl' => 'AKL',
        'akuntansi dan keuangan lembaga' => 'AKL',
        'otkp' => 'OTKP',
        'otomatisasi dan tata kelola perkantoran' => 'OTKP',
        'otomatisasi tata kelola perkantoran' => 'OTKP',
    ];

    /**
     * Seluruh jurusan (KODE => NAMA LENGKAP).
     *
     * @return array<string,string>
     */
    public static function all(): array
    {
        return config('departments', []);
    }

    /**
     * Daftar kode jurusan kanonik.
     *
     * @return array<int,string>
     */
    public static function codes(): array
    {
        return array_keys(static::all());
    }

    /**
     * Nama lengkap untuk sebuah kode. Mengembalikan nilai asli bila tidak
     * dikenal (mis. "Umum") agar data lama tidak hilang saat ditampilkan.
     */
    public static function label(?string $code): ?string
    {
        if ($code === null || $code === '') {
            return null;
        }

        return static::all()[$code] ?? $code;
    }

    /**
     * Kode kanonik bila nilai dikenal (kode/alias/nama lengkap), selain itu null.
     * Mengembalikan null juga untuk input kosong.
     */
    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $key = static::keyize($value);
        if ($key === '') {
            return null;
        }

        if (isset(self::ALIASES[$key])) {
            return self::ALIASES[$key];
        }

        foreach (static::all() as $code => $name) {
            if (static::keyize($code) === $key || static::keyize($name) === $key) {
                return $code;
            }
        }

        return null;
    }

    /**
     * Normalisasi yang mempertahankan nilai tak dikenal: kode kanonik bila
     * cocok, jika tidak kembalikan nilai asli yang sudah di-trim. Input kosong
     * menjadi null.
     */
    public static function normalizeOrKeep(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $canonical = static::normalize($value);

        if ($canonical !== null) {
            return $canonical;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * Nama/judul lengkap untuk ditampilkan; "Umum" bila tidak ada jurusan.
     */
    public static function display(?string $code): string
    {
        return static::label($code) ?? 'Umum';
    }

    /**
     * Kunci pembanding: lowercase, rapikan spasi ganda dan spasi di tepi.
     */
    private static function keyize(string $value): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $value) ?? ''));
    }
}
