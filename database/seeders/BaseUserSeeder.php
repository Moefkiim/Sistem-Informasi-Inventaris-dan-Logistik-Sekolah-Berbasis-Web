<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Akun dasar yang wajib ada agar aplikasi bisa dipakai (login tiap role).
 * Idempoten: aman dijalankan berulang tanpa menggandakan baris.
 */
class BaseUserSeeder extends Seeder
{
    /**
     * @var array<int,array{username:string,name:string,email:string,role:string,department:?string,is_active:bool}>
     */
    private const ACCOUNTS = [
        [
            'username' => 'kajur_rpl',
            'name' => 'Budi Santoso, S.Kom (Kajur RPL)',
            'email' => 'kajur.rpl@sekolah.sch.id',
            'role' => 'kajur',
            'department' => 'RPL',
            'is_active' => true,
        ],
        [
            'username' => 'sarpras',
            'name' => 'Staf Sarana dan Prasarana',
            'email' => 'sarpras@sekolah.sch.id',
            'role' => 'sarpras',
            'department' => null,
            'is_active' => true,
        ],
        [
            'username' => 'kepsek',
            'name' => 'Drs. H. Mulyadi, M.Pd (Kepala Sekolah)',
            'email' => 'kepsek@sekolah.sch.id',
            'role' => 'kepala_sekolah',
            'department' => null,
            'is_active' => true,
        ],
        [
            'username' => 'user_nonaktif',
            'name' => 'User Nonaktif',
            'email' => 'nonaktif@sekolah.sch.id',
            'role' => 'kajur',
            'department' => 'TKJ',
            'is_active' => false,
        ],
    ];

    public function run(): void
    {
        foreach (self::ACCOUNTS as $account) {
            $user = User::firstOrNew(['username' => $account['username']]);

            // Field role/is_active tidak mass-assignable; set eksplisit.
            $user->name = $account['name'];
            $user->email = $account['email'];
            $user->password = 'password123';
            $user->department = $account['department'];
            $user->role = $account['role'];
            $user->is_active = $account['is_active'];
            $user->save();
        }
    }
}
