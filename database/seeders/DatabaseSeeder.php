<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Akun Kajur (dengan relasi jurusan)
        User::updateOrCreate(
            ['username' => 'kajur_rpl'],
            [
                'name' => 'Budi Santoso, S.Kom (Kajur RPL)',
                'email' => 'kajur.rpl@sekolah.sch.id',
                'password' => 'password123',
                'role' => 'kajur',
                'department' => 'Rekayasa Perangkat Lunak',
                'is_active' => true,
            ]
        );

        // 2. Akun Sarpras
        User::updateOrCreate(
            ['username' => 'sarpras'],
            [
                'name' => 'Staf Sarana dan Prasarana',
                'email' => 'sarpras@sekolah.sch.id',
                'password' => 'password123',
                'role' => 'sarpras',
                'department' => null,
                'is_active' => true,
            ]
        );

        // 3. Akun Kepala Sekolah
        User::updateOrCreate(
            ['username' => 'kepsek'],
            [
                'name' => 'Drs. H. Mulyadi, M.Pd (Kepala Sekolah)',
                'email' => 'kepsek@sekolah.sch.id',
                'password' => 'password123',
                'role' => 'kepala_sekolah',
                'department' => null,
                'is_active' => true,
            ]
        );

        // 4. Akun Nonaktif untuk pengujian pencegahan login
        User::updateOrCreate(
            ['username' => 'user_nonaktif'],
            [
                'name' => 'User Nonaktif',
                'email' => 'nonaktif@sekolah.sch.id',
                'password' => 'password123',
                'role' => 'kajur',
                'department' => 'TKJ',
                'is_active' => false,
            ]
        );
    }
}
