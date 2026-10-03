<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DistributionStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_distribution_reduces_stock(): void
    {
        $sarpras = User::factory()->sarpras()->create();
        $loc = Location::factory()->create();
        $item = Item::factory()->create(['stock' => 10]);

        $this->actingAs($sarpras)
            ->post(route('sarpras.logistics.distributions.store'), [
                'item_id' => $item->id,
                'quantity' => 3,
                'to_location_id' => $loc->id,
                'recipient_department' => 'RPL',
                'recipient_name' => 'Guru',
                'distribution_date' => now()->format('Y-m-d'),
                'notes' => 'Test',
            ])
            ->assertSessionHas('success');

        $item->refresh();
        $this->assertEquals(7, $item->stock);
    }

    public function test_distribution_rejects_if_insufficient_stock(): void
    {
        $sarpras = User::factory()->sarpras()->create();
        $loc = Location::factory()->create();
        $item = Item::factory()->create(['stock' => 2]);

        $this->actingAs($sarpras)
            ->post(route('sarpras.logistics.distributions.store'), [
                'item_id' => $item->id,
                'quantity' => 5,
                'to_location_id' => $loc->id,
                'recipient_department' => 'RPL',
                'recipient_name' => 'Guru',
                'distribution_date' => now()->format('Y-m-d'),
                'notes' => 'Test',
            ])
            ->assertSessionHasErrors();
    }
}
