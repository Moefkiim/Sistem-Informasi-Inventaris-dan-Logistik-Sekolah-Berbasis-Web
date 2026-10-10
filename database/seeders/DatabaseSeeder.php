<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Urutan seeding: akun dasar (selalu) lalu data demo (kecuali produksi).
     */
    public function run(): void
    {
        $this->call(BaseUserSeeder::class);

        if (App::environment('production') && ! filter_var(env('SIILS_ALLOW_DEMO_SEED', false), FILTER_VALIDATE_BOOL)) {
            $this->command?->warn('Data demo dilewati: APP_ENV=production. Set SIILS_ALLOW_DEMO_SEED=true bila memang ingin data demo.');

            return;
        }

        $this->call([
            DemoMasterSeeder::class,
            DemoInventorySeeder::class,
            DemoLogisticsSeeder::class,
            DemoWorkflowSeeder::class,
        ]);
    }
}
