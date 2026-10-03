<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPrivilegeTest extends TestCase
{
    use RefreshDatabase;

    public function test_sarpras_cannot_create_kepala_sekolah(): void
    {
        $sarpras = User::factory()->sarpras()->create();

        $this->actingAs($sarpras)
            ->post(route('sarpras.users.store'), [
                'name' => 'Kepsek Baru',
                'username' => 'kepsek_baru',
                'email' => 'kepsek@example.com',
                'password' => 'password123',
                'role' => 'kepala_sekolah',
                'department' => null,
            ])
            ->assertSessionHasErrors(['role']);
    }

    public function test_sarpras_cannot_toggle_kepala_sekolah(): void
    {
        $sarpras = User::factory()->sarpras()->create();
        $kepsek = User::factory()->state(['role' => 'kepala_sekolah', 'department' => null, 'is_active' => true])->create();

        $this->actingAs($sarpras)
            ->post(route('sarpras.users.toggle', $kepsek))
            ->assertSessionHasErrors();
    }

    public function test_sarpras_cannot_toggle_self(): void
    {
        $sarpras = User::factory()->sarpras()->create();

        $this->actingAs($sarpras)
            ->post(route('sarpras.users.toggle', $sarpras))
            ->assertSessionHasErrors();
    }
}
