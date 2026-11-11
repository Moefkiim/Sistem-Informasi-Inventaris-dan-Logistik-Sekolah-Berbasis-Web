<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Location;
use App\Models\LocationHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PR-2: F1 + F2 — Konsistensi stok dan lokasi pada logistik.
 *
 * F1: Barang masuk untuk item individual harus ditolak dengan pesan
 *     yang mengarahkan user mendaftarkan unit via alur registrasi.
 * F2: Distribusi harus memperbarui items.location_id (consumable) dan
 *     location_histories.from_location_id harus berisi lokasi SEBELUM
 *     distribusi; distribusi berantai pakai lokasi hasil distribusi sebelumnya.
 */
class StockLocationSyncTest extends TestCase
{
    use RefreshDatabase;

    private function sarpras(): User
    {
        return User::factory()->sarpras()->create();
    }

    // =========================================================================
    // F1 — Barang masuk individual ditolak
    // =========================================================================

    /** @test */
    public function test_barang_masuk_individual_ditolak_dengan_pesan_arahan(): void
    {
        $sarpras = $this->sarpras();
        $item = Item::factory()->individual()->create(['stock' => 0]);

        $response = $this->actingAs($sarpras)
            ->post(route('sarpras.logistics.incoming.store'), [
                'item_id' => $item->id,
                'quantity' => 2,
                'source' => 'pembelian',
                'entry_date' => now()->format('Y-m-d'),
            ]);

        // Harus ada error (redirect dengan validation error) atau session error,
        // BUKAN session 'success'.
        $response->assertSessionMissing('success');

        // Stok tidak boleh bertambah.
        $this->assertSame(0, $item->fresh()->stock);
    }

    /** @test */
    public function test_barang_masuk_consumable_tetap_berfungsi_normal(): void
    {
        $sarpras = $this->sarpras();
        $item = Item::factory()->consumable()->create(['stock' => 5]);

        $this->actingAs($sarpras)
            ->post(route('sarpras.logistics.incoming.store'), [
                'item_id' => $item->id,
                'quantity' => 3,
                'source' => 'pembelian',
                'entry_date' => now()->format('Y-m-d'),
            ])
            ->assertSessionHas('success');

        $this->assertSame(8, $item->fresh()->stock);
    }

    // =========================================================================
    // F2 — Distribusi memperbarui items.location_id
    // =========================================================================

    /** @test */
    public function test_distribusi_consumable_memperbarui_location_id_item(): void
    {
        $sarpras = $this->sarpras();
        $asal = Location::factory()->create(['name' => 'Gudang Pusat']);
        $tujuan = Location::factory()->create(['name' => 'Lab RPL']);
        $item = Item::factory()->consumable()->create(['stock' => 10, 'location_id' => $asal->id]);

        $this->actingAs($sarpras)
            ->post(route('sarpras.logistics.distributions.store'), [
                'item_id' => $item->id,
                'quantity' => 4,
                'to_location_id' => $tujuan->id,
                'distribution_date' => now()->format('Y-m-d'),
            ])
            ->assertSessionHas('success');

        $this->assertSame($tujuan->id, $item->fresh()->location_id);
    }

    /** @test */
    public function test_distribusi_mencatat_from_location_id_lokasi_sebelumnya(): void
    {
        $sarpras = $this->sarpras();
        $asal = Location::factory()->create(['name' => 'Gudang A']);
        $tujuan = Location::factory()->create(['name' => 'Lab B']);
        $item = Item::factory()->consumable()->create(['stock' => 10, 'location_id' => $asal->id]);

        $this->actingAs($sarpras)
            ->post(route('sarpras.logistics.distributions.store'), [
                'item_id' => $item->id,
                'quantity' => 2,
                'to_location_id' => $tujuan->id,
                'distribution_date' => now()->format('Y-m-d'),
            ]);

        $this->assertDatabaseHas('location_histories', [
            'item_id' => $item->id,
            'from_location_id' => $asal->id,
            'to_location_id' => $tujuan->id,
        ]);
    }

    /** @test */
    public function test_distribusi_berantai_pakai_lokasi_hasil_distribusi_sebelumnya(): void
    {
        $sarpras = $this->sarpras();
        $gudang = Location::factory()->create(['name' => 'Gudang']);
        $labRpl = Location::factory()->create(['name' => 'Lab RPL']);
        $labTkj = Location::factory()->create(['name' => 'Lab TKJ']);
        $item = Item::factory()->consumable()->create(['stock' => 20, 'location_id' => $gudang->id]);

        // Distribusi pertama: Gudang → Lab RPL
        $this->actingAs($sarpras)
            ->post(route('sarpras.logistics.distributions.store'), [
                'item_id' => $item->id,
                'quantity' => 5,
                'to_location_id' => $labRpl->id,
                'distribution_date' => now()->format('Y-m-d'),
            ])
            ->assertSessionHas('success');

        $this->assertSame($labRpl->id, $item->fresh()->location_id);

        // Distribusi kedua: Lab RPL → Lab TKJ
        // from harus = Lab RPL (bukan Gudang lagi)
        $this->actingAs($sarpras)
            ->post(route('sarpras.logistics.distributions.store'), [
                'item_id' => $item->id,
                'quantity' => 3,
                'to_location_id' => $labTkj->id,
                'distribution_date' => now()->format('Y-m-d'),
            ])
            ->assertSessionHas('success');

        $this->assertSame($labTkj->id, $item->fresh()->location_id);

        $histories = LocationHistory::where('item_id', $item->id)->orderBy('id')->get();
        $this->assertCount(2, $histories);
        $this->assertSame($gudang->id, $histories[0]->from_location_id);
        $this->assertSame($labRpl->id, $histories[0]->to_location_id);
        $this->assertSame($labRpl->id, $histories[1]->from_location_id);
        $this->assertSame($labTkj->id, $histories[1]->to_location_id);
    }
}
