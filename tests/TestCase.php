<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        if ($connection !== 'sqlite' || $database !== ':memory:') {
            throw new RuntimeException(
                "Test suite hanya boleh berjalan pada koneksi sqlite :memory: untuk mencegah modifikasi/penghapusan data non-test. " .
                "Koneksi aktif saat ini: '{$connection}' (database: '{$database}'). " .
                "Jika Anda baru saja menjalankan 'php artisan optimize', bersihkan cache konfigurasi terlebih dahulu dengan 'php artisan optimize:clear'."
            );
        }
    }
}

