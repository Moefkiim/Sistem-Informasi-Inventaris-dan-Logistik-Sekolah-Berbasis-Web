<?php

namespace Tests\Feature;

use App\Models\Distribution;
use App\Models\IncomingItem;
use App\Models\Item;
use App\Models\Location;
use App\Models\OutgoingItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentNumberSequentialTest extends TestCase
{
    use RefreshDatabase;

    private function sarpras(): User
    {
        return User::factory()->sarpras()->create();
    }

    public function test_incoming_numbers_are_sequential(): void
    {
        $sarpras = $this->sarpras();
        $item = Item::factory()->create(['stock' => 0]);

        for ($i = 0; $i < 4; $i++) {
            $this->actingAs($sarpras)
                ->post(route('sarpras.logistics.incoming.store'), [
                    'item_id' => $item->id,
                    'quantity' => 1,
                    'source' => 'pembelian',
                    'entry_date' => '2026-10-06',
                ])
                ->assertSessionHas('success');
        }

        $numbers = IncomingItem::orderBy('id')->pluck('transaction_number')->all();

        $this->assertSame([
            'IN-20261006-0001',
            'IN-20261006-0002',
            'IN-20261006-0003',
            'IN-20261006-0004',
        ], $numbers);
    }

    public function test_outgoing_numbers_are_sequential(): void
    {
        $sarpras = $this->sarpras();
        $item = Item::factory()->create(['stock' => 100]);

        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($sarpras)
                ->post(route('sarpras.logistics.outgoing.store'), [
                    'item_id' => $item->id,
                    'quantity' => 1,
                    'exit_date' => '2026-10-06',
                    'reason' => 'Habis pakai',
                ])
                ->assertSessionHas('success');
        }

        $numbers = OutgoingItem::orderBy('id')->pluck('transaction_number')->all();

        $this->assertSame([
            'OUT-20261006-0001',
            'OUT-20261006-0002',
            'OUT-20261006-0003',
        ], $numbers);
    }

    public function test_distribution_numbers_are_sequential(): void
    {
        $sarpras = $this->sarpras();
        $item = Item::factory()->create(['stock' => 100]);
        $location = Location::factory()->create();

        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($sarpras)
                ->post(route('sarpras.logistics.distributions.store'), [
                    'item_id' => $item->id,
                    'quantity' => 1,
                    'to_location_id' => $location->id,
                    'distribution_date' => '2026-10-06',
                ])
                ->assertSessionHas('success');
        }

        $numbers = Distribution::orderBy('id')->pluck('distribution_number')->all();

        $this->assertSame([
            'DIST-20261006-0001',
            'DIST-20261006-0002',
            'DIST-20261006-0003',
        ], $numbers);
    }

    public function test_new_number_continues_after_legacy_uniqid_number(): void
    {
        $sarpras = $this->sarpras();
        $item = Item::factory()->create(['stock' => 0]);

        IncomingItem::create([
            'transaction_number' => 'IN-20261006-A3F2',
            'item_id' => $item->id,
            'quantity' => 1,
            'source' => 'bantuan',
            'entry_date' => '2026-10-06',
            'user_id' => $sarpras->id,
        ]);

        $this->actingAs($sarpras)
            ->post(route('sarpras.logistics.incoming.store'), [
                'item_id' => $item->id,
                'quantity' => 1,
                'source' => 'pembelian',
                'entry_date' => '2026-10-06',
            ])
            ->assertSessionHas('success');

        $numbers = IncomingItem::orderBy('id')->pluck('transaction_number')->all();

        $this->assertSame(['IN-20261006-A3F2', 'IN-20261006-0001'], $numbers);
        $this->assertSame(2, count(array_unique($numbers)));
    }

    public function test_bulk_incoming_generation_has_no_duplicates(): void
    {
        $sarpras = $this->sarpras();
        $item = Item::factory()->create(['stock' => 0]);

        for ($i = 0; $i < 50; $i++) {
            $this->actingAs($sarpras)
                ->post(route('sarpras.logistics.incoming.store'), [
                    'item_id' => $item->id,
                    'quantity' => 1,
                    'source' => 'pembelian',
                    'entry_date' => '2026-10-06',
                ]);
        }

        $numbers = IncomingItem::pluck('transaction_number')->all();

        $this->assertSame(50, count($numbers));
        $this->assertSame(50, count(array_unique($numbers)));
        $this->assertSame('IN-20261006-0001', min($numbers));
        $this->assertSame('IN-20261006-0050', max($numbers));
    }
}
