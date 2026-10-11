<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileAndPasswordTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'password' => Hash::make('password123'),
            'must_change_password' => false,
        ], $attrs));
    }

    public function test_profile_page_accessible_by_kajur(): void
    {
        $user = $this->makeUser(['role' => 'kajur']);
        $this->actingAs($user)->get('/profile')
            ->assertOk()
            ->assertSee('Informasi Akun');
    }

    public function test_profile_page_accessible_by_sarpras(): void
    {
        $user = $this->makeUser(['role' => 'sarpras']);
        $this->actingAs($user)->get('/profile')
            ->assertOk()
            ->assertSee('Informasi Akun');
    }

    public function test_profile_page_accessible_by_kepala_sekolah(): void
    {
        $user = $this->makeUser(['role' => 'kepala_sekolah']);
        $this->actingAs($user)->get('/profile')
            ->assertOk()
            ->assertSee('Informasi Akun');
    }

    public function test_profile_page_shows_must_change_password_warning_banner(): void
    {
        $user = $this->makeUser(['must_change_password' => true]);
        $this->actingAs($user)->get('/profile')
            ->assertOk()
            ->assertSee('Perubahan Kata Sandi Diwajibkan');
    }

    public function test_profile_page_not_accessible_by_guest(): void
    {
        $this->get('/profile')
            ->assertRedirect('/login');
    }

    public function test_password_change_fails_when_current_password_wrong(): void
    {
        $user = $this->makeUser();
        $response = $this->actingAs($user)->put('/profile/password', [
            'current_password'      => 'salah-sekali',
            'password'              => 'NewPass9x',
            'password_confirmation' => 'NewPass9x',
        ]);
        $response->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('password123', $user->fresh()->password));
    }

    public function test_password_change_fails_when_new_password_too_short(): void
    {
        $user = $this->makeUser();
        $response = $this->actingAs($user)->put('/profile/password', [
            'current_password'      => 'password123',
            'password'              => 'Abc1',
            'password_confirmation' => 'Abc1',
        ]);
        $response->assertSessionHasErrors('password');
    }

    public function test_password_change_fails_when_new_password_has_no_letters(): void
    {
        $user = $this->makeUser();
        $response = $this->actingAs($user)->put('/profile/password', [
            'current_password'      => 'password123',
            'password'              => '12345678',
            'password_confirmation' => '12345678',
        ]);
        $response->assertSessionHasErrors('password');
    }

    public function test_password_change_fails_when_new_password_has_no_numbers(): void
    {
        $user = $this->makeUser();
        $response = $this->actingAs($user)->put('/profile/password', [
            'current_password'      => 'password123',
            'password'              => 'OnlyLetters',
            'password_confirmation' => 'OnlyLetters',
        ]);
        $response->assertSessionHasErrors('password');
    }

    public function test_password_change_fails_when_confirmation_does_not_match(): void
    {
        $user = $this->makeUser();
        $response = $this->actingAs($user)->put('/profile/password', [
            'current_password'      => 'password123',
            'password'              => 'NewPass9x',
            'password_confirmation' => 'DifferentPass9x',
        ]);
        $response->assertSessionHasErrors('password');
    }

    public function test_password_change_fails_when_same_as_current(): void
    {
        $user = $this->makeUser();
        $response = $this->actingAs($user)->put('/profile/password', [
            'current_password'      => 'password123',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);
        $response->assertSessionHasErrors('password');
    }

    public function test_password_change_succeeds_with_valid_data(): void
    {
        $user = $this->makeUser();
        $response = $this->actingAs($user)->put('/profile/password', [
            'current_password'      => 'password123',
            'password'              => 'BaruBanget9',
            'password_confirmation' => 'BaruBanget9',
        ]);
        $response->assertRedirect(route('profile.show'));
        $response->assertSessionHas('success');
        $this->assertTrue(Hash::check('BaruBanget9', $user->fresh()->password));
    }

    public function test_must_change_password_flag_is_cleared_after_successful_change(): void
    {
        $user = $this->makeUser(['must_change_password' => true]);
        $this->actingAs($user)->put('/profile/password', [
            'current_password'      => 'password123',
            'password'              => 'BaruBanget9',
            'password_confirmation' => 'BaruBanget9',
        ]);
        $this->assertFalse((bool) $user->fresh()->must_change_password);
    }

    public function test_activity_log_recorded_without_password_after_change(): void
    {
        $user = $this->makeUser(['name' => 'Uji Coba Log', 'username' => 'ujicoba_log']);
        $this->actingAs($user)->put('/profile/password', [
            'current_password'      => 'password123',
            'password'              => 'BaruBanget9',
            'password_confirmation' => 'BaruBanget9',
        ]);
        // auditable_label menyimpan username (label parameter pada ActivityLog::log)
        $log = ActivityLog::where('action', 'user_password_changed')
            ->where('auditable_label', 'ujicoba_log')
            ->first();
        $this->assertNotNull($log, 'Activity log harus tercatat setelah ubah sandi');
        $this->assertStringNotContainsString('password123', $log->description ?? '');
        $this->assertStringNotContainsString('BaruBanget9', $log->description ?? '');
    }

    public function test_user_with_must_change_password_is_redirected_to_profile_on_other_routes(): void
    {
        $user = $this->makeUser(['role' => 'kajur', 'must_change_password' => true]);
        $this->actingAs($user)
            ->get('/home')
            ->assertRedirect(route('profile.show'));
    }

    public function test_user_with_must_change_password_can_access_profile_page(): void
    {
        $user = $this->makeUser(['role' => 'kajur', 'must_change_password' => true]);
        $this->actingAs($user)
            ->get('/profile')
            ->assertOk();
    }

    public function test_profile_link_visible_in_layout_for_authenticated_user(): void
    {
        $user = $this->makeUser(['role' => 'sarpras']);
        $this->actingAs($user)
            ->get('/profile')
            ->assertSee('Akun Saya');
    }
}
