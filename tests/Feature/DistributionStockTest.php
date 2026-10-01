<?php

namespace Tests\Feature;

use App\Models\Distribution;
use App\Models\Item;
use App\Models\Location;
use App\Models\LocationHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DistributionStockTest extends TestCase
{
    use RefreshDatabase;

    private User $sarprasUser;
    private Location $locationA;
    private Location $locationB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sarprasUser = User::factory()->create([
            'role' => 'sarpras',
            'is_active' => true,
        ]);

        $this->locationA = Location::create([
            'code' => 'LOC-01',
            'name' => 'Gudang Utama',
            'building' => 'Gedung A',
        ]);

        $this->locationB = Location::create([
            'code' => 'LOC-02',
            'name' => 'Lab Komputer',
            'building' => 'Gedung B',
            'department' => 'RPL',
        ]);
    }

    public function test_distribution_decrements_item_stock_and_records_history(): void
    {
        $item = Item::create([
            'code' => 'BRG-001',
            'name' => 'Monitor LED 24 Inch',
            'category' => 'Elektronik',
            'unit' => 'Unit',
            'stock' => 10,
            'source' => 'pembelian',
            'location_id' => $this->locationA->id,
            'current_condition' => 'baik',
        ]);

        $response = $this->actingAs($this->sarprasUser)
            ->post(route('sarpras.logistics.distributions.store'), [
                'item_id' => $item->id,
                'quantity' => 4,
                'to_location_id' => $this->locationB->id,
                'recipient_department' => 'RPL',
                'recipient_name' => 'Pak Budi',
                'distribution_date' => '2026-10-02',
                'notes' => 'Penyaluran ke Lab RPL',
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertEquals(6, $item->fresh()->stock);

        $this->assertDatabaseHas('distributions', [
            'item_id' => $item->id,
            'quantity' => 4,
            'to_location_id' => $this->locationB->id,
            'recipient_department' => 'RPL',
        ]);

        $this->assertDatabaseHas('location_histories', [
            'item_id' => $item->id,
            'from_location_id' => $this->locationA->id,
            'to_location_id' => $this->locationB->id,
            'user_id' => $this->sarprasUser->id,
        ]);
    }

    public function test_distribution_fails_if_stock_is_insufficient(): void
    {
        $item = Item::create([
            'code' => 'BRG-002',
            'name' => 'Printer Laser',
            'category' => 'Elektronik',
            'unit' => 'Unit',
            'stock' => 2,
            'source' => 'pembelian',
            'location_id' => $this->locationA->id,
            'current_condition' => 'baik',
        ]);

        $response = $this->actingAs($this->sarprasUser)
            ->post(route('sarpras.logistics.distributions.store'), [
                'item_id' => $item->id,
                'quantity' => 5,
                'to_location_id' => $this->locationB->id,
                'distribution_date' => '2026-10-02',
            ]);

        $response->assertSessionHasErrors('quantity');
        $this->assertEquals(2, $item->fresh()->stock);
        $this->assertEquals(0, Distribution::count());
        $this->assertEquals(0, LocationHistory::count());
    }

    public function test_distribution_concurrent_requests_do_not_produce_negative_stock(): void
    {
        $item = Item::create([
            'code' => 'BRG-003',
            'name' => 'Switch Hub 24 Port',
            'category' => 'Elektronik',
            'unit' => 'Unit',
            'stock' => 5,
            'source' => 'pembelian',
            'location_id' => $this->locationA->id,
            'current_condition' => 'baik',
        ]);

        // First request tries to distribute 4 units (5 - 4 = 1 left)
        $firstResponse = $this->actingAs($this->sarprasUser)
            ->post(route('sarpras.logistics.distributions.store'), [
                'item_id' => $item->id,
                'quantity' => 4,
                'to_location_id' => $this->locationB->id,
                'distribution_date' => '2026-10-02',
            ]);

        $firstResponse->assertSessionHasNoErrors();
        $this->assertEquals(1, $item->fresh()->stock);

        // Second request also tries to distribute 4 units (1 < 4, should fail)
        $secondResponse = $this->actingAs($this->sarprasUser)
            ->post(route('sarpras.logistics.distributions.store'), [
                'item_id' => $item->id,
                'quantity' => 4,
                'to_location_id' => $this->locationB->id,
                'distribution_date' => '2026-10-02',
            ]);

        $secondResponse->assertSessionHasErrors('quantity');
        $this->assertEquals(1, $item->fresh()->stock);
        $this->assertGreaterThanOrEqual(0, $item->fresh()->stock);
        $this->assertEquals(1, Distribution::count());
    }
}
