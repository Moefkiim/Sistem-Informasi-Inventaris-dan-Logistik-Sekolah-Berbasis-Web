<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
use PDO;
use Throwable;

/**
 * Instalasi idempoten: pastikan .env + APP_KEY, buat database bila perlu,
 * migrate, seed, dan cetak ringkasan penggunaan.
 */
class SiilsInstall extends Command
{
    protected $signature = 'siils:install
        {--fresh : Hapus seluruh tabel lalu migrasi ulang (destruktif, hanya lokal)}
        {--tanpa-demo : Hanya buat akun dasar, tanpa data demo}
        {--force : Lewati prompt konfirmasi pada opsi destruktif}';

    protected $description = 'Memasang aplikasi: .env, APP_KEY, database, migration, dan data awal';

    public function handle(): int
    {
        $this->ensureEnvFile();
        $this->reloadEnvironment();

        if (! $this->prepareAppKey()) {
            return self::FAILURE;
        }

        if ($this->option('fresh') && App::isProduction() && ! $this->option('force')) {
            $this->line('  ✘ Opsi --fresh ditolak pada APP_ENV=production.');
            $this->line('     Solusi: tidak disarankan memakai --fresh di server produksi.');

            return self::FAILURE;
        }

        if ($this->option('fresh') && ! $this->confirmFresh()) {
            $this->line('Dibatalkan.');

            return self::FAILURE;
        }

        if (! $this->ensureDatabase()) {
            return self::FAILURE;
        }

        $this->runMigrations();
        $this->runSeeder();

        $this->printSummary();

        return self::SUCCESS;
    }

    private function ensureEnvFile(): void
    {
        $env = base_path('.env');

        if (file_exists($env)) {
            return;
        }

        copy(base_path('.env.example'), $env);
        $this->line('  ✔ File .env dibuat dari .env.example.');
    }

    private function reloadEnvironment(): void
    {
        (new LoadEnvironmentVariables)->bootstrap(App::getInstance());
    }

    private function prepareAppKey(): bool
    {
        $key = config('app.key');

        if (! empty($key)) {
            $this->line('  ✔ APP_KEY sudah terisi.');

            return true;
        }

        $this->line('  • APP_KEY kosong, membuat kunci baru...');
        $this->callSilently('key:generate');
        $this->reloadEnvironment();
        config(['app.key' => env('APP_KEY')]);

        if (empty(config('app.key'))) {
            $this->line('  ✘ Gagal membuat APP_KEY.');

            return false;
        }

        $this->line('  ✔ APP_KEY berhasil dibuat.');

        return true;
    }

    private function confirmFresh(): bool
    {
        if ($this->option('force')) {
            return true;
        }

        if (! $this->input->isInteractive()) {
            return true;
        }

        return $this->components->confirm('Menjalankan --fresh akan menghapus SELURUH data. Lanjutkan?');
    }

    private function ensureDatabase(): bool
    {
        $connection = config('database.default');

        if ($connection === 'sqlite') {
            $this->ensureSqliteFileExists();

            return true;
        }

        if (! in_array($connection, ['mysql', 'mariadb'], true)) {
            $this->line("  ✘ Driver database \"{$connection}\" belum didukung otomatis.");
            $this->line('     Solusi: gunakan MySQL/MariaDB (default) atau SQLite.');

            return false;
        }

        $database = (string) config("database.connections.{$connection}.database", '');
        $host = (string) config("database.connections.{$connection}.host", '127.0.0.1');
        $port = (string) config("database.connections.{$connection}.port", '3306');
        $username = (string) config("database.connections.{$connection}.username", 'root');
        $password = (string) config("database.connections.{$connection}.password", '');

        if (! preg_match('/^[A-Za-z0-9_]+$/', $database)) {
            $this->line("  ✘ Nama database \"{$database}\" tidak valid (hanya huruf, angka, dan garis bawah).");
            $this->line('     Solusi: ubah DB_DATABASE di .env, misalnya "siils".');

            return false;
        }

        $this->line("  • Memastikan database \"{$database}\" tersedia...");

        try {
            $pdo = new PDO("{$connection}:host={$host};port={$port}", $username, $password, [
                PDO::ATTR_TIMEOUT => 5,
            ]);

            $pdo->exec(self::createDatabaseStatement($database));
            $pdo = null;

            $this->line("  ✔ Database \"{$database}\" tersedia (dibuat bila belum ada).");
        } catch (Throwable $e) {
            $message = $e->getMessage();

            if (str_contains($message, 'could not find driver')) {
                $this->line('  ✘ Driver PDO '.$connection.' tidak tersedia.');
                $this->line('     Solusi: aktifkan extension=pdo_mysql di php.ini, lalu restart.');
            } elseif (str_contains($message, 'Access denied')) {
                $this->line('  ✘ User/password database salah saat membuat database.');
                $this->line('     Solusi: periksa DB_USERNAME dan DB_PASSWORD di .env (default Laragon: root tanpa password).');
            } else {
                $this->line('  ✘ Gagal membuat database: '.$message);
                $this->line('     Solusi: pastikan MySQL/MariaDB menyala dan kredensial DB_* benar.');
            }

            return false;
        }

        return true;
    }

    /**
     * Statement CREATE DATABASE. Nama wajib sudah lolos validasi regex
     * ^[A-Za-z0-9_]+$ (lihat ensureDatabase), sehingga aman dibungkus backtick.
     * Backtick adalah identifier yang valid di MySQL maupun MariaDB.
     */
    public static function createDatabaseStatement(string $database): string
    {
        return "CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    }

    private function ensureSqliteFileExists(): void
    {
        $database = (string) config('database.connections.sqlite.database', database_path('database.sqlite'));

        if (! file_exists($database)) {
            $directory = dirname($database);
            if (! is_dir($directory)) {
                mkdir($directory, 0775, true);
            }
            touch($database);
            $this->line("  ✔ File SQLite dibuat: {$database}");
        }
    }

    private function runMigrations(): void
    {
        $this->line('  • Menjalankan migration...');

        $options = ['--force' => true];
        if ($this->option('fresh')) {
            $options['--fresh'] = true;
            $this->components->warn('Menghapus seluruh tabel dan migrasi ulang...');
        }

        Artisan::call('migrate', $options);
        $this->newLine();
        $this->output->write(Artisan::output());
    }

    private function runSeeder(): void
    {
        $this->line('  • Menjalankan seeder...');

        $options = ['--force' => true];

        if ($this->option('tanpa-demo')) {
            $options['--class'] = 'Database\\Seeders\\BaseUserSeeder';
            $this->line('  • Mode tanpa demo: hanya akun dasar.');
        }

        Artisan::call('db:seed', $options);
        $this->newLine();
        $this->output->write(Artisan::output());
    }

    private function printSummary(): void
    {
        $this->newLine();
        $this->components->info('Instalasi selesai.');
        $this->newLine();

        $this->line('  URL aplikasi   : '.config('app.url'));
        $this->line('  Environment    : '.App::environment());
        $this->newLine();

        $this->components->info('Akun demo (HANYA untuk pengembangan lokal):');
        $this->table(
            ['Role', 'Username', 'Password'],
            [
                ['Kajur RPL', 'kajur_rpl', 'password123'],
                ['Kajur TKJ', 'kajur_tkj', 'password123'],
                ['Sarpras', 'sarpras', 'password123'],
                ['Kepala Sekolah', 'kepsek', 'password123'],
            ]
        );

        $this->line('Cara melihat isi database: buka HeidiSQL (menu Database di Laragon) atau phpMyAdmin');
        $this->line('(http://localhost/phpmyadmin), lalu pilih database siils.');
    }
}
