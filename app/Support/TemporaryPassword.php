<?php

namespace App\Support;

use Random\Randomizer;

/**
 * Pembuat kata sandi sementara acak untuk reset akun.
 *
 * Menghindari karakter yang mudah tertukar saat dibaca/diketik:
 * 0 (nol) vs O, 1 (satu) vs l/I. Kata sandi selalu memuat minimal satu
 * huruf besar, satu huruf kecil, dan satu angka.
 */
class TemporaryPassword
{
    private const UPPERCASE = 'ABCDEFGHJKMNPQRSTUVWXYZ';

    private const LOWERCASE = 'abcdefghijkmnpqrstuvwxyz';

    private const DIGITS = '23456789';

    public static function generate(int $length = 12): string
    {
        $length = max(8, $length);

        $all = self::UPPERCASE.self::LOWERCASE.self::DIGITS;

        $chars = [
            self::UPPERCASE[random_int(0, strlen(self::UPPERCASE) - 1)],
            self::LOWERCASE[random_int(0, strlen(self::LOWERCASE) - 1)],
            self::DIGITS[random_int(0, strlen(self::DIGITS) - 1)],
        ];

        for ($i = count($chars); $i < $length; $i++) {
            $chars[] = $all[random_int(0, strlen($all) - 1)];
        }

        // Fisher–Yates berbasis CSPRNG (shuffle() lama bukan secure random,
        // dan bisa meleset dari distribusi seragam untuk elemen banyak).
        $randomizer = new Randomizer;

        return implode('', $randomizer->shuffleArray($chars));
    }
}
