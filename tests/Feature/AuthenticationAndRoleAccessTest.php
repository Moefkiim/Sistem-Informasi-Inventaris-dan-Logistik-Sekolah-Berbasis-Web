<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationAndRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('Inventaris & Logistik Sekolah', false);
    }

    public function test_user_can_login_using_email(): void
    {
        $user = User::factory()->create([
            'email' => 'kajur@sekolah.sch.id',
            'username' => 'kajur_user',
            'password' => 'secret123',
            'role' => 'kajur',
            'is_active' => true,
        ]);

        $response = $this->post('/login', [
            'login' => 'kajur@sekolah.sch.id',
            'password' => 'secret123',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('home'));
    }

    public function test_user_can_login_using_username(): void
    {
        $user = User::factory()->create([
            'email' => 'sarpras@sekolah.sch.id',
            'username' => 'sarpras_operator',
            'password' => 'secret123',
            'role' => 'sarpras',
            'is_active' => true,
        ]);

        $response = $this->post('/login', [
            'login' => 'sarpras_operator',
            'password' => 'secret123',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('home'));
    }

    public function test_user_cannot_login_with_invalid_password(): void
    {
        User::factory()->create([
            'email' => 'kepsek@sekolah.sch.id',
            'username' => 'kepsek_user',
            'password' => 'secret123',
            'role' => 'kepala_sekolah',
            'is_active' => true,
        ]);

        $response = $this->from('/login')->post('/login', [
            'login' => 'kepsek_user',
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('login');
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'nonaktif@sekolah.sch.id',
            'username' => 'user_nonaktif',
            'password' => 'secret123',
            'role' => 'kajur',
            'is_active' => false,
        ]);

        $response = $this->from('/login')->post('/login', [
            'login' => 'user_nonaktif',
            'password' => 'secret123',
        ]);

        $this->assertGuest();
        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('login');
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create([
            'username' => 'kajur_logout',
            'is_active' => true,
        ]);

        $this->actingAs($user);
        $this->assertAuthenticated();

        $response = $this->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/login');
    }

    public function test_unauthenticated_user_cannot_access_protected_routes(): void
    {
        $this->get('/home')->assertRedirect('/login');
        $this->get('/kajur/area')->assertRedirect('/login');
        $this->get('/sarpras/area')->assertRedirect('/login');
        $this->get('/kepala-sekolah/area')->assertRedirect('/login');
    }

    public function test_kajur_can_access_kajur_area_but_forbidden_from_other_roles(): void
    {
        $kajur = User::factory()->create([
            'username' => 'kajur_auth',
            'role' => 'kajur',
            'department' => 'RPL',
            'is_active' => true,
        ]);

        $this->actingAs($kajur)
            ->get('/kajur/area')
            ->assertRedirect(route('kajur.submissions.index'));

        $this->actingAs($kajur)
            ->get('/sarpras/area')
            ->assertStatus(403);

        $this->actingAs($kajur)
            ->get('/kepala-sekolah/area')
            ->assertStatus(403);
    }

    public function test_sarpras_can_access_sarpras_area_but_forbidden_from_other_roles(): void
    {
        $sarpras = User::factory()->create([
            'username' => 'sarpras_auth',
            'role' => 'sarpras',
            'is_active' => true,
        ]);

        $this->actingAs($sarpras)
            ->get('/sarpras/area')
            ->assertRedirect(route('sarpras.inventory.index'));

        $this->actingAs($sarpras)
            ->get('/kajur/area')
            ->assertStatus(403);

        $this->actingAs($sarpras)
            ->get('/kepala-sekolah/area')
            ->assertStatus(403);
    }

    public function test_kepala_sekolah_can_access_kepala_sekolah_area_but_forbidden_from_other_roles(): void
    {
        $kepsek = User::factory()->create([
            'username' => 'kepsek_auth',
            'role' => 'kepala_sekolah',
            'is_active' => true,
        ]);

        $this->actingAs($kepsek)
            ->get('/kepala-sekolah/area')
            ->assertRedirect(route('kepala_sekolah.approval.index'));

        $this->actingAs($kepsek)
            ->get('/kajur/area')
            ->assertStatus(403);

        $this->actingAs($kepsek)
            ->get('/sarpras/area')
            ->assertStatus(403);
    }
}
