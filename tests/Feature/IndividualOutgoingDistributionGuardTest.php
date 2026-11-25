<?php

namespace Tests\Feature;

use App\Models\AssetUnit;
use App\Models\Distribution;
use App\Models\Item;
use App\Models\Location;
use App\Models\LocationHistory;
use App\Models\OutgoingItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndividualOutgoingDistributionGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_barang_keluar_menolak_item_individual_tanpa_mengubah_stok_atau_unit(): void
    {
        $sarpras = User::factory()->sarpras()->create();
        $location = Location::factory()->create();
        $item = Item::factory()->individual()->create([
            'stock' => 2,
            'location_id' => $location->id,
        ]);

        AssetUnit::factory()->create([
            'item_id' => $item->id,
            'location_id' => $location->id,
            'current_status' => 'aktif',
        ]);

        $response = $this->actingAs($sarpras)
            ->from(route('sarpras.logistics.outgoing'))
            ->post(route('sarpras.logistics.outgoing.store'), [
                'item_id' => $item->id,
                'quantity' => 1,
                'exit_date' => now()->toDateString(),
                'reason' => 'Rusak',
                'notes' => 'Uji tolak barang keluar individual',
            ]);

        $response->assertSessionHasErrors('item_id');
        $this->assertSame(2, $item->fresh()->stock);
        $this->assertSame(0, OutgoingItem::count());
        $this->assertSame('aktif', AssetUnit::where('item_id', $item->id)->firstOrFail()->current_status);
    }

    public function test_distribusi_menolak_item_individual_tanpa_mengubah_stok_lokasi_atau_unit(): void
    {
        $sarpras = User::factory()->sarpras()->create();
        $origin = Location::factory()->create();
        $destination = Location::factory()->create();
        $item = Item::factory()->individual()->create([
            'stock' => 2,
            'location_id' => $origin->id,
        ]);

        AssetUnit::factory()->create([
            'item_id' => $item->id,
            'location_id' => $origin->id,
            'current_status' => 'aktif',
        ]);

        $response = $this->actingAs($sarpras)
            ->from(route('sarpras.logistics.distributions'))
            ->post(route('sarpras.logistics.distributions.store'), [
                'item_id' => $item->id,
                'quantity' => 1,
                'to_location_id' => $destination->id,
                'recipient_department' => 'RPL',
                'recipient_name' => 'Kepala Lab RPL',
                'distribution_date' => now()->toDateString(),
                'notes' => 'Uji tolak distribusi individual',
            ]);

        $response->assertSessionHasErrors('item_id');
        $this->assertSame(2, $item->fresh()->stock);
        $this->assertSame($origin->id, $item->fresh()->location_id);
        $this->assertSame(0, Distribution::count());
        $this->assertSame(0, LocationHistory::count());
        $this->assertSame($origin->id, AssetUnit::where('item_id', $item->id)->firstOrFail()->location_id);
    }

    public function test_barang_keluar_consumable_tetap_berfungsi_normal(): void
    {
        $sarpras = User::factory()->sarpras()->create();
        $item = Item::factory()->consumable()->create(['stock' => 10]);

        $response = $this->actingAs($sarpras)
            ->post(route('sarpras.logistics.outgoing.store'), [
                'item_id' => $item->id,
                'quantity' => 3,
                'exit_date' => now()->toDateString(),
                'reason' => 'Habis Pakai',
                'notes' => 'Pemakaian rutin',
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame(7, $item->fresh()->stock);
        $this->assertSame(1, OutgoingItem::where('item_id', $item->id)->count());
    }

    public function test_distribusi_consumable_tetap_berfungsi_normal(): void
    {
        $sarpras = User::factory()->sarpras()->create();
        $origin = Location::factory()->create();
        $destination = Location::factory()->create();
        $item = Item::factory()->consumable()->create([
            'stock' => 10,
            'location_id' => $origin->id,
        ]);

        $response = $this->actingAs($sarpras)
            ->post(route('sarpras.logistics.distributions.store'), [
                'item_id' => $item->id,
                'quantity' => 4,
                'to_location_id' => $destination->id,
                'recipient_department' => 'RPL',
                'recipient_name' => 'Lab RPL',
                'distribution_date' => now()->toDateString(),
                'notes' => 'Distribusi rutin',
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame(6, $item->fresh()->stock);
        $this->assertSame($destination->id, $item->fresh()->location_id);
        $this->assertSame(1, Distribution::where('item_id', $item->id)->count());
        $this->assertSame(1, LocationHistory::where('item_id', $item->id)->count());
    }
}
