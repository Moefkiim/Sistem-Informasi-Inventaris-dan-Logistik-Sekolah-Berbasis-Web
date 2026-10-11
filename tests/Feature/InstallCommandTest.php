<?php

namespace Tests\Feature;

use App\Console\Commands\SiilsInstall;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InstallCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->sqliteFile = sys_get_temp_dir().'/siils_install_test.sqlite';
        @unlink($this->sqliteFile);
        touch($this->sqliteFile);

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', $this->sqliteFile);
    }

    /** @var string */
    private $sqliteFile;

    protected function tearDown(): void
    {
        @unlink($this->sqliteFile);

        parent::tearDown();
    }

    public function test_siils_check_lolos_saat_database_siap(): void
    {
        $this->artisan('migrate --force')->assertExitCode(0);

        $this->artisan('siils:check')
            ->assertSuccessful()
            ->expectsOutputToContain('Semua prasyarat terpenuhi');
    }

    public function test_siils_install_idempoten_tidak_menggandakan_data(): void
    {
        $this->artisan('siils:install')->assertSuccessful();

        $users = User::count();
        $this->assertGreaterThan(0, $users);

        $this->artisan('siils:install')->assertSuccessful();

        $this->assertSame($users, User::count());
    }

    public function test_siils_check_gagal_saat_app_key_kosong(): void
    {
        config()->set('app.key', '');

        $this->artisan('migrate --force')->assertExitCode(0);

        $this->artisan('siils:check')
            ->assertFailed()
            ->expectsOutputToContain('APP_KEY belum terisi');
    }

    public function test_instalasi_menolak_fresh_saat_app_env_production(): void
    {
        $this->app->instance('env', 'production');
        config()->set('app.env', 'production');

        try {
            $this->artisan('siils:install', ['--fresh' => true])
                ->assertFailed()
                ->expectsOutputToContain('--fresh ditolak pada APP_ENV=production');
        } finally {
            $this->app->instance('env', 'testing');
            config()->set('app.env', 'testing');
        }
    }

    public function test_instalasi_menolak_nama_database_tidak_valid(): void
    {
        DB::purge('sqlite');
        config()->set('database.default', 'mysql');
        config()->set('database.connections.mysql.database', 'bad;name');
        config()->set('database.connections.mysql.host', '127.0.0.1');
        config()->set('database.connections.mysql.port', '3306');
        config()->set('database.connections.mysql.username', 'root');
        config()->set('database.connections.mysql.password', '');

        $this->artisan('siils:install')
            ->assertFailed()
            ->expectsOutputToContain('Nama database "bad;name" tidak valid');
    }

    public function test_statement_create_database_memakai_identifier_backtick(): void
    {
        $this->assertSame(
            'CREATE DATABASE IF NOT EXISTS `siils` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
            SiilsInstall::createDatabaseStatement('siils')
        );
    }
}
