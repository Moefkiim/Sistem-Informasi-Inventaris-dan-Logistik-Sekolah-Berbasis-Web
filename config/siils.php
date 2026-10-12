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

];
