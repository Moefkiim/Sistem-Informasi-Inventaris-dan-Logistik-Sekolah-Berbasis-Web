<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Location;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardScopingTest extends TestCase
{
    use RefreshDatabase;

    private function kajur(string $department): User
    {
        return User::factory()->kajur()->create(['department' => $department]);
    }

    private function item(string $department): Item
    {
        return Item::factory()->create(['department' => $department]);
    }

    private function location(string $department): Location
    {
        return Location::factory()->create(['department' => $department]);
    }

    private function submission(string $department, User $user, string $status): Submission
    {
        $submission = Submission::create([
            'submission_number' => 'REQ-'.md5(uniqid()),
            'user_id' => $user->id,
            'department' => $department,
            'title' => 'Pengajuan '.$department,
            'purpose' => 'Pengajuan kebutuhan jurusan '.$department,
        ]);
        $submission->forceFill(['status' => $status])->save();

        return $submission;
    }

    public function test_kajur_hanya_melihat_angka_department_sendiri(): void
    {
        $kajurRpl = $this->kajur('RPL');
        $kajurTkj = $this->kajur('TKJ');

        $this->location('RPL');
        $this->location('TKJ');

        $this->item('RPL');
        $this->item('RPL');
        $this->item('TKJ');

        $this->submission('RPL', $kajurRpl, 'submitted');
        $this->submission('RPL', $kajurRpl, 'draft');
        $this->submission('TKJ', $kajurTkj, 'submitted');

        $response = $this->actingAs($kajurRpl)->get(route('home'));

        $response->assertOk();
        $this->assertSame(2, $response->viewData('totalItems'));
        $this->assertSame(1, $response->viewData('totalLocations'));
        $this->assertSame(1, $response->viewData('pendingSubmissions'));
    }

    public function test_kajur_tanpa_department_dashboard_ditahan_403(): void
    {
        $kajur = User::factory()->kajur()->create(['department' => null]);

        $this->item('RPL');
        $this->item('TKJ');

        $this->actingAs($kajur)->get(route('home'))->assertForbidden();
    }

    public function test_sarpras_dan_kepala_sekolah_melihat_angka_global(): void
    {
        $this->location('RPL');
        $this->location('TKJ');

        $this->item('RPL');
        $this->item('TKJ');
        $this->item('Umum');

        $kajurRpl = $this->kajur('RPL');
        $this->submission('RPL', $kajurRpl, 'submitted');
        $this->submission('RPL', $kajurRpl, 'reviewed_sarpras');

        $sarpras = User::factory()->sarpras()->create();
        $kepsek = User::factory()->kepalaSekolah()->create();

        $sarprasResponse = $this->actingAs($sarpras)->get(route('home'));
        $kepsekResponse = $this->actingAs($kepsek)->get(route('home'));

        foreach ([$sarprasResponse, $kepsekResponse] as $response) {
            $this->assertSame(3, $response->viewData('totalItems'));
            $this->assertSame(2, $response->viewData('totalLocations'));
            $this->assertSame(2, $response->viewData('pendingSubmissions'));
        }
    }
}
