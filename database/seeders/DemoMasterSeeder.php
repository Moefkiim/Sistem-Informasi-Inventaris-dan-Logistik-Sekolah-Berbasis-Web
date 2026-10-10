<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\User;
use Database\Seeders\Concerns\GuardsDemoEnvironment;
use Illuminate\Database\Seeder;

/**
 * Data induk demo: akun Kajur jurusan kedua dan daftar lokasi nyata sekolah.
 */
class DemoMasterSeeder extends Seeder
{
    use GuardsDemoEnvironment;

    /**
     * @var array<int,array{code:string,name:string,building:string,department:?string,description:?string}>
     */
    private const LOCATIONS = [
        ['code' => 'LAB-RPL-01', 'name' => 'Laboratorium RPL', 'building' => 'Gedung A', 'department' => 'RPL', 'description' => 'Lab komputer jurusan RPL'],
        ['code' => 'LAB-TKJ-01', 'name' => 'Laboratorium TKJ', 'building' => 'Gedung A', 'department' => 'TKJ', 'description' => 'Lab jaringan jurusan TKJ'],
        ['code' => 'GDG-SARPRAS', 'name' => 'Gudang Sarpras', 'building' => 'Gedung C', 'department' => null, 'description' => 'Gudang penyimpanan barang'],
        ['code' => 'R-GURU', 'name' => 'Ruang Guru', 'building' => 'Gedung B', 'department' => null, 'description' => 'Ruang guru dan staf'],
        ['code' => 'R-TU', 'name' => 'Ruang Tata Usaha', 'building' => 'Gedung B', 'department' => null, 'description' => 'Ruang tata usaha'],
        ['code' => 'R-KELAS-RPL', 'name' => 'Ruang Kelas X RPL', 'building' => 'Gedung A', 'department' => 'RPL', 'description' => 'Ruang kelas jurusan RPL'],
    ];

    public function run(): void
    {
        if (! $this->demoDataAllowed()) {
            $this->command?->warn('DemoMasterSeeder dilewati: APP_ENV=production.');

            return;
        }

        // Kajur jurusan kedua untuk memperlihatkan isolasi antar jurusan.
        $kajurTkj = User::firstOrNew(['username' => 'kajur_tkj']);
        $kajurTkj->name = 'Siti Aminah, S.Kom (Kajur TKJ)';
        $kajurTkj->email = 'kajur.tkj@sekolah.sch.id';
        $kajurTkj->password = 'password123';
        $kajurTkj->department = 'TKJ';
        $kajurTkj->role = 'kajur';
        $kajurTkj->is_active = true;
        $kajurTkj->save();

        foreach (self::LOCATIONS as $location) {
            Location::firstOrCreate(
                ['code' => $location['code']],
                [
                    'name' => $location['name'],
                    'building' => $location['building'],
                    'department' => $location['department'],
                    'description' => $location['description'],
                ]
            );
        }
    }
}
