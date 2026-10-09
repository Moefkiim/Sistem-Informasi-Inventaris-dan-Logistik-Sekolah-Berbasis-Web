<?php

namespace App\Support;

/**
 * Pemetaan label laporan yang dipakai bersama oleh tampilan layar,
 * halaman cetak (print), dan ekspor PDF agar istilah tetap konsisten.
 */
class ReportTypes
{
    /**
     * Kode jenis laporan => judul manusiawi.
     *
     * @var array<string,string>
     */
    private const TITLES = [
        'inventory' => 'Rekap Inventaris Barang',
        'incoming' => 'Log Barang Masuk',
        'outgoing' => 'Log Barang Keluar',
        'distribution' => 'Log Distribusi / Penyaluran',
        'submission' => 'Riwayat Pengajuan',
    ];

    public static function titles(): array
    {
        return self::TITLES;
    }

    public static function title(?string $type): string
    {
        return self::TITLES[$type] ?? 'Laporan';
    }

    /**
     * Label singkat untuk judul halaman cetak/pdf.
     */
    public static function isKnown(string $type): bool
    {
        return array_key_exists($type, self::TITLES);
    }
}
