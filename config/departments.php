<?php

/**
 * Sumber kebenaran tunggal untuk daftar jurusan sekolah.
 *
 * Format: KODE => NAMA LENGKAP.
 * - KODE adalah nilai kanonik yang disimpan di kolom `department` (singkatan).
 * - NAMA LENGKAP dipakai untuk tampilan (label) di seluruh antarmuka.
 *
 * Semua form/filter yang menerima jurusan harus diisi dari config ini, bukan
 * teks bebas. Alias penulisan lama (mis. "Rekayasa Perangkat Lunak") dipetakan
 * ke kode kanonik melalui App\Support\Departments.
 */
return [
    'RPL' => 'Rekayasa Perangkat Lunak',
    'TKJ' => 'Teknik Komputer dan Jaringan',
    'TKRO' => 'Teknik Kendaraan Ringan Otomotif',
    'TBSM' => 'Teknik Bisnis Sepeda Motor',
    'AKL' => 'Akuntansi dan Keuangan Lembaga',
    'OTKP' => 'Otomatisasi dan Tata Kelola Perkantoran',
];
