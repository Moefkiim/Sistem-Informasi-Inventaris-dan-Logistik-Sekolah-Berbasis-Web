<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPrivilegeTest extends TestCase
{
    use RefreshDatabase;

    public function test_sarpras_cannot_create_kepala_sekolah_user(): void
    {
        $sarpras = User::factory()->create([
            'role' => 'sarpras',
            'is_active' => true,
        ]);

        $response = $this->actingAs($sarpras)->post(route('sarpras.users.store'), [
            'name' => 'Calon Kepala Sekolah',
            'username' => 'calon_kepsek',
            'email' => 'calon_kepsek@sekolah.sch.id',
            'password' => 'secret123',
            'role' => 'kepala_sekolah',
        ]);

        $response->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', [
            'username' => 'calon_kepsek',
        ]);
    }

    public function test_sarpras_must_provide_department_when_creating_kajur(): void
    {
        $sarpras = User::factory()->create([
            'role' => 'sarpras',
            'is_active' => true,
        ]);

        $response = $this->actingAs($sarpras)->post(route('sarpras.users.store'), [
            'name' => 'Kajur Baru',
            'username' => 'kajur_baru',
            'email' => 'kajur_baru@sekolah.sch.id',
            'password' => 'secret123',
            'role' => 'kajur',
            'department' => '',
        ]);

        $response->assertSessionHasErrors('department');
        $this->assertDatabaseMissing('users', [
            'username' => 'kajur_baru',
        ]);
    }

    public function test_sarpras_cannot_toggle_kepala_sekolah_or_sarpras_status(): void
    {
        $operatorSarpras = User::factory()->create([
            'role' => 'sarpras',
            'is_active' => true,
        ]);

        $kepalaSekolah = User::factory()->create([
            'role' => 'kepala_sekolah',
            'is_active' => true,
        ]);

        $otherSarpras = User::factory()->create([
            'role' => 'sarpras',
            'is_active' => true,
        ]);

        // Attempt toggle Kepala Sekolah
        $response1 = $this->actingAs($operatorSarpras)->post(route('sarpras.users.toggle', $kepalaSekolah));
        $response1->assertSessionHasErrors('msg');
        $this->assertTrue($kepalaSekolah->fresh()->is_active);

        // Attempt toggle another Sarpras
        $response2 = $this->actingAs($operatorSarpras)->post(route('sarpras.users.toggle', $otherSarpras));
        $response2->assertSessionHasErrors('msg');
        $this->assertTrue($otherSarpras->fresh()->is_active);
    }

    public function test_sarpras_can_toggle_kajur_status(): void
    {
        $operatorSarpras = User::factory()->create([
            'role' => 'sarpras',
            'is_active' => true,
        ]);

        $kajur = User::factory()->create([
            'role' => 'kajur',
            'department' => 'RPL',
            'is_active' => true,
        ]);

        $response = $this->actingAs($operatorSarpras)->post(route('sarpras.users.toggle', $kajur));
        $response->assertSessionHasNoErrors();
        $this->assertFalse($kajur->fresh()->is_active);
    }
}
