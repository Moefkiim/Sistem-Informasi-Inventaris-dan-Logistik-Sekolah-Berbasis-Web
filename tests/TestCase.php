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
        $database = (string) config("database.connections.{$connection}.database");

        // Cegah eksekusi test pada lingkungan produksi tanpa kecuali
        if ($this->app->environment('production')) {
            throw new RuntimeException('Test suite dilarang dijalankan pada environment production.');
        }

        // Izinkan sqlite :memory: (standar PHPUnit lokal & SQLite CI)
        if ($connection === 'sqlite' && $database === ':memory:') {
            return;
        }

        // Jika koneksi MySQL/MariaDB digunakan untuk pengujian database riil di CI,
        // pastikan dijalankan dengan APP_ENV=testing dan nama database khusus test/CI.
        $isTestDb = str_contains($database, 'test') || str_contains($database, '_ci') || $database === ':memory:';
        if (($connection === 'mysql' || $connection === 'mariadb') && $this->app->environment('testing') && $isTestDb) {
            return;
        }

        throw new RuntimeException(
            "Test suite tidak boleh dijalankan pada database non-test. " .
            "Koneksi aktif: '{$connection}' (database: '{$database}', env: '{$this->app->environment()}'). " .
            "Gunakan sqlite :memory: atau pastikan APP_ENV=testing dengan database test (mis. siils_ci/siils_test). " .
            "Jika baru saja menjalankan 'php artisan optimize', bersihkan cache konfigurasi terlebih dahulu dengan 'php artisan optimize:clear'."
        );
    }
}

