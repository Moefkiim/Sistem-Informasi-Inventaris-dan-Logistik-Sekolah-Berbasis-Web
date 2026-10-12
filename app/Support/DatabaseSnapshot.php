<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;

/**
 * Utilitas dump/restore database yang portabel (tanpa bergantung pada biner
 * eksternal). Dipakai oleh perintah siils:backup dan siils:restore.
 */
class DatabaseSnapshot
{
    /**
     * Tabel sementara yang tidak perlu dicadangkan.
     *
     * @var array<int,string>
     */
    public const EXCLUDED_TABLES = ['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'];

    private const CHUNK_SIZE = 500;

    private string $connection;

    public function __construct(?string $connection = null)
    {
        $this->connection = $connection ?? (string) config('database.default');
    }

    public function connectionName(): string
    {
        return $this->connection;
    }

    public function driver(): string
    {
        return (string) config("database.connections.{$this->connection}.driver");
    }

    public function databaseName(): string
    {
        return (string) config("database.connections.{$this->connection}.database", '');
    }

    public function isSqlite(): bool
    {
        return $this->driver() === 'sqlite';
    }

    public function sqlitePath(): string
    {
        return (string) config("database.connections.{$this->connection}.database");
    }

    /**
     * Daftar tabel basis data, tanpa tabel sementara.
     *
     * @return array<int,string>
     */
    public function tables(): array
    {
        $pdo = $this->pdo();

        if ($this->isSqlite()) {
            $names = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'")
                ->fetchAll(PDO::FETCH_COLUMN);
        } else {
            $names = $pdo->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')
                ->fetchAll(PDO::FETCH_COLUMN);
        }

        $names = array_values(array_filter(
            $names,
            fn ($name) => ! in_array($name, self::EXCLUDED_TABLES, true),
        ));

        sort($names);

        return $names;
    }

    /**
     * Menghitung jumlah baris per tabel (untuk manifest).
     *
     * @return array<string,int>
     */
    public function rowCounts(): array
    {
        $counts = [];

        foreach ($this->tables() as $table) {
            $counts[$table] = (int) $this->pdo()->query('SELECT COUNT(*) FROM '.$this->quoteIdentifier($table))->fetchColumn();
        }

        return $counts;
    }

    /**
     * Membuat dump SQL portabel (satu pernyataan per baris) untuk seluruh tabel.
     */
    public function dumpSql(): string
    {
        $pdo = $this->pdo();
        $out = "-- SIILS portable database dump\n";
        $out .= '-- Driver: '.$this->driver().' | Database: '.$this->databaseName()."\n";
        $out .= '-- Dibuat: '.now()->toDateTimeString()."\n\n";

        if (! $this->isSqlite()) {
            $out .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
        }

        foreach ($this->tables() as $table) {
            $out .= $this->createTableSql($pdo, $table)."\n\n";

            $offset = 0;

            while (true) {
                $sql = 'SELECT * FROM '.$this->quoteIdentifier($table)
                    .' LIMIT '.self::CHUNK_SIZE.' OFFSET '.$offset;
                $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

                if ($rows === []) {
                    break;
                }

                foreach ($rows as $row) {
                    $out .= $this->insertSql($pdo, $table, $row)."\n";
                }

                if (count($rows) < self::CHUNK_SIZE) {
                    break;
                }

                $offset += self::CHUNK_SIZE;
            }

            $out .= "\n";
        }

        if (! $this->isSqlite()) {
            $out .= "SET FOREIGN_KEY_CHECKS=1;\n";
        }

        return $out;
    }

    /**
     * Menjalankan dump SQL pada koneksi aktif (mengganti isi tabel bila ada).
     */
    public function restoreSql(string $sql): void
    {
        $pdo = $this->pdo();

        if ($this->isSqlite()) {
            $pdo->exec('PRAGMA foreign_keys = OFF');
        } else {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        }

        try {
            foreach ($this->splitStatements($sql) as $statement) {
                try {
                    $pdo->exec($statement);
                } catch (\PDOException $e) {
                    throw new RuntimeException('Gagal mengeksekusi pernyataan saat pemulihan: '.$e->getMessage(), 0, $e);
                }
            }
        } finally {
            if ($this->isSqlite()) {
                $pdo->exec('PRAGMA foreign_keys = ON');
            } else {
                $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
            }
        }
    }

    /**
     * Menimpa berkas database SQLite dengan salinan sumber.
     */
    public function restoreSqliteFile(string $source): void
    {
        $destination = $this->sqlitePath();

        DB::purge($this->connection);
        copy($source, $destination);
        DB::reconnect($this->connection);
    }

    public function pdo(): PDO
    {
        return DB::connection($this->connection)->getPdo();
    }

    public function quoteIdentifier(string $identifier): string
    {
        $quote = $this->isSqlite() ? '"' : '`';

        return $quote.str_replace($quote, $quote.$quote, $identifier).$quote;
    }

    private function createTableSql(PDO $pdo, string $table): string
    {
        $drop = 'DROP TABLE IF EXISTS '.$this->quoteIdentifier($table).";\n";

        if ($this->isSqlite()) {
            $statement = $pdo->prepare("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = ?");
            $statement->execute([$table]);
            $create = (string) $statement->fetchColumn();

            if ($create === '') {
                throw new RuntimeException("Tidak dapat membaca skema tabel SQLite: {$table}");
            }

            return $drop.$create.';';
        }

        $row = $pdo->query('SHOW CREATE TABLE '.$this->quoteIdentifier($table))->fetch(PDO::FETCH_ASSOC);
        $create = (string) ($row['Create Table'] ?? '');

        if ($create === '') {
            throw new RuntimeException("Tidak dapat membaca skema tabel: {$table}");
        }

        return $drop.$create.';';
    }

    /**
     * @param  array<string,mixed>  $row
     */
    private function insertSql(PDO $pdo, string $table, array $row): string
    {
        $columns = array_map(fn ($column) => $this->quoteIdentifier($column), array_keys($row));
        $values = array_map(fn ($value) => $this->valueSql($pdo, $value), array_values($row));

        return 'INSERT INTO '.$this->quoteIdentifier($table)
            .' ('.implode(', ', $columns).') VALUES ('.implode(', ', $values).');';
    }

    private function valueSql(PDO $pdo, mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return $pdo->quote((string) $value);
    }

    /**
     * Memecah dump SQL menjadi pernyataan individual dengan menghormati
     * string literal, identifier, dan komentar.
     *
     * @return array<int,string>
     */
    public function splitStatements(string $sql): array
    {
        $statements = [];
        $current = '';
        $length = strlen($sql);
        $i = 0;

        while ($i < $length) {
            $char = $sql[$i];

            if ($char === '-' && ($sql[$i + 1] ?? '') === '-') {
                while ($i < $length && $sql[$i] !== "\n") {
                    $i++;
                }

                continue;
            }

            if ($char === '/' && ($sql[$i + 1] ?? '') === '*') {
                $end = strpos($sql, '*/', $i + 2);
                $i = $end === false ? $length : $end + 2;

                continue;
            }

            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                $current .= $char;
                $i++;

                while ($i < $length) {
                    $inner = $sql[$i];
                    $current .= $inner;
                    $i++;

                    if ($inner === '\\' && $quote !== '`' && $i < $length) {
                        $current .= $sql[$i];
                        $i++;

                        continue;
                    }

                    if ($inner === $quote) {
                        break;
                    }
                }

                continue;
            }

            if ($char === ';') {
                $trimmed = trim($current);

                if ($trimmed !== '') {
                    $statements[] = $trimmed;
                }

                $current = '';
                $i++;

                continue;
            }

            $current .= $char;
            $i++;
        }

        $tail = trim($current);

        if ($tail !== '') {
            $statements[] = $tail;
        }

        return $statements;
    }
}
