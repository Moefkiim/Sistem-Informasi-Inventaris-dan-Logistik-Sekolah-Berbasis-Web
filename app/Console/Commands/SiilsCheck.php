<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Pemeriksa prasyarat instalasi. Hanya-baca: tidak mengubah apa pun.
 * Kode keluar 0 = siap, 1 = ada yang gagal, 2 = hanya peringatan.
 */
class SiilsCheck extends Command
{
    protected $signature = 'siils:check';

    protected $description = 'Memeriksa prasyarat instalasi (PHP, ekstensi, APP_KEY, koneksi database, migrasi, izin tulis)';

    /**
     * @var array<int,array{ok:bool,label:string,fix?:string}>
     */
    private array $errors = [];

    /**
     * @var array<int,array{ok:bool,label:string,fix?:string}>
     */
    private array $warnings = [];

    public function handle(): int
    {
        $this->components->info('Memeriksa prasyarat Sistem Informasi Inventaris Sekolah (SIILS)...');

        $this->checkPhpVersion();
        $this->checkExtensions();
        $this->checkAppKey();
        $this->checkDatabase();
        $this->checkPendingMigrations();
        $this->checkDataInvariants();
        $this->checkWritablePaths();
        $this->checkBackups();
        $this->checkDebugInProduction();

        $this->newLine();

        foreach ($this->errors as $error) {
            $this->line('  ✘ '.$error['label']);
            if (! empty($error['fix'])) {
                $this->line('     Solusi: '.$error['fix']);
            }
            $this->newLine();
        }

        foreach ($this->warnings as $warning) {
            $this->line('  ⚠ '.$warning['label']);
            if (! empty($warning['fix'])) {
                $this->line('     Solusi: '.$warning['fix']);
            }
            $this->newLine();
        }

        if (empty($this->errors) && empty($this->warnings)) {
            $this->components->success('Semua prasyarat terpenuhi. Aplikasi siap dipasang.');
        } elseif (empty($this->errors)) {
            $this->components->warn('Prasyarat inti terpenuhi, namun ada peringatan yang perlu diperhatikan.');
        } else {
            $this->components->error('Ada '.count($this->errors).' masalah yang harus diperbaiki sebelum instalasi.');
        }

        return empty($this->errors) ? self::SUCCESS : self::FAILURE;
    }

    private function checkPhpVersion(): void
    {
        $version = PHP_VERSION;
        $ok = version_compare(PHP_VERSION, '8.3.0', '>=');

        if ($ok) {
            $this->line('  ✔ Versi PHP '.$version.' (minimal 8.3)');
        } else {
            $this->errors[] = [
                'ok' => false,
                'label' => "Versi PHP {$version} kurang dari 8.3",
                'fix' => 'Gunakan PHP 8.3 atau lebih baru (Laragon: menu PHP → Versi).',
            ];
        }
    }

    private function checkExtensions(): void
    {
        $required = ['gd', 'zip', 'mbstring', 'openssl', 'fileinfo', 'curl', 'iconv', 'simplexml', 'xmlreader', 'xmlwriter', 'ctype', 'dom'];

        if (config('database.default') === 'mysql') {
            $required[] = 'pdo_mysql';
        }

        foreach ($required as $extension) {
            if (extension_loaded($extension)) {
                $this->line("  ✔ Ekstensi {$extension} aktif");

                continue;
            }

            $this->errors[] = [
                'ok' => false,
                'label' => "Ekstensi {$extension} tidak aktif",
                'fix' => "Aktifkan di php.ini dengan menghapus titik koma pada baris extension={$extension} (Laragon: menu PHP → Ekstensi), lalu restart Apache/PHP.",
            ];
        }
    }

    private function checkAppKey(): void
    {
        $key = config('app.key');

        if (! empty($key) && ! str_starts_with((string) $key, 'base64:invalid')) {
            $this->line('  ✔ APP_KEY sudah terisi');

            return;
        }

        $this->errors[] = [
            'ok' => false,
            'label' => 'APP_KEY belum terisi',
            'fix' => 'Jalankan "php artisan key:generate" atau "composer setup".',
        ];
    }

    private function checkDatabase(): void
    {
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database", '');

        $this->line("  • Koneksi database: {$connection} (database: {$database})");

        try {
            $pdo = DB::connection()->getPdo();

            if ($connection === 'sqlite') {
                $this->line('  ✔ SQLite terhubung (tidak berlaku pengecekan versi MySQL/MariaDB)');

                return;
            }

            $rawVersion = (string) $pdo->query('select version()')->fetchColumn();
            $isMariaDb = str_contains(strtolower($rawVersion), 'mariadb');

            if ($isMariaDb) {
                $this->line("  ✔ Database terhubung (MariaDB {$rawVersion})");
                $this->checkDbVersion($rawVersion, '10.4', 'MariaDB');
            } else {
                $this->line("  ✔ Database terhubung (MySQL {$rawVersion})");
                $this->checkDbVersion($rawVersion, '8.0', 'MySQL');
            }
        } catch (Throwable $e) {
            $this->errors[] = $this->mapConnectionError($e);
        }
    }

    private function checkDbVersion(string $rawVersion, string $minimum, string $brand): void
    {
        preg_match('/(\d+)\.(\d+)/', $rawVersion, $match);
        $version = (isset($match[1], $match[2])) ? $match[1].'.'.$match[2] : '0.0';

        if (version_compare($version, $minimum, '>=')) {
            $this->line("  ✔ Versi {$brand} {$version} (minimal {$minimum})");

            return;
        }

        $this->errors[] = [
            'ok' => false,
            'label' => "Versi {$brand} {$version} terlalu lama (minimal {$minimum})",
            'fix' => "Perbarui {$brand} di Laragon menjadi versi yang lebih baru.",
        ];
    }

    /**
     * @return array{ok:bool,label:string,fix:string}
     */
    private function mapConnectionError(Throwable $e): array
    {
        if ($e instanceof QueryException) {
            $previous = $e->getPrevious();
            if ($previous) {
                $e = $previous;
            }
        }

        $message = $e->getMessage();
        $code = (int) $e->getCode();

        return match (true) {
            $code === 1049 => [
                'ok' => false,
                'label' => 'Database belum dibuat',
                'fix' => 'Jalankan "php artisan siils:install" untuk membuat database otomatis, atau buat manual di HeidiSQL/phpMyAdmin.',
            ],
            $code === 1045 => [
                'ok' => false,
                'label' => 'User/password database salah',
                'fix' => 'Periksa DB_USERNAME dan DB_PASSWORD di .env (default Laragon: root tanpa password).',
            ],
            $code === 2002 => [
                'ok' => false,
                'label' => 'MySQL/MariaDB tidak dapat dijangkau (connecting refused)',
                'fix' => 'Pastikan MySQL/MariaDB sudah menyala (tombol Start All di Laragon) dan DB_HOST/DB_PORT di .env benar.',
            ],
            str_contains($message, 'could not find driver') => [
                'ok' => false,
                'label' => 'Driver PDO database tidak tersedia',
                'fix' => 'Aktifkan extension=pdo_mysql (atau pdo_sqlite) di php.ini, lalu restart.',
            ],
            default => [
                'ok' => false,
                'label' => 'Koneksi database gagal: '.$message,
                'fix' => 'Periksa konfigurasi DB_* di .env dan pastikan server database aktif.',
            ],
        };
    }

    private function checkPendingMigrations(): void
    {
        try {
            $migrator = app('migrator');
            $repository = $migrator->getRepository();

            if (! $repository->repositoryExists()) {
                $this->errors[] = [
                    'ok' => false,
                    'label' => 'Tabel migrasi belum dibuat',
                    'fix' => 'Jalankan "php artisan migrate" atau "composer setup".',
                ];

                return;
            }

            $pending = array_values(array_diff(array_keys($migrator->getMigrationFiles($migrator->paths())), $repository->getRan()));

            if (empty($pending)) {
                $this->line('  ✔ Tidak ada migration yang tertunda');
            } else {
                $this->errors[] = [
                    'ok' => false,
                    'label' => count($pending).' migration belum dijalankan ('.implode(', ', array_slice($pending, 0, 5)).')',
                    'fix' => 'Jalankan "php artisan migrate --force" atau "composer setup".',
                ];
            }
        } catch (Throwable $e) {
            $this->errors[] = [
                'ok' => false,
                'label' => 'Tidak dapat memeriksa status migrasi: '.$e->getMessage(),
                'fix' => 'Hubungi pengelola aplikasi.',
            ];
        }
    }

    /**
     * Memeriksa invarian data penting (hanya-baca): stok item individual
     * terhadap jumlah unit fisik, unit dipinjam tanpa peminjaman aktif, dan
     * unit aktif dengan kondisi tidak layak. Temuan dilaporkan sebagai
     * peringatan agar tidak mengubah kode keluar instalasi.
     */
    private function checkDataInvariants(): void
    {
        if (! Schema::hasTable('items') || ! Schema::hasTable('asset_units')) {
            return;
        }

        try {
            $mismatchedStock = DB::table('items')
                ->leftJoin('asset_units', function ($join) {
                    $join->on('asset_units.item_id', '=', 'items.id')
                        ->whereNull('asset_units.deleted_at')
                        ->where('asset_units.current_status', '!=', 'disposed');
                })
                ->where('items.item_type', 'individual')
                ->whereNull('items.deleted_at')
                ->groupBy('items.id', 'items.stock')
                ->havingRaw('items.stock <> count(asset_units.id)')
                ->count();

            $borrowedWithoutLoan = DB::table('asset_units')
                ->leftJoin('loans', function ($join) {
                    $join->on('loans.asset_unit_id', '=', 'asset_units.id')
                        ->whereIn('loans.status', ['menunggu', 'disetujui', 'dipinjam', 'terlambat'])
                        ->whereNull('loans.deleted_at');
                })
                ->whereNull('asset_units.deleted_at')
                ->where('asset_units.current_status', 'dipinjam')
                ->whereNull('loans.id')
                ->count();

            $activeButUnusable = DB::table('asset_units')
                ->whereNull('deleted_at')
                ->where('current_status', 'aktif')
                ->whereIn('current_condition', ['hilang', 'tidak_layak_pakai'])
                ->count();

            $issues = [];

            if ($mismatchedStock > 0) {
                $issues[] = "{$mismatchedStock} item individual dengan stok tidak sama dengan jumlah unit fisik";
            }

            if ($borrowedWithoutLoan > 0) {
                $issues[] = "{$borrowedWithoutLoan} unit berstatus dipinjam tanpa peminjaman aktif";
            }

            if ($activeButUnusable > 0) {
                $issues[] = "{$activeButUnusable} unit aktif dengan kondisi hilang/tidak layak pakai";
            }

            if ($issues === []) {
                $this->line('  ✔ Tidak ada pelanggaran invarian data terdeteksi');

                return;
            }

            $this->warnings[] = [
                'ok' => false,
                'label' => 'Invarian data perlu ditinjau: '.implode('; ', $issues).'.',
                'fix' => 'Perbaiki lewat menu inventaris/peminjaman atau hubungi pengelola aplikasi.',
            ];
        } catch (Throwable $e) {
            $this->warnings[] = [
                'ok' => false,
                'label' => 'Tidak dapat memeriksa invarian data: '.$e->getMessage(),
                'fix' => 'Hubungi pengelola aplikasi.',
            ];
        }
    }

    private function checkWritablePaths(): void
    {
        foreach ([storage_path(), base_path('bootstrap/cache')] as $path) {
            if (is_writable($path)) {
                $this->line('  ✔ Direktori dapat ditulis: '.$this->shortenPath($path));

                continue;
            }

            $this->errors[] = [
                'ok' => false,
                'label' => 'Direktori tidak dapat ditulis: '.$this->shortenPath($path),
                'fix' => 'Beri izin tulis pada direktori storage dan bootstrap/cache (Windows: properti folder; Linux: chmod -R 775).',
            ];
        }
    }

    private function shortenPath(string $path): string
    {
        return str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
    }

    private function checkBackups(): void
    {
        $directory = (string) config('siils.backup_path', storage_path('app/backups'));
        $latest = null;

        if (is_dir($directory)) {
            foreach ((array) glob($directory.DIRECTORY_SEPARATOR.'siils-backup-*.zip') as $archive) {
                $time = filemtime($archive);

                if ($time !== false && ($latest === null || $time > $latest)) {
                    $latest = $time;
                }
            }
        }

        $maxAgeDays = (int) config('siils.backup_max_age_days', 7);

        if ($latest === null) {
            $this->warnings[] = [
                'ok' => false,
                'label' => 'Belum ada arsip cadangan',
                'fix' => 'Jalankan "php artisan siils:backup" dan simpan arsipnya di lokasi terpisah.',
            ];

            return;
        }

        $ageDays = (int) floor((time() - $latest) / 86400);

        if ($ageDays > $maxAgeDays) {
            $this->warnings[] = [
                'ok' => false,
                'label' => "Arsip cadangan terbaru berumur {$ageDays} hari (disarankan maksimal {$maxAgeDays} hari)",
                'fix' => 'Jalankan "php artisan siils:backup" untuk membuat cadangan terbaru.',
            ];

            return;
        }

        $this->line('  ✔ Arsip cadangan terbaru berumur '.$ageDays.' hari');
    }

    private function checkDebugInProduction(): void
    {
        if (config('app.env') !== 'production') {
            return;
        }

        if (config('app.debug')) {
            $this->warnings[] = [
                'ok' => false,
                'label' => 'APP_DEBUG aktif di lingkungan production',
                'fix' => 'Set APP_DEBUG=false di .env sebelum digunakan secara resmi.',
            ];
        }
    }
}
