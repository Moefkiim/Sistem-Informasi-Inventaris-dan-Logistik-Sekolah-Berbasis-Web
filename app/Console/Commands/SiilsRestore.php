<?php

namespace App\Console\Commands;

use App\Support\DatabaseSnapshot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use ZipArchive;

/**
 * Memulihkan database dan berkas dokumen dari arsip cadangan.
 */
class SiilsRestore extends Command
{
    protected $signature = 'siils:restore
        {file : Path ke arsip .zip hasil siils:backup}
        {--force : Lewati konfirmasi (wajib di lingkungan production)}';

    protected $description = 'Memulihkan database dan berkas dokumen dari arsip cadangan SIILS';

    public function handle(): int
    {
        $snapshot = new DatabaseSnapshot;
        $path = $this->resolvePath((string) $this->argument('file'));

        if ($path === null) {
            $this->components->error('Arsip cadangan tidak ditemukan.');

            return self::FAILURE;
        }

        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            $this->components->error("Tidak dapat membuka arsip: {$path}");

            return self::FAILURE;
        }

        try {
            $manifest = $this->readManifest($zip);

            if (! $this->validateDriver($snapshot, $manifest)) {
                return self::FAILURE;
            }

            if ($this->exceedsGuards()) {
                return self::FAILURE;
            }

            if (! $this->isConfirmed()) {
                $this->components->warn('Pemulihan dibatalkan.');

                return self::SUCCESS;
            }

            if (! $this->restoreDatabase($zip, $snapshot)) {
                return self::FAILURE;
            }

            $files = $this->restoreDocuments($zip);
        } finally {
            $zip->close();
        }

        $this->components->info('Pemulihan selesai.');
        $this->line('  Database  : '.$snapshot->databaseName().' ('.$snapshot->driver().')');
        $this->line('  Dokumen   : '.$files['restored'].' berkas dipulihkan');

        if ($files['rejected'] > 0) {
            $this->components->warn($files['rejected'].' entri arsip dilewati karena nama/berkas tidak aman ('.implode(', ', array_slice($files['names'], 0, 5)).(count($files['names']) > 5 ? ', …' : '').').');
        }

        return self::SUCCESS;
    }

    private function resolvePath(string $input): ?string
    {
        $candidates = [$input, getcwd().DIRECTORY_SEPARATOR.$input];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && File::isFile($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return array<string,mixed>
     */
    private function readManifest(ZipArchive $zip): array
    {
        $contents = $zip->getFromName('manifest.json');

        if ($contents === false) {
            return [];
        }

        $decoded = json_decode($contents, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string,mixed>  $manifest
     */
    private function validateDriver(DatabaseSnapshot $snapshot, array $manifest): bool
    {
        $driver = (string) ($manifest['driver'] ?? '');

        if ($driver === '' || $driver === $snapshot->driver()) {
            return true;
        }

        if ($this->option('force')) {
            $this->components->warn("Driver arsip \"{$driver}\" berbeda dengan koneksi aktif \"{$snapshot->driver()}\"; dilanjutkan karena --force.");

            return true;
        }

        $this->components->error("Driver arsip \"{$driver}\" tidak cocok dengan koneksi aktif \"{$snapshot->driver()}\". Gunakan --force untuk memaksa.");

        return false;
    }

    private function exceedsGuards(): bool
    {
        if (config('app.env') !== 'production' || $this->option('force')) {
            return false;
        }

        $this->components->error('Pemulihan ditolak di lingkungan production tanpa opsi --force.');

        return true;
    }

    private function isConfirmed(): bool
    {
        if ($this->option('force')) {
            return true;
        }

        if (! $this->input->isInteractive()) {
            $this->components->error('Jalankan ulang dengan opsi --force untuk pemulihan non-interaktif.');

            return false;
        }

        return $this->confirm('Seluruh data saat ini akan digantikan oleh isi arsip. Lanjutkan?', false);
    }

    private function restoreDatabase(ZipArchive $zip, DatabaseSnapshot $snapshot): bool
    {
        if ($snapshot->isSqlite()) {
            $contents = $zip->getFromName('database.sqlite');

            if ($contents === false) {
                $sql = $zip->getFromName('database.sql');

                if ($sql === false) {
                    $this->components->error('Arsip tidak memuat berkas database.');

                    return false;
                }

                $snapshot->restoreSql($sql);

                return true;
            }

            $temporary = tempnam(sys_get_temp_dir(), 'siils-restore-');

            if ($temporary === false) {
                $this->components->error('Tidak dapat membuat berkas sementara.');

                return false;
            }

            File::put($temporary, $contents);
            $snapshot->restoreSqliteFile($temporary);
            @unlink($temporary);

            return true;
        }

        $sql = $zip->getFromName('database.sql');

        if ($sql === false) {
            $this->components->error('Arsip tidak memuat database.sql.');

            return false;
        }

        $snapshot->restoreSql($sql);

        return true;
    }

    /**
     * @return array{restored:int,rejected:int,names:array<int,string>}
     */
    private function restoreDocuments(ZipArchive $zip): array
    {
        $root = storage_path('app/private');
        $restored = 0;
        $rejected = 0;
        $names = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = (string) $zip->getNameIndex($i);

            if (! str_starts_with($entry, 'files/')) {
                continue;
            }

            $relative = substr($entry, strlen('files/'));
            $safe = $this->safeRelativePath($relative);

            if ($safe === null) {
                $rejected++;
                $names[] = $entry;

                continue;
            }

            $target = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $safe);

            if (! $this->isWithinRoot($root, $target)) {
                $rejected++;
                $names[] = $entry;

                continue;
            }

            if (! File::isDirectory(dirname($target))) {
                File::makeDirectory(dirname($target), 0755, true);
            }

            $contents = $zip->getFromIndex($i);

            if ($contents === false) {
                $rejected++;
                $names[] = $entry;

                continue;
            }

            File::put($target, $contents);
            $restored++;
        }

        DB::purge();

        return ['restored' => $restored, 'rejected' => $rejected, 'names' => $names];
    }

    /**
     * Validasi path per segmen: tolak segmen kosong, ".", "..", pemisah
     * Windows, karakter NUL, dan huruf drive. Mengembalikan path relatif yang
     * sudah dinormalisasi atau null bila tidak aman.
     */
    private function safeRelativePath(string $relative): ?string
    {
        if ($relative === '' || str_contains($relative, "\0") || str_contains($relative, '\\')) {
            return null;
        }

        $segments = explode('/', $relative);

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return null;
            }

            if (preg_match('/^[A-Za-z]:$/', $segment) === 1) {
                return null;
            }
        }

        return implode('/', $segments);
    }

    private function isWithinRoot(string $root, string $target): bool
    {
        $root = rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        return str_starts_with($target, $root);
    }
}
