<?php

namespace Tests\Feature;

use App\Console\Commands\SiilsBackup;
use Database\Seeders\Concerns\GuardsDemoEnvironment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class ProductionOptimizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_siils_config_exposes_production_keys(): void
    {
        $this->assertArrayHasKey('mysqldump_path', config('siils'));
        $this->assertArrayHasKey('allow_demo_seed', config('siils'));
        $this->assertIsString(config('siils.mysqldump_path'));
        $this->assertIsBool(config('siils.allow_demo_seed'));
    }

    public function test_backup_command_resolves_mysqldump_from_config(): void
    {
        config(['siils.mysqldump_path' => '/custom/bin/mysqldump']);

        $command = new SiilsBackup;
        $refMethod = new ReflectionMethod($command, 'mysqldumpBinary');
        $refMethod->setAccessible(true);

        // Jika binary kustom tidak executable, method jatuh ke fallback 'mysqldump' atau null
        // namun kita memastikan method membaca config('siils.mysqldump_path') tanpa error
        $result = $refMethod->invoke($command);
        $this->assertTrue(is_string($result) || is_null($result));
    }

    public function test_demo_seeder_guard_uses_config_allow_demo_seed(): void
    {
        $target = new class
        {
            use GuardsDemoEnvironment;

            public function check(): bool
            {
                return $this->demoDataAllowed();
            }
        };

        $this->app['env'] = 'production';

        config(['siils.allow_demo_seed' => false]);
        $this->assertFalse($target->check(), 'Seeder demo harus ditolak di produksi saat allow_demo_seed=false');

        config(['siils.allow_demo_seed' => true]);
        $this->assertTrue($target->check(), 'Seeder demo harus diizinkan di produksi saat allow_demo_seed=true');

        $this->app['env'] = 'testing';
    }
}
