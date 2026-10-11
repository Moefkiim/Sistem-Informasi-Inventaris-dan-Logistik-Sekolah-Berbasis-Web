<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CustomErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/test-error/{code}', function (int $code) {
            abort($code, "Pesan khusus error {$code}");
        });

        Route::post('/test-token-mismatch', function () {
            throw new TokenMismatchException('Token expired');
        });

        Route::post('/test-throttle-generic', function () {
            throw new ThrottleRequestsException('Too Many Attempts.', null, ['Retry-After' => '45']);
        });
    }

    public function test_403_renders_custom_view_with_exception_message(): void
    {
        $response = $this->get('/test-error/403');

        $response->assertStatus(403);
        $response->assertSee('Akses Ditolak');
        $response->assertSee('Pesan khusus error 403');
    }

    public function test_404_renders_custom_view_with_back_button(): void
    {
        $response = $this->get('/halaman-yang-pasti-tidak-ada-12345');

        $response->assertStatus(404);
        $response->assertSee('Halaman Tidak Ditemukan');
        $response->assertSee('Kembali');
    }

    public function test_419_renders_custom_view(): void
    {
        $response = $this->get('/test-error/419');

        $response->assertStatus(419);
        $response->assertSee('Sesi Berakhir');
    }

    public function test_429_renders_custom_view_for_generic_routes(): void
    {
        $response = $this->get('/test-error/429');

        $response->assertStatus(429);
        $response->assertSee('Terlalu Banyak Permintaan');
    }

    public function test_500_renders_custom_view_with_back_button(): void
    {
        $response = $this->get('/test-error/500');

        $response->assertStatus(500);
        $response->assertSee('Terjadi Kendala Teknis');
        $response->assertSee('Kembali');
    }

    public function test_503_renders_custom_view(): void
    {
        $response = $this->get('/test-error/503');

        $response->assertStatus(503);
        $response->assertSee('Sistem Dalam Pemeliharaan');
    }

    public function test_wrong_role_triggers_custom_403_with_middleware_message(): void
    {
        $kajur = User::factory()->kajur()->create();

        $response = $this->actingAs($kajur)->get(route('sarpras.inventory.index'));

        $response->assertStatus(403);
        $response->assertSee('Akses ditolak: Anda tidak memiliki wewenang untuk membuka halaman ini.');
        $response->assertSee('Kembali ke Dashboard');
    }

    public function test_session_expired_or_csrf_mismatch_redirects_to_login_with_message(): void
    {
        $response = $this->from('/halaman-sebelumnya')
            ->followingRedirects()
            ->post('/test-token-mismatch');

        $response->assertSee('Sesi Anda berakhir, silakan masuk lagi.');
    }

    public function test_login_throttle_displays_friendly_message_on_login_page(): void
    {
        // Pastikan tidak ada sesi auth aktif
        auth()->logout();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login'), [
                'login' => 'user_throttle_test',
                'password' => 'wrong_password',
            ]);
        }

        // Percobaan ke-6 harus ditolak oleh middleware throttle:5,1 dan dialihkan kembali ke login
        $response = $this->followingRedirects()->post(route('login'), [
            'login' => 'user_throttle_test',
            'password' => 'wrong_password',
        ]);

        $response->assertSee('Terlalu banyak percobaan. Coba lagi dalam');
    }
}
