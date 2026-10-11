<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SessionHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function useDatabaseSessionDriver(): void
    {
        config()->set('session.driver', 'database');
        config()->set('session.encrypt', false);
    }

    private function makeUser(array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'password' => Hash::make('password123'),
            'must_change_password' => false,
        ], $attrs));
    }

    private function insertSessionRow(User $user, string $id = 'sesi-lama-xyz'): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Uji Otomatis',
            'payload' => base64_encode(json_encode(['_token' => str_repeat('a', 40)])),
            'last_activity' => now()->timestamp,
        ]);
    }

    private function sessionCookieFromResponse(TestResponse $response): string
    {
        $name = config('session.cookie', 'laravel_session');

        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $name) {
                return (string) $cookie->getValue();
            }
        }

        $this->fail("Respons tidak mengirim cookie sesi '{$name}'.");
    }

    public function test_sesi_lama_tidak_berlaku_setelah_ganti_kata_sandi(): void
    {
        $this->useDatabaseSessionDriver();
        $user = $this->makeUser(['role' => 'kajur']);

        // Sesi lain yang sudah eksis sebelum kata sandi diganti.
        $this->insertSessionRow($user, 'sesi-a');
        $this->insertSessionRow($user, 'sesi-b');

        $this->actingAs($user)->put('/profile/password', [
            'current_password' => 'password123',
            'password' => 'BaruSekali9',
            'password_confirmation' => 'BaruSekali9',
        ])->assertRedirect();

        $this->assertDatabaseMissing('sessions', ['id' => 'sesi-a']);
        $this->assertDatabaseMissing('sessions', ['id' => 'sesi-b']);
        $this->assertSame(1, DB::table('sessions')->where('user_id', $user->id)->count());
    }

    public function test_sesi_lama_dihapus_setelah_reset_sandi_dari_antarmuka(): void
    {
        $this->useDatabaseSessionDriver();
        $sarpras = User::factory()->sarpras()->create();
        $kajur = $this->makeUser(['role' => 'kajur']);

        $this->insertSessionRow($kajur, 'sesi-1');
        $this->insertSessionRow($kajur, 'sesi-2');

        $this->actingAs($sarpras)
            ->post(route('sarpras.users.reset_password', $kajur))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, DB::table('sessions')->where('user_id', $kajur->id)->count());
    }

    public function test_kata_sandi_sementara_tidak_tersisa_di_payload_sesi_setelah_halaman_dibaca(): void
    {
        $this->useDatabaseSessionDriver();
        $sarpras = User::factory()->sarpras()->create();
        $kajur = $this->makeUser(['role' => 'kajur']);

        $response = $this->actingAs($sarpras)
            ->post(route('sarpras.users.reset_password', $kajur))
            ->assertSessionHas('temporary_password');

        $sessionId = $response->getSession()->getId();
        $sessionCookie = $this->sessionCookieFromResponse($response);

        $this->withUnencryptedCookie(config('session.cookie', 'laravel_session'), $sessionCookie);
        $this->actingAs($sarpras)
            ->get(route('sarpras.users.index'))
            ->assertOk();

        $row = DB::table('sessions')->where('id', $sessionId)->first();
        $this->assertNotNull($row);

        $payload = (string) base64_decode((string) $row->payload);

        $this->assertStringNotContainsString('"temporary_password":', $payload);
        $this->assertStringNotContainsString('"password":', $payload);
    }

    public function test_kata_sandi_sementara_tidak_tercatat_di_log_aplikasi(): void
    {
        $sarpras = User::factory()->sarpras()->create();
        $kajur = $this->makeUser(['role' => 'kajur']);

        $logPath = storage_path('logs/laravel.log');
        @unlink($logPath);

        $response = $this->actingAs($sarpras)
            ->post(route('sarpras.users.reset_password', $kajur));

        $temporary = $response->getSession()->get('temporary_password')['password'];

        if (file_exists($logPath)) {
            $this->assertStringNotContainsString($temporary, (string) file_get_contents($logPath));
        } else {
            $this->assertFileDoesNotExist($logPath);
        }
    }

    public function test_activity_log_reset_tidak_memuat_kata_sandi_sementara(): void
    {
        $sarpras = User::factory()->sarpras()->create();
        $kajur = $this->makeUser(['role' => 'kajur']);

        $response = $this->actingAs($sarpras)
            ->post(route('sarpras.users.reset_password', $kajur));

        $temporary = $response->getSession()->get('temporary_password')['password'];

        $logs = ActivityLog::where('action', 'user_password_reset')->get();
        $this->assertNotEmpty($logs);
        foreach ($logs as $log) {
            $this->assertStringNotContainsString($temporary, (string) $log->description);
        }
    }
}
