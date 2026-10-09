<?php

use App\Support\Departments;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Data migration: menormalkan nilai kolom jurusan ke kode kanonik.
 *
 * Konteks: sebelumnya data memakai DUA penulisan untuk jurusan yang sama:
 * - users.department = 'Rekayasa Perangkat Lunak' (nama panjang), sementara
 * - items.department = 'RPL' (singkatan),
 * sehingga laporan/dashboard yang memfilter dengan kesamaan string persis
 * tampak kosong untuk sebagian jurusan.
 *
 * Migrasi ini idempotent, tidak peka huruf besar/kecil atau spasi ganda,
 * dan HANYA memetakan alias yang dikenal. Nilai yang tidak dapat dipetakan
 * DIBIARKAN apa adanya dan dilaporkan (tidak ditebak).
 *
 * Catatan: normalisasi ini tidak reversibel penuh. down() sengaja
 * non-destruktif (tidak mengubah data), lihat laporan ringkasan.
 */
return new class extends Migration
{
    /**
     * Alias (lowercase, spasi sudah dinormalisasi) => kode kanonik.
     *
     * @var array<string,string>
     */
    private array $aliases = [
        'rpl' => 'RPL',
        'rekayasa perangkat lunak' => 'RPL',
        'rekayasa perangkat lunak (rpl)' => 'RPL',
        'tkj' => 'TKJ',
        'teknik komputer jaringan' => 'TKJ',
        'teknik komputer dan jaringan' => 'TKJ',
        'tkro' => 'TKRO',
        'teknik kendaraan ringan otomotif' => 'TKRO',
        'teknik kendaraan ringan' => 'TKRO',
        'tbsm' => 'TBSM',
        'teknik bisnis sepeda motor' => 'TBSM',
        'teknik dan bisnis sepeda motor' => 'TBSM',
        'akl' => 'AKL',
        'akuntansi dan keuangan lembaga' => 'AKL',
        'otkp' => 'OTKP',
        'otomatisasi dan tata kelola perkantoran' => 'OTKP',
        'otomatisasi tata kelola perkantoran' => 'OTKP',
    ];

    /**
     * Tabel => kolom jurusan yang perlu dinormalkan.
     *
     * @var array<string,string>
     */
    private array $targets = [
        'users' => 'department',
        'items' => 'department',
        'submissions' => 'department',
        'locations' => 'department',
        'documents' => 'department',
        'loans' => 'borrower_department',
        'distributions' => 'recipient_department',
    ];

    public function up(): void
    {
        $report = [];
        $changed = 0;

        foreach ($this->targets as $table => $column) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            $rows = DB::table($table)
                ->select($column)
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->distinct()
                ->pluck($column);

            foreach ($rows as $value) {
                $canonical = $this->canonicalize((string) $value);

                if ($canonical === null || $canonical === $value) {
                    continue;
                }

                $affected = DB::table($table)
                    ->where($column, $value)
                    ->update([$column => $canonical]);

                $changed += $affected;
                $report[] = sprintf('%s.%s: "%s" => "%s" (%d baris)', $table, $column, $value, $canonical, $affected);
            }
        }

        $unmapped = $this->unmappedReport();

        $this->line('Normalisasi jurusan selesai. Total baris diubah: '.$changed);
        foreach ($report as $line) {
            $this->line('  - '.$line);
        }

        if ($unmapped !== []) {
            $this->line('Nilai jurusan yang TIDAK dipetakan (dibiarkan apa adanya):');
            foreach ($unmapped as $line) {
                $this->line('  - '.$line);
            }
        }
    }

    public function down(): void
    {
        // Non-destruktif: normalisasi tidak dibalik secara otomatis karena
        // nilai asli (nama panjang vs singkatan) tidak dapat dipulihkan dengan
        // aman. Nilai lama tercatat pada laporan up().
        $this->line('down(): dilewati. Normalisasi jurusan tidak dibalik (non-destruktif).');
    }

    /**
     * Kode kanonik bila dikenal (kode/alias), selain itu null.
     */
    private function canonicalize(string $value): ?string
    {
        $key = strtolower(trim(preg_replace('/\s+/', ' ', $value) ?? ''));

        if ($key === '') {
            return null;
        }

        if (isset($this->aliases[$key])) {
            return $this->aliases[$key];
        }

        foreach (Departments::all() as $code => $name) {
            $codeKey = strtolower(trim(preg_replace('/\s+/', ' ', $code) ?? ''));
            $nameKey = strtolower(trim(preg_replace('/\s+/', ' ', $name) ?? ''));

            if ($key === $codeKey || $key === $nameKey) {
                return $code;
            }
        }

        return null;
    }

    /**
     * Kumpulkan nilai distinct yang tidak dapat dipetakan ke kode kanonik.
     *
     * @return array<int,string>
     */
    private function unmappedReport(): array
    {
        $known = array_map('strtolower', Departments::codes());
        $unmapped = [];

        foreach ($this->targets as $table => $column) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            $rows = DB::table($table)
                ->select($column)
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->distinct()
                ->pluck($column);

            foreach ($rows as $value) {
                if ($this->canonicalize((string) $value) !== null) {
                    continue;
                }

                if (in_array(strtolower((string) $value), $known, true)) {
                    continue;
                }

                $unmapped[] = sprintf('%s.%s: "%s"', $table, $column, $value);
            }
        }

        return array_values(array_unique($unmapped));
    }

    /**
     * Cetak baris ke output command bila tersedia (CLI), agar laporan migrasi
     * terlihat saat `php artisan migrate`.
     */
    private function line(string $message): void
    {
        if (isset($this->command) && $this->command !== null) {
            $this->command->line($message);
        }
    }
};
