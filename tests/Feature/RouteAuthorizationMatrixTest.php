<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\Support\RouteAccessMatrix;
use Tests\TestCase;

class RouteAuthorizationMatrixTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Pastikan semua route yang terdaftar di aplikasi memiliki entri di RouteAccessMatrix.
     */
    public function test_seluruh_route_terdaftar_memiliki_entri_pada_matriks_otorisasi(): void
    {
        $unregistered = [];

        foreach (RouteFacade::getRoutes() as $route) {
            $access = RouteAccessMatrix::getAccessForRoute($route);

            if ($access === null) {
                $unregistered[] = sprintf(
                    '%s [%s] %s',
                    $route->getName() ?: '(tanpa nama)',
                    implode('|', $route->methods()),
                    $route->uri()
                );
            }
        }

        $this->assertEmpty(
            $unregistered,
            "Route berikut belum didaftarkan di RouteAccessMatrix:\n".implode("\n", $unregistered)
        );
    }

    /**
     * Pastikan middleware yang terpasang pada route sesuai dengan deklarasi matriks otorisasi.
     */
    public function test_middleware_route_sesuai_dengan_matriks_otorisasi(): void
    {
        foreach (RouteFacade::getRoutes() as $route) {
            $name = $route->getName() ?: $route->uri();
            $access = RouteAccessMatrix::getAccessForRoute($route);
            $middleware = $route->gatherMiddleware();

            if ($access === 'guest') {
                $this->assertNotContains(
                    'auth',
                    $middleware,
                    "Route guest '{$name}' tidak boleh memuat middleware 'auth'."
                );
            } elseif ($access === 'any-auth') {
                $this->assertContains(
                    'auth',
                    $middleware,
                    "Route '{$name}' harus memuat middleware 'auth'."
                );
                $this->assertContains(
                    'role',
                    $middleware,
                    "Route '{$name}' harus memuat middleware 'role'."
                );
                $this->assertContains(
                    'password.changed',
                    $middleware,
                    "Route '{$name}' harus memuat middleware 'password.changed'."
                );

                // Tidak boleh membatasi ke satu role tertentu
                foreach (['role:kajur', 'role:sarpras', 'role:kepala_sekolah'] as $roleMiddleware) {
                    $this->assertNotContains(
                        $roleMiddleware,
                        $middleware,
                        "Route bersama '{$name}' tidak boleh dibatasi oleh '{$roleMiddleware}'."
                    );
                }
            } elseif (is_array($access)) {
                $this->assertContains(
                    'auth',
                    $middleware,
                    "Route role '{$name}' harus memuat middleware 'auth'."
                );
                $this->assertContains(
                    'role',
                    $middleware,
                    "Route role '{$name}' harus memuat middleware 'role'."
                );

                $expectedRole = $access[0];
                $this->assertContains(
                    "role:{$expectedRole}",
                    $middleware,
                    "Route role '{$name}' harus memuat middleware 'role:{$expectedRole}'."
                );
            }
        }
    }

    /**
     * Menguji rute GET peranan terhadap hak akses, proteksi guest, proteksi user nonaktif,
     * dan pengalihan must_change_password.
     */
    public function test_otorisasi_akses_rute_get_peran_dan_tamu(): void
    {
        $sarpras = User::factory()->sarpras()->create([
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $kajur = User::factory()->kajur()->create([
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $kepalaSekolah = User::factory()->kepalaSekolah()->create([
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $usersByRole = [
            'sarpras' => $sarpras,
            'kajur' => $kajur,
            'kepala_sekolah' => $kepalaSekolah,
        ];

        // Sampel representatif seluruh rute GET tanpa parameter
        $routesToTest = [
            // Rute umum terautentikasi
            'home' => 'any-auth',
            'profile.show' => 'any-auth',
            'notifications.index' => 'any-auth',
            'reports.index' => 'any-auth',
            'documents.index' => 'any-auth',

            // Area Kajur
            'kajur.submissions.index' => ['kajur'],
            'kajur.submissions.create' => ['kajur'],
            'kajur.inventory.index' => ['kajur'],
            'kajur.area' => ['kajur'],

            // Area Sarpras
            'sarpras.inventory.index' => ['sarpras'],
            'sarpras.inventory.create' => ['sarpras'],
            'sarpras.inventory.import.create' => ['sarpras'],
            'sarpras.inventory.import.template' => ['sarpras'],
            'sarpras.submissions.index' => ['sarpras'],
            'sarpras.realizations.index' => ['sarpras'],
            'sarpras.logistics.incoming' => ['sarpras'],
            'sarpras.logistics.outgoing' => ['sarpras'],
            'sarpras.logistics.distributions' => ['sarpras'],
            'sarpras.locations.index' => ['sarpras'],
            'sarpras.users.index' => ['sarpras'],
            'sarpras.loans.index' => ['sarpras'],
            'sarpras.loans.create' => ['sarpras'],
            'sarpras.activity_logs.index' => ['sarpras'],
            'sarpras.area' => ['sarpras'],

            // Area Kepala Sekolah
            'kepala_sekolah.approval.index' => ['kepala_sekolah'],
            'kepala_sekolah.area' => ['kepala_sekolah'],
        ];

        foreach ($routesToTest as $routeName => $allowed) {
            $url = route($routeName);

            // Pastikan keadaan bersih sebagai Guest
            auth()->logout();
            $this->flushSession();

            // 1. Guest harus dialihkan ke login
            $guestResponse = $this->get($url);
            $this->assertTrue(
                $guestResponse->isRedirect(route('login')),
                "Route '{$routeName}' ({$url}) diakses guest menghasilkan status {$guestResponse->getStatusCode()} bukan redirect ke login."
            );

            // 2. Akses peranan
            foreach ($usersByRole as $roleName => $user) {
                $response = $this->actingAs($user)->get($url);

                if ($allowed === 'any-auth' || (is_array($allowed) && in_array($roleName, $allowed, true))) {
                    $this->assertNotEquals(
                        403,
                        $response->getStatusCode(),
                        "User role '{$roleName}' harusnya diizinkan mengakses '{$routeName}', tetapi mendapat 403."
                    );
                } else {
                    $this->assertEquals(
                        403,
                        $response->getStatusCode(),
                        "User role '{$roleName}' harusnya DITOLAK (403) saat mengakses '{$routeName}'."
                    );
                }
            }
        }
    }

    /**
     * Pengguna yang nonaktif harus ditolak dan dialihkan ke login.
     */
    public function test_pengguna_nonaktif_diblokir_pada_seluruh_rute_terautentikasi(): void
    {
        $inactiveUser = User::factory()->sarpras()->create([
            'is_active' => false,
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($inactiveUser)->get(route('home'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    /**
     * Pengguna dengan must_change_password harus dialihkan ke halaman ubah kata sandi
     * saat mengakses rute terautentikasi selain halaman profil.
     */
    public function test_pengguna_must_change_password_dialihkan_ke_halaman_ganti_kata_sandi(): void
    {
        $user = User::factory()->sarpras()->create([
            'is_active' => true,
            'must_change_password' => true,
        ]);

        $response = $this->actingAs($user)->get(route('home'));

        $response->assertRedirect(route('profile.show'));
    }
}
