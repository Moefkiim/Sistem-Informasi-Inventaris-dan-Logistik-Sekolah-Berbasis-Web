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
        $stamp = now()->format('Ymd');

        for ($i = 0; $i < 4; $i++) {
            $this->actingAs($sarpras)
                ->post(route('sarpras.logistics.incoming.store'), [
                    'item_id' => $item->id,
                    'quantity' => 1,
                    'source' => 'pembelian',
                    'entry_date' => now()->format('Y-m-d'),
                ])
                ->assertSessionHas('success');
        }

        $numbers = IncomingItem::orderBy('id')->pluck('transaction_number')->all();

        $this->assertSame([
            "IN-{$stamp}-0001",
            "IN-{$stamp}-0002",
            "IN-{$stamp}-0003",
            "IN-{$stamp}-0004",
        ], $numbers);
    }

    public function test_outgoing_numbers_are_sequential(): void
    {
        $sarpras = $this->sarpras();
        $item = Item::factory()->create(['stock' => 100]);
        $stamp = now()->format('Ymd');

        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($sarpras)
                ->post(route('sarpras.logistics.outgoing.store'), [
                    'item_id' => $item->id,
                    'quantity' => 1,
                    'exit_date' => now()->format('Y-m-d'),
                    'reason' => 'Habis pakai',
                ])
                ->assertSessionHas('success');
        }

        $numbers = OutgoingItem::orderBy('id')->pluck('transaction_number')->all();

        $this->assertSame([
            "OUT-{$stamp}-0001",
            "OUT-{$stamp}-0002",
            "OUT-{$stamp}-0003",
        ], $numbers);
    }

    public function test_distribution_numbers_are_sequential(): void
    {
        $sarpras = $this->sarpras();
        $item = Item::factory()->create(['stock' => 100]);
        $location = Location::factory()->create();
        $stamp = now()->format('Ymd');

        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($sarpras)
                ->post(route('sarpras.logistics.distributions.store'), [
                    'item_id' => $item->id,
                    'quantity' => 1,
                    'to_location_id' => $location->id,
                    'distribution_date' => now()->format('Y-m-d'),
                ])
                ->assertSessionHas('success');
        }

        $numbers = Distribution::orderBy('id')->pluck('distribution_number')->all();

        $this->assertSame([
            "DIST-{$stamp}-0001",
            "DIST-{$stamp}-0002",
            "DIST-{$stamp}-0003",
        ], $numbers);
    }

    public function test_new_number_continues_after_legacy_uniqid_number(): void
    {
        $sarpras = $this->sarpras();
        $item = Item::factory()->create(['stock' => 0]);
        $stamp = now()->format('Ymd');

        IncomingItem::create([
            'transaction_number' => "IN-{$stamp}-A3F2",
            'item_id' => $item->id,
            'quantity' => 1,
            'source' => 'bantuan',
            'entry_date' => now()->format('Y-m-d'),
            'user_id' => $sarpras->id,
        ]);

        $this->actingAs($sarpras)
            ->post(route('sarpras.logistics.incoming.store'), [
                'item_id' => $item->id,
                'quantity' => 1,
                'source' => 'pembelian',
                'entry_date' => now()->format('Y-m-d'),
            ])
            ->assertSessionHas('success');

        $numbers = IncomingItem::orderBy('id')->pluck('transaction_number')->all();

        $this->assertSame(["IN-{$stamp}-A3F2", "IN-{$stamp}-0001"], $numbers);
        $this->assertSame(2, count(array_unique($numbers)));
    }

    public function test_bulk_incoming_generation_has_no_duplicates(): void
    {
        $sarpras = $this->sarpras();
        $item = Item::factory()->create(['stock' => 0]);
        $stamp = now()->format('Ymd');

        for ($i = 0; $i < 50; $i++) {
            $this->actingAs($sarpras)
                ->post(route('sarpras.logistics.incoming.store'), [
                    'item_id' => $item->id,
                    'quantity' => 1,
                    'source' => 'pembelian',
                    'entry_date' => now()->format('Y-m-d'),
                ]);
        }

        $numbers = IncomingItem::pluck('transaction_number')->all();

        $this->assertSame(50, count($numbers));
        $this->assertSame(50, count(array_unique($numbers)));
        $this->assertSame("IN-{$stamp}-0001", min($numbers));
        $this->assertSame("IN-{$stamp}-0050", max($numbers));
    }
}
