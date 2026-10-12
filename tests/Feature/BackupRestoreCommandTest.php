<?php

namespace Tests\Feature;

use App\Console\Commands\SiilsBackup;
use App\Models\User;
use App\Support\DatabaseSnapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
use ZipArchive;

class BackupRestoreCommandTest extends TestCase
{
    private string $databaseFile;

    private string $backupDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $token = bin2hex(random_bytes(6));
        $this->databaseFile = sys_get_temp_dir()."/siils_backup_{$token}.sqlite";
        $this->backupDirectory = sys_get_temp_dir()."/siils_backups_{$token}";

        touch($this->databaseFile);
        File::makeDirectory($this->backupDirectory, 0755, true);

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', $this->databaseFile);

        DB::purge('sqlite');

        $this->artisan('migrate --force')->assertExitCode(0);
    }

    protected function tearDown(): void
    {
        @unlink($this->databaseFile);
        @unlink("{$this->databaseFile}-journal");

        File::deleteDirectory($this->backupDirectory);

        parent::tearDown();
    }

    public function test_berkas_sementara_dibersihkan_setelah_backup(): void
    {
        $before = $this->temporaryArtifacts();

        $this->artisan('siils:backup', ['--path' => $this->backupDirectory])->assertSuccessful();

        $this->assertSame($before, $this->temporaryArtifacts());
    }

    public function test_backup_membuat_arsip_dengan_manifest_dan_jumlah_baris(): void
    {
        User::factory()->count(3)->create();

        $this->artisan('siils:backup', ['--path' => $this->backupDirectory])
            ->assertSuccessful();

        $archives = $this->archives();
        $this->assertCount(1, $archives);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($archives[0]) === true);
        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);

        $this->assertSame('sqlite', $manifest['driver']);
        $this->assertSame(3, $manifest['jumlah_baris']['users']);
        $this->assertNotFalse($zip->getFromName('database.sqlite'));
        $this->assertFalse($zip->getFromName('.env'));

        $zip->close();
    }

    public function test_backup_menyertakan_berkas_dokumen_privat_dan_dapat_dilewati(): void
    {
        $relative = 'documents/'.bin2hex(random_bytes(4)).'.txt';
        $absolute = storage_path('app/private/'.$relative);
        File::ensureDirectoryExists(dirname($absolute));
        File::put($absolute, 'isi dokumen');

        try {
            $this->artisan('siils:backup', ['--path' => $this->backupDirectory])->assertSuccessful();

            $zip = new ZipArchive;
            $zip->open($this->archives()[0]);
            $this->assertNotFalse($zip->getFromName('files/'.$relative));
            $zip->close();

            File::delete($absolute);

            $this->artisan('siils:backup', ['--path' => $this->backupDirectory, '--tanpa-berkas' => true])
                ->assertSuccessful();

            $withFiles = $this->archives();
            $zip = new ZipArchive;
            $zip->open($withFiles[0]);
            $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
            $this->assertFalse($manifest['menyertakan_berkas']);
            $this->assertFalse($zip->getFromName('files/'.$relative));
            $zip->close();
        } finally {
            File::delete($absolute);
        }
    }

    public function test_retensi_menyisakan_arsip_terbaru_saja(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->artisan('siils:backup', ['--path' => $this->backupDirectory, '--keep' => 2])
                ->assertSuccessful();

            $this->travel(2)->seconds();
        }

        $this->assertCount(2, $this->archives());
    }

    public function test_restore_mengembalikan_data_dari_arsip(): void
    {
        User::factory()->count(2)->create();
        $expected = User::count();

        $this->artisan('siils:backup', ['--path' => $this->backupDirectory])->assertSuccessful();

        User::query()->delete();
        $this->assertSame(0, User::count());

        $this->artisan('siils:restore', ['file' => $this->archives()[0], '--force' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Pemulihan selesai');

        $this->assertSame($expected, User::count());
    }

    public function test_restore_ditolak_di_production_tanpa_force(): void
    {
        $this->artisan('siils:backup', ['--path' => $this->backupDirectory])->assertSuccessful();

        config()->set('app.env', 'production');

        try {
            $this->artisan('siils:restore', ['file' => $this->archives()[0]])
                ->assertFailed()
                ->expectsOutputToContain('production');
        } finally {
            config()->set('app.env', 'testing');
        }
    }

    public function test_dump_mysql_tidak_menaruh_kredensial_pada_argumen(): void
    {
        $password = 'rahasia-super-penting';

        config()->set('database.connections.mysql.username', 'siils_user');
        config()->set('database.connections.mysql.password', $password);
        config()->set('database.connections.mysql.host', '127.0.0.1');
        config()->set('database.connections.mysql.port', '3306');
        config()->set('database.connections.mysql.database', 'siils');

        $command = new SiilsBackup;
        $snapshot = new DatabaseSnapshot('mysql');

        $optionFile = '/tmp/siils-test.cnf';
        $built = $command->buildDumpCommand('/usr/bin/mysqldump', $optionFile, $snapshot);

        $this->assertStringContainsString('--defaults-extra-file=', $built);
        $this->assertStringNotContainsString($password, $built);
        $this->assertStringContainsString('--ignore-table', $built);
        $this->assertStringContainsString('siils.sessions', $built);

        $credentials = $command->credentialsContent($snapshot);
        $this->assertStringContainsString('password="'.$password.'"', $credentials);
    }

    public function test_split_statements_menghormati_string_dan_komentar(): void
    {
        $snapshot = new DatabaseSnapshot('sqlite');

        $sql = "-- komentar\nINSERT INTO `t` (`a`) VALUES ('nila;i');\n/* blok */\nINSERT INTO `t` (`a`) VALUES ('b');";

        $statements = $snapshot->splitStatements($sql);

        $this->assertCount(2, $statements);
        $this->assertStringContainsString("'nila;i'", $statements[0]);
        $this->assertStringContainsString("'b'", $statements[1]);
    }

    /**
     * @return array<int,string>
     */
    private function archives(): array
    {
        $archives = glob($this->backupDirectory.DIRECTORY_SEPARATOR.'siils-backup-*.zip') ?: [];
        sort($archives);

        return $archives;
    }

    /**
     * @return array<int,string>
     */
    private function temporaryArtifacts(): array
    {
        $artifacts = array_merge(
            glob(sys_get_temp_dir().'/siils-db-*') ?: [],
            glob(sys_get_temp_dir().'/siils-my-*') ?: [],
            glob(sys_get_temp_dir().'/siils-restore-*') ?: [],
        );

        sort($artifacts);

        return $artifacts;
    }
}
