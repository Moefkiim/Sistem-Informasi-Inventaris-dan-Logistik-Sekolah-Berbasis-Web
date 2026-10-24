<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Direktori Arsip Cadangan
    |--------------------------------------------------------------------------
    |
    | Lokasi default arsip hasil "php artisan siils:backup". Dapat diarahkan
    | ke disk terpisah (mis. drive eksternal) melalui SIILS_BACKUP_PATH.
    |
    */

    'backup_path' => env('SIILS_BACKUP_PATH', storage_path('app/backups')),

    /*
    |--------------------------------------------------------------------------
    | Batas Umur Arsip Cadangan
    |--------------------------------------------------------------------------
    |
    | "siils:check" akan memberi peringatan bila arsip cadangan terbaru sudah
    | lebih tua dari jumlah hari ini.
    |
    */

    'backup_max_age_days' => (int) env('SIILS_BACKUP_MAX_AGE_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Jalur Binary mysqldump
    |--------------------------------------------------------------------------
    |
    | Path kustom ke executable mysqldump bila tidak ada di PATH standar.
    |
    */

    'mysqldump_path' => env('MYSQLDUMP_PATH', ''),

    /*
    |--------------------------------------------------------------------------
    | Izin Seeder Data Demo di Produksi
    |--------------------------------------------------------------------------
    |
    | Menentukan apakah DatabaseSeeder diizinkan menanam data demo saat
    | APP_ENV=production. Default false.
    |
    */

    'allow_demo_seed' => filter_var(env('SIILS_ALLOW_DEMO_SEED', false), FILTER_VALIDATE_BOOL),

    /*
    |--------------------------------------------------------------------------
    | HTTP Strict Transport Security (HSTS)
    |--------------------------------------------------------------------------
    |
    | Menentukan apakah header Strict-Transport-Security dikirimkan ketika
    | request menggunakan HTTPS. Default false.
    |
    */

    'hsts' => filter_var(env('SIILS_HSTS', false), FILTER_VALIDATE_BOOL),

    /*
    |--------------------------------------------------------------------------
    | Durasi Remember Me (Hari)
    |--------------------------------------------------------------------------
    |
    | Batas masa berlaku cookie ingat-saya (remember-me) dalam satuan hari.
    | Default 7 hari untuk membatasi risiko pada komputer bersama.
    |
    */

    'remember_me_days' => (int) env('SIILS_REMEMBER_ME_DAYS', 7),

];
