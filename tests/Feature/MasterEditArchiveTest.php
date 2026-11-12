<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Loan;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PR-5: F6 — Edit atribut deskriptif master barang dan lokasi, serta arsip & restore.
 */
class MasterEditArchiveTest extends TestCase
{
    use RefreshDatabase;

    private function sarpras(): User
    {
        return User::factory()->sarpras()->create();
    }

    private function kajur(): User
    {
        return User::factory()->kajur()->create();
    }

    public function test_sarpras_dapat_mengedit_atribut_deskriptif_barang(): void
    {
        $sarpras = $this->sarpras();
        $item = Item::factory()->consumable()->create([
            'name' => 'Kabel UTP Cat 5',
            'brand' => 'Belden',
            'model' => 'Cat5e',
            'category' => 'Jaringan',
            'unit' => 'Meter',
            'stock' => 50,
        ]);

        $this->actingAs($sarpras)
            ->get(route('sarpras.inventory.edit', $item))
            ->assertOk()
            ->assertSee('Kabel UTP Cat 5');

        $this->actingAs($sarpras)
            ->put(route('sarpras.inventory.update', $item), [
                'name' => 'Kabel UTP Cat 6 High Speed',
                'brand' => 'Schneider',
                'model' => 'Actassi',
                'category' => 'Jaringan',
                'unit' => 'Meter',
                'description' => 'Kabel patchcord ruang server',
                'minimum_stock' => 10,
            ])
            ->assertRedirect(route('sarpras.inventory.show', $item))
            ->assertSessionHas('success');

        $item->refresh();
        $this->assertSame('Kabel UTP Cat 6 High Speed', $item->name);
        $this->assertSame('Schneider', $item->brand);
        $this->assertSame('Actassi', $item->model);
        $this->assertSame(50, $item->stock, 'Stok tidak boleh terpengaruh form edit');

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'item_updated',
            'user_id' => $sarpras->id,
        ]);
    }

    public function test_stok_dan_kode_tidak_dapat_diubah_lewat_form_edit_barang(): void
    {
        $sarpras = $this->sarpras();
        $originalCode = 'BRG-ORIGINAL-01';
        $item = Item::factory()->consumable()->create([
            'code' => $originalCode,
            'name' => 'Spidol Papan Tulis',
            'stock' => 15,
        ]);

        $this->actingAs($sarpras)
            ->put(route('sarpras.inventory.update', $item), [
                'code' => 'BRG-HACKED-99',
                'stock' => 9999,
                'name' => 'Spidol Papan Tulis Baru',
                'category' => 'Alat',
                'unit' => 'Pcs',
            ])
            ->assertRedirect();

        $item->refresh();
        $this->assertSame($originalCode, $item->code, 'Kode barang tidak boleh berubah');
        $this->assertSame(15, $item->stock, 'Stok barang tidak boleh berubah');
        $this->assertSame('Spidol Papan Tulis Baru', $item->name);
    }

    public function test_sarpras_dapat_mengarsipkan_dan_memulihkan_barang(): void
    {
        $sarpras = $this->sarpras();
        $item = Item::factory()->consumable()->create(['name' => 'Mouse Rusak Total']);

        // Arsipkan barang (soft delete)
        $this->actingAs($sarpras)
            ->delete(route('sarpras.inventory.destroy', $item))
            ->assertRedirect(route('sarpras.inventory.index'))
            ->assertSessionHas('success');

        $this->assertSoftDeleted('items', ['id' => $item->id]);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'item_archived',
            'user_id' => $sarpras->id,
        ]);

        // Pulihkan barang (restore)
        $this->actingAs($sarpras)
            ->post(route('sarpras.inventory.restore', $item->id))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('items', [
            'id' => $item->id,
            'deleted_at' => null,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'item_restored',
            'user_id' => $sarpras->id,
        ]);
    }

    public function test_barang_dengan_pinjaman_aktif_tidak_dapat_diarsipkan(): void
    {
        $sarpras = $this->sarpras();
        $item = Item::factory()->create(['name' => 'Proyektor Epson']);

        Loan::factory()->create([
            'item_id' => $item->id,
            'status' => 'dipinjam',
        ]);

        $this->actingAs($sarpras)
            ->delete(route('sarpras.inventory.destroy', $item))
            ->assertSessionHasErrors();

        $this->assertDatabaseHas('items', [
            'id' => $item->id,
            'deleted_at' => null,
        ]);
    }

    public function test_sarpras_dapat_mengedit_master_lokasi(): void
    {
        $sarpras = $this->sarpras();
        $location = Location::factory()->create([
            'code' => 'LOK-LAB-01',
            'name' => 'Lab RPL 1',
            'building' => 'Gedung A',
        ]);

        $this->actingAs($sarpras)
            ->put(route('sarpras.locations.update', $location), [
                'name' => 'Lab Rekayasa Perangkat Lunak 1',
                'building' => 'Gedung Utama Lt. 2',
                'department' => 'RPL',
                'description' => 'Laboratorium praktik pemrograman',
            ])
            ->assertRedirect(route('sarpras.locations.index'))
            ->assertSessionHas('success');

        $location->refresh();
        $this->assertSame('Lab Rekayasa Perangkat Lunak 1', $location->name);
        $this->assertSame('Gedung Utama Lt. 2', $location->building);
        $this->assertSame('LOK-LAB-01', $location->code, 'Kode lokasi tidak boleh berubah');

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'location_updated',
            'user_id' => $sarpras->id,
        ]);
    }

    public function test_sarpras_dapat_memulihkan_lokasi_yang_diarsipkan(): void
    {
        $sarpras = $this->sarpras();
        $location = Location::factory()->create(['name' => 'Gudang Lama']);
        $location->delete();

        $this->assertSoftDeleted('locations', ['id' => $location->id]);

        $this->actingAs($sarpras)
            ->post(route('sarpras.locations.restore', $location->id))
            ->assertRedirect(route('sarpras.locations.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('locations', [
            'id' => $location->id,
            'deleted_at' => null,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'location_restored',
            'user_id' => $sarpras->id,
        ]);
    }

    public function test_kajur_tidak_dapat_mengedit_atau_mengarsipkan_barang_dan_lokasi(): void
    {
        $kajur = $this->kajur();
        $item = Item::factory()->create();
        $location = Location::factory()->create();

        $this->actingAs($kajur)->get(route('sarpras.inventory.edit', $item))->assertForbidden();
        $this->actingAs($kajur)->put(route('sarpras.inventory.update', $item), ['name' => 'Ubah'])->assertForbidden();
        $this->actingAs($kajur)->delete(route('sarpras.inventory.destroy', $item))->assertForbidden();
        $this->actingAs($kajur)->put(route('sarpras.locations.update', $location), ['name' => 'Ubah'])->assertForbidden();
    }
}
