<?php

namespace App\Support\Import;

use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

/**
 * Membatasi pembacaan berkas impor pada baris/kolom yang relevan saja.
 *
 * Dipakai agar berkas besar tidak dimuat utuh ke memori: pembaca hanya
 * menyentuh beberapa baris pertama (deteksi batas baris) dan kolom yang
 * dikenal. Baris dipotong satu baris lebih banyak dari batas agar pelanggaran
 * batas tetap terdeteksi.
 */
class LimitedReadFilter implements IReadFilter
{
    public function __construct(
        private readonly int $maxRow,
        private readonly int $maxColumn,
    ) {}

    public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
    {
        if ($row > $this->maxRow) {
            return false;
        }

        $columnIndex = 0;
        $length = strlen($columnAddress);

        for ($i = 0; $i < $length; $i++) {
            $ord = ord($columnAddress[$i]);
            if ($ord < 65 || $ord > 90) {
                break;
            }
            $columnIndex = $columnIndex * 26 + ($ord - 64);
        }

        return $columnIndex >= 1 && $columnIndex <= $this->maxColumn;
    }
}
