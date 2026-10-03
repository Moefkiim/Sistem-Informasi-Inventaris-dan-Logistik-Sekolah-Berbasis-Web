<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KajurTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_kajur_cannot_access_other_department_item(): void
    {
        $rpl = User::factory()->kajur()->create(['department' => 'Rekayasa Perangkat Lunak']);
        $tkj = User::factory()->kajur()->create(['department' => 'Teknik Komputer Jaringan']);

        $itemRpl = Item::factory()->create(['department' => 'Rekayasa Perangkat Lunak']);
        $itemTkj = Item::factory()->create(['department' => 'Teknik Komputer Jaringan']);

        $this->actingAs($rpl)
            ->get(route('kajur.inventory.show', $itemTkj))
            ->assertForbidden();

        $response = $this->actingAs($rpl)
            ->get(route('kajur.inventory.index'))
            ->assertOk();
        $response->assertDontSee($itemTkj->name);
        $response->assertSee($itemRpl->name);
    }

    public function test_kajur_with_null_department_gets_forbidden(): void
    {
        $kajur = User::factory()->kajur()->create(['department' => null]);
        $item = Item::factory()->create(['department' => 'Rekayasa Perangkat Lunak']);

        $this->actingAs($kajur)
            ->get(route('kajur.inventory.index'))
            ->assertForbidden();

        $this->actingAs($kajur)
            ->get(route('kajur.inventory.show', $item))
            ->assertForbidden();
    }
}
