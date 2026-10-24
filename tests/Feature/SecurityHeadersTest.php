<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_header_keamanan_dasar_ada_pada_halaman_login(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    }

    public function test_header_keamanan_dasar_ada_pada_halaman_tiap_peran(): void
    {
        $roles = [
            'sarpras' => User::factory()->sarpras()->create(),
            'kajur' => User::factory()->kajur()->create(),
            'kepala_sekolah' => User::factory()->kepalaSekolah()->create(),
        ];

        foreach ($roles as $role => $user) {
            $response = $this->actingAs($user)->get(route('home'));

            $response->assertOk();
            $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
            $response->assertHeader('X-Content-Type-Options', 'nosniff');
            $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
            $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        }
    }

    public function test_halaman_terautentikasi_memiliki_cache_control_no_store(): void
    {
        $user = User::factory()->sarpras()->create();

        $response = $this->actingAs($user)->get(route('home'));

        $response->assertOk();
        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('private', $cacheControl);
        $response->assertHeader('Pragma', 'no-cache');
    }

    public function test_halaman_tamu_tidak_memiliki_cache_control_no_store(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringNotContainsString('no-store', $cacheControl);
    }

    public function test_hsts_tidak_dikirim_pada_koneksi_http(): void
    {
        config(['siils.hsts' => true]);

        $response = $this->get(route('login'));

        $response->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_hsts_tidak_dikirim_jika_konfigurasi_hsts_nonaktif_meskipun_https(): void
    {
        config(['siils.hsts' => false]);

        $response = $this->get(secure_url('/login'));

        $response->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_hsts_dikirim_pada_koneksi_https_jika_konfigurasi_hsts_aktif(): void
    {
        config(['siils.hsts' => true]);

        $response = $this->get(secure_url('/login'));

        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_durasi_ingat_saya_dibatasi_sesuai_konfigurasi(): void
    {
        config(['siils.remember_me_days' => 7]);

        $user = User::factory()->create([
            'username' => 'testuser',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);

        $response = $this->post(route('login'), [
            'login' => 'testuser',
            'password' => 'password123',
            'remember' => '1',
        ]);

        $response->assertRedirect(route('home'));

        $guard = Auth::guard('web');
        $ref = new \ReflectionProperty($guard, 'rememberDuration');
        $durationMinutes = $ref->getValue($guard);

        $this->assertSame(7 * 24 * 60, $durationMinutes);

        // Periksa expiry cookie remember_web
        $cookies = $response->headers->getCookies();
        $rememberCookie = null;
        foreach ($cookies as $cookie) {
            if (str_starts_with($cookie->getName(), 'remember_web_')) {
                $rememberCookie = $cookie;
                break;
            }
        }

        $this->assertNotNull($rememberCookie, 'Cookie remember_web tidak ditemukan pada respons');
        // Toleransi delta 120 detik untuk masa berlaku cookie (7 hari dalam detik = 604800)
        $expectedMaxAge = 7 * 24 * 3600;
        $actualMaxAge = $rememberCookie->getExpiresTime() - time();
        $this->assertEqualsWithDelta($expectedMaxAge, $actualMaxAge, 120);
    }

    public function test_logout_membatalkan_token_ingat_saya(): void
    {
        $user = User::factory()->create([
            'remember_token' => 'old_remember_token_value',
        ]);

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect(route('login'));

        $user->refresh();
        $this->assertNotEquals('old_remember_token_value', $user->remember_token);
    }

    public function test_siils_check_memberi_peringatan_bila_app_url_https_tetapi_session_secure_cookie_false(): void
    {
        config([
            'app.url' => 'https://siils.sekolah.sch.id',
            'session.secure' => false,
        ]);

        $this->artisan('siils:check')
            ->expectsOutputToContain('APP_URL menggunakan HTTPS tetapi SESSION_SECURE_COOKIE belum aktif');
    }

    public function test_siils_check_tidak_memberi_peringatan_bila_app_url_https_dan_session_secure_cookie_true(): void
    {
        config([
            'app.url' => 'https://siils.sekolah.sch.id',
            'session.secure' => true,
        ]);

        $this->artisan('siils:check')
            ->expectsOutputToContain('SESSION_SECURE_COOKIE aktif untuk HTTPS');
    }
}
