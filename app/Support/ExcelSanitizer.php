<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Menulis nilai sel Excel secara aman terhadap injeksi rumus.
 *
 * String yang diawali dengan '=', '+', '-', '@', TAB, atau CR
 * diperlakukan sebagai rumus potensial oleh Excel. Nilai seperti itu
 * diawali dengan apostrof transparan (prefix quoting) agar ditampilkan
 * sebagai teks biasa, bukan rumus.
 *
 * Referensi: OWASP - CSV Formula Injection (berlaku juga pada .xlsx).
 */
class ExcelSanitizer
{
    /**
     * Karakter awal yang memicu interpretasi rumus di Excel / LibreOffice.
     */
    private const FORMULA_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * Tulis nilai ke sel sebagai string yang aman dari injeksi rumus.
     *
     * Angka, null, dan bool dilewatkan apa adanya (tipe numerik/bool).
     * String bertipe rumus-prefix diawali apostrof transparan.
     */
    public static function setCellSafe(
        Worksheet $sheet,
        string $coordinate,
        mixed $value,
    ): void {
        if (is_string($value)) {
            if ($value !== '' && in_array($value[0], self::FORMULA_PREFIXES, true)) {
                // Apostrophe-prefix: Excel menyimpan nilai sebagai string
                // dan menyembunyikan apostrof dari tampilan.
                $sheet->setCellValueExplicit(
                    $coordinate,
                    "'".$value,
                    DataType::TYPE_STRING,
                );
                return;
            }

            $sheet->setCellValueExplicit(
                $coordinate,
                $value,
                DataType::TYPE_STRING,
            );
            return;
        }

        // Angka, tanggal (float), bool, null — tulis apa adanya.
        $sheet->setCellValue($coordinate, $value);
    }

    /**
     * Versi pembantu yang menerima koordinat berbentuk array [kolom, baris].
     *
     * @param  array{int, int}  $coordinate
     */
    public static function setCellSafeByArray(
        Worksheet $sheet,
        array $coordinate,
        mixed $value,
    ): void {
        if (is_string($value)) {
            if ($value !== '' && in_array($value[0], self::FORMULA_PREFIXES, true)) {
                $sheet->setCellValueExplicit(
                    $coordinate,
                    "'".$value,
                    DataType::TYPE_STRING,
                );
                return;
            }

            $sheet->setCellValueExplicit(
                $coordinate,
                $value,
                DataType::TYPE_STRING,
            );
            return;
        }

        $sheet->setCellValue($coordinate, $value);
    }
}
