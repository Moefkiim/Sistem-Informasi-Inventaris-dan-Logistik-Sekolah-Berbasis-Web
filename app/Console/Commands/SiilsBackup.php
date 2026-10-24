<?php

namespace App\Console\Commands;

use App\Support\DatabaseSnapshot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * Membuat arsip cadangan: database, berkas dokumen (disk privat), dan manifest.
 */
class SiilsBackup extends Command
{
    protected $signature = 'siils:backup
        {--keep=14 : Jumlah arsip terbaru yang disimpan (sisanya dihapus)}
        {--path= : Direktori tujuan arsip (default: storage/app/backups)}
        {--tanpa-berkas : Lewati berkas dokumen, hanya database}
        {--tanpa-mysqldump : Paksa memakai pembuat dump PHP portabel}';

    protected $description = 'Membuat arsip cadangan database, berkas dokumen, dan manifest untuk SIILS';

    private const PRIVATE_DISK_PATH = 'app/private';

    public function handle(): int
    {
        $snapshot = new DatabaseSnapshot;
        $destination = $this->option('path') ?: (string) config('siils.backup_path', storage_path('app/backups'));

        if (! File::isDirectory($destination) && ! File::makeDirectory($destination, 0755, true)) {
            $this->components->error("Tidak dapat membuat direktori tujuan: {$destination}");

            return self::FAILURE;
        }

        $archive = rtrim($destination, DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR.'siils-backup-'.now()->format('Ymd-His').'.zip';

        [$temporaryDatabase, $databaseEntry, $cleanup] = $this->prepareDatabase($snapshot);

        if ($temporaryDatabase === null) {
            return self::FAILURE;
        }

        try {
            $manifest = $this->buildManifest($snapshot);

            $zip = new ZipArchive;

            if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                $this->components->error("Tidak dapat membuat arsip: {$archive}");
                $cleanup();

                return self::FAILURE;
            }

            $zip->addFile($temporaryDatabase, $databaseEntry);
            $zip->addFromString(
                'manifest.json',
                json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n",
            );

            if (! $this->option('tanpa-berkas')) {
                $this->addDocuments($zip);
            }

            $zip->close();
        } finally {
            $cleanup();
        }

        $this->prune($destination, (int) $this->option('keep'));

        $size = File::exists($archive) ? $this->humanSize((int) File::size($archive)) : '0 B';

        $this->components->info('Arsip cadangan dibuat:');
        $this->line('  Lokasi    : '.$archive);
        $this->line('  Ukuran    : '.$size);
        $this->line('  Database  : '.$databaseEntry);
        $this->line('  Dokumen   : '.($this->option('tanpa-berkas') ? 'tidak disertakan' : 'disertakan'));

        return self::SUCCESS;
    }

    /**
     * Menyiapkan berkas database sementara.
     *
     * @return array{0:?string,1:?string,2:callable} [path, entryName, cleanup]
     */
    private function prepareDatabase(DatabaseSnapshot $snapshot): array
    {
        $temporary = tempnam(sys_get_temp_dir(), 'siils-db-');

        if ($temporary === false) {
            $this->components->error('Tidak dapat membuat berkas sementara.');

            return [null, null, fn () => null];
        }

        $cleanup = function () use ($temporary): void {
            if (File::exists($temporary)) {
                @unlink($temporary);
            }
        };

        if ($snapshot->isSqlite()) {
            $source = $snapshot->sqlitePath();

            if (! File::isFile($source)) {
                $this->components->error("Berkas database SQLite tidak ditemukan: {$source}");

                return [null, null, $cleanup];
            }

            copy($source, $temporary);

            return [$temporary, 'database.sqlite', $cleanup];
        }

        if (! $this->option('tanpa-mysqldump') && $this->dumpWithMysqldump($snapshot, $temporary)) {
            return [$temporary, 'database.sql', $cleanup];
        }

        File::put($temporary, $snapshot->dumpSql());

        return [$temporary, 'database.sql', $cleanup];
    }

    private function dumpWithMysqldump(DatabaseSnapshot $snapshot, string $destination): bool
    {
        $binary = $this->mysqldumpBinary();

        if ($binary === null) {
            return false;
        }

        $optionFile = tempnam(sys_get_temp_dir(), 'siils-my-');

        if ($optionFile === false) {
            return false;
        }

        File::put($optionFile, $this->credentialsContent($snapshot));
        @chmod($optionFile, 0600);

        try {
            $command = $this->buildDumpCommand($binary, $optionFile, $snapshot);

            $process = Process::fromShellCommandline($command);
            $process->setTimeout(300);
            $process->run();

            if (! $process->isSuccessful()) {
                $this->components->warn('mysqldump gagal, memakai dump PHP portabel: '.trim($process->getErrorOutput()));

                return false;
            }

            File::put($destination, $process->getOutput());

            return true;
        } finally {
            if (File::exists($optionFile)) {
                @unlink($optionFile);
            }
        }
    }

    public function buildDumpCommand(string $binary, string $optionFile, DatabaseSnapshot $snapshot): string
    {
        return escapeshellarg($binary)
            .' --defaults-extra-file='.escapeshellarg($optionFile)
            .' --single-transaction --skip-lock-tables --no-tablespaces'
            .$this->ignoreTableFlags($snapshot)
            .' '.escapeshellarg($snapshot->databaseName());
    }

    public function credentialsContent(DatabaseSnapshot $snapshot): string
    {
        $config = config("database.connections.{$snapshot->connectionName()}");

        $lines = [
            '[client]',
            'user='.$this->optionValue((string) ($config['username'] ?? '')),
            'password='.$this->optionValue((string) ($config['password'] ?? '')),
        ];

        if (! empty($config['host'])) {
            $lines[] = 'host='.$this->optionValue((string) $config['host']);
        }

        if (! empty($config['port'])) {
            $lines[] = 'port='.$this->optionValue((string) $config['port']);
        }

        if (! empty($config['unix_socket'])) {
            $lines[] = 'socket='.$this->optionValue((string) $config['unix_socket']);
        }

        return implode("\n", $lines)."\n";
    }

    private function optionValue(string $value): string
    {
        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    }

    private function ignoreTableFlags(DatabaseSnapshot $snapshot): string
    {
        $database = $snapshot->databaseName();
        $flags = '';

        foreach (DatabaseSnapshot::EXCLUDED_TABLES as $table) {
            $flags .= ' --ignore-table='.escapeshellarg($database.'.'.$table);
        }

        return $flags;
    }

    private function mysqldumpBinary(): ?string
    {
        $candidates = [];

        $envPath = (string) config('siils.mysqldump_path', '');

        if ($envPath !== '') {
            $candidates[] = $envPath;
        }

        if (PHP_OS_FAMILY === 'Windows' && is_dir('C:\\laragon\\bin\\mysql')) {
            foreach ((array) glob('C:\\laragon\\bin\\mysql\\*\\bin\\mysqldump.exe') as $found) {
                $candidates[] = $found;
            }
        }

        $candidates[] = 'mysqldump';

        foreach ($candidates as $candidate) {
            if ($candidate === 'mysqldump') {
                $process = Process::fromShellCommandline('mysqldump --version');
                $process->run();

                if ($process->isSuccessful()) {
                    return 'mysqldump';
                }

                continue;
            }

            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function addDocuments(ZipArchive $zip): void
    {
        $root = storage_path(self::PRIVATE_DISK_PATH);

        if (! File::isDirectory($root)) {
            return;
        }

        foreach (File::allFiles($root) as $file) {
            $relative = str_replace('\\', '/', $file->getRelativePathname());

            if (str_contains($relative, '..')) {
                continue;
            }

            $zip->addFile($file->getPathname(), 'files/'.$relative);
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function buildManifest(DatabaseSnapshot $snapshot): array
    {
        return [
            'aplikasi' => config('app.name'),
            'dibuat_pada' => now()->toIso8601String(),
            'zona_waktu' => config('app.timezone'),
            'driver' => $snapshot->driver(),
            'database' => $snapshot->databaseName(),
            'versi_php' => PHP_VERSION,
            'versi_laravel' => app()->version(),
            'versi_database' => $this->databaseVersion($snapshot),
            'jumlah_baris' => $snapshot->rowCounts(),
            'menyertakan_berkas' => ! $this->option('tanpa-berkas'),
        ];
    }

    private function databaseVersion(DatabaseSnapshot $snapshot): ?string
    {
        try {
            return (string) $snapshot->pdo()->getAttribute(\PDO::ATTR_SERVER_VERSION);
        } catch (\Throwable) {
            return null;
        }
    }

    private function prune(string $directory, int $keep): void
    {
        if ($keep < 1) {
            $keep = 1;
        }

        $archives = collect(File::files($directory))
            ->filter(fn ($file) => str_starts_with($file->getFilename(), 'siils-backup-') && $file->getExtension() === 'zip')
            ->sortByDesc(fn ($file) => $file->getFilename())
            ->values();

        foreach ($archives->slice($keep) as $stale) {
            @unlink($stale->getPathname());
            $this->components->warn('Arsip lama dihapus: '.$stale->getFilename());
        }
    }

    private function humanSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $power = $bytes > 0 ? (int) floor(log($bytes, 1024)) : 0;
        $power = min($power, count($units) - 1);

        return round($bytes / (1024 ** $power), 2).' '.$units[$power];
    }
}
