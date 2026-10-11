<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function sarpras(): User
    {
        return User::factory()->sarpras()->create();
    }

    public function test_validasi_password_akun_baru_minimal_8_huruf_dan_angka(): void
    {
        $response = $this->actingAs($this->sarpras())->post(route('sarpras.users.store'), [
            'name' => 'Kajur Baru',
            'username' => 'kajur_baru',
            'email' => 'kajur_baru@example.com',
            'password' => 'Abc1',
            'role' => 'kajur',
            'department' => 'RPL',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users', ['username' => 'kajur_baru']);
    }

    public function test_password_akun_baru_wajib_memuat_angka(): void
    {
        $this->actingAs($this->sarpras())->post(route('sarpras.users.store'), [
            'name' => 'Kajur Baru',
            'username' => 'kajur_baru',
            'email' => 'kajur_baru@example.com',
            'password' => 'HanyaHuruf',
            'role' => 'kajur',
            'department' => 'RPL',
        ])->assertSessionHasErrors('password');
    }

    public function test_akun_baru_ditandai_wajib_ganti_kata_sandi(): void
    {
        $this->actingAs($this->sarpras())->post(route('sarpras.users.store'), [
            'name' => 'Kajur Baru',
            'username' => 'kajur_baru',
            'email' => 'kajur_baru@example.com',
            'password' => 'Rahasia123',
            'role' => 'kajur',
            'department' => 'RPL',
        ])->assertSessionHasNoErrors();

        $user = User::where('username', 'kajur_baru')->firstOrFail();
        $this->assertTrue((bool) $user->must_change_password);
        $this->assertTrue(Hash::check('Rahasia123', $user->password));
    }

    public function test_sarpras_dapat_mengubah_nama_dan_jurusan_kajur(): void
    {
        $kajur = User::factory()->kajur()->create(['name' => 'Nama Lama', 'department' => 'RPL']);

        $this->actingAs($this->sarpras())
            ->put(route('sarpras.users.update', $kajur), [
                'name' => 'Nama Baru',
                'department' => 'TKJ',
            ])
            ->assertSessionHasNoErrors();

        $kajur->refresh();
        $this->assertSame('Nama Baru', $kajur->name);
        $this->assertSame('TKJ', $kajur->department);
    }

    public function test_sarpras_tidak_dapat_mengubah_kepala_sekolah_atau_sarpras_lain(): void
    {
        $actor = $this->sarpras();
        $kepsek = User::factory()->kepalaSekolah()->create();
        $lain = User::factory()->sarpras()->create();

        $this->actingAs($actor)
            ->put(route('sarpras.users.update', $kepsek), ['name' => 'Diubah'])
            ->assertSessionHasErrors();

        $this->actingAs($actor)
            ->put(route('sarpras.users.update', $lain), ['name' => 'Diubah'])
            ->assertSessionHasErrors();

        $this->assertNotSame('Diubah', $kepsek->fresh()->name);
    }

    public function test_reset_sandi_menghasilkan_kata_sandi_sementara_dan_memaksa_ganti(): void
    {
        $kajur = User::factory()->kajur()->create(['must_change_password' => false]);

        $response = $this->actingAs($this->sarpras())
            ->post(route('sarpras.users.reset_password', $kajur));

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('temporary_password');

        $temporary = $response->getSession()->get('temporary_password')['password'];

        $kajur->refresh();
        $this->assertTrue((bool) $kajur->must_change_password);
        $this->assertTrue(Hash::check($temporary, $kajur->password));
        $this->assertGreaterThanOrEqual(8, strlen($temporary));
    }

    public function test_kata_sandi_sementara_tidak_dicatat_dalam_log(): void
    {
        $kajur = User::factory()->kajur()->create();

        $response = $this->actingAs($this->sarpras())
            ->post(route('sarpras.users.reset_password', $kajur));

        $temporary = $response->getSession()->get('temporary_password')['password'];

        $log = ActivityLog::where('action', 'user_password_reset')->firstOrFail();
        $this->assertStringNotContainsString($temporary, (string) $log->description);
        $this->assertNull($log->old_values);
        $this->assertNull($log->new_values);
    }

    public function test_sarpras_tidak_dapat_mereset_kepala_sekolah_atau_sarpras_lain(): void
    {
        $actor = $this->sarpras();
        $kepsek = User::factory()->kepalaSekolah()->create();
        $lain = User::factory()->sarpras()->create();

        $this->actingAs($actor)
            ->post(route('sarpras.users.reset_password', $kepsek))
            ->assertSessionHasErrors();

        $this->actingAs($actor)
            ->post(route('sarpras.users.reset_password', $lain))
            ->assertSessionHasErrors();

        $this->assertFalse((bool) $kepsek->fresh()->must_change_password);
    }

    public function test_perintah_siils_reset_password_gagal_bila_username_tidak_ada(): void
    {
        $this->artisan('siils:reset-password', ['username' => 'tidak_ada'])
            ->assertFailed();
    }

    public function test_perintah_siils_reset_password_memaksa_ganti(): void
    {
        $kajur = User::factory()->kajur()->create([
            'username' => 'kajur_lupa',
            'must_change_password' => false,
        ]);

        $this->artisan('siils:reset-password', ['username' => 'kajur_lupa'])
            ->assertSuccessful();

        $this->assertTrue((bool) $kajur->fresh()->must_change_password);
        $this->assertTrue(ActivityLog::where('action', 'user_password_reset')->exists());
    }
}
