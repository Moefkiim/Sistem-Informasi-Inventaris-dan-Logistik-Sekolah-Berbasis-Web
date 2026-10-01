<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KajurTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_kajur_with_null_department_is_forbidden_from_inventory(): void
    {
        $kajurNull = User::factory()->create([
            'role' => 'kajur',
            'department' => null,
            'is_active' => true,
        ]);

        $response = $this->actingAs($kajurNull)->get(route('kajur.inventory.index'));
        $response->assertStatus(403);
    }

    public function test_kajur_cannot_access_items_from_different_department(): void
    {
        $kajurRPL = User::factory()->create([
            'role' => 'kajur',
            'department' => 'RPL',
            'is_active' => true,
        ]);

        $itemTKJ = Item::create([
            'code' => 'TKJ-001',
            'name' => 'Crimping Tool',
            'category' => 'Alat',
            'unit' => 'Pcs',
            'stock' => 5,
            'source' => 'pembelian',
            'department' => 'TKJ',
            'current_condition' => 'baik',
        ]);

        $response = $this->actingAs($kajurRPL)->get(route('kajur.inventory.show', $itemTKJ));
        $response->assertStatus(403);
    }

    public function test_kajur_with_null_department_is_forbidden_from_reports(): void
    {
        $kajurNull = User::factory()->create([
            'role' => 'kajur',
            'department' => null,
            'is_active' => true,
        ]);

        $response = $this->actingAs($kajurNull)->get(route('reports.index'));
        $response->assertStatus(403);
    }

    public function test_kajur_can_only_see_their_own_department_inventory(): void
    {
        $kajurRPL = User::factory()->create([
            'role' => 'kajur',
            'department' => 'RPL',
            'is_active' => true,
        ]);

        $itemRPL = Item::create([
            'code' => 'RPL-001',
            'name' => 'PC Workstation RPL',
            'category' => 'Elektronik',
            'unit' => 'Unit',
            'stock' => 10,
            'source' => 'pembelian',
            'department' => 'RPL',
            'current_condition' => 'baik',
        ]);

        $response = $this->actingAs($kajurRPL)->get(route('kajur.inventory.index'));
        $response->assertStatus(200);
        $response->assertSee('PC Workstation RPL');

        $detailResponse = $this->actingAs($kajurRPL)->get(route('kajur.inventory.show', $itemRPL));
        $detailResponse->assertStatus(200);
        $detailResponse->assertSee('PC Workstation RPL');
    }
}
