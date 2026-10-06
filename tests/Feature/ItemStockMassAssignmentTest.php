<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemStockMassAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_cannot_be_set_via_mass_assignment_on_create(): void
    {
        $item = Item::create([
            'code' => 'STOCK-MA-001',
            'name' => 'Kertas HVS',
            'category' => 'ATK',
            'unit' => 'Rim',
            'stock' => 999,
            'minimum_stock' => 5,
            'source' => 'pembelian',
            'current_condition' => 'baik',
        ]);

        $item->refresh();

        $this->assertSame(0, $item->stock, 'stock tidak boleh masuk lewat mass-assignment');
        $this->assertSame(5, $item->minimum_stock, 'minimum_stock seharusnya tetap bisa dimasukkan');
    }

    public function test_stock_cannot_be_changed_via_mass_assignment_on_update(): void
    {
        $item = Item::create([
            'code' => 'STOCK-MA-002',
            'name' => 'Tinta Printer',
            'category' => 'ATK',
            'unit' => 'Botol',
            'source' => 'pembelian',
            'current_condition' => 'baik',
        ]);

        $this->assertSame(0, $item->fresh()->stock);

        $item->update([
            'stock' => 12345,
            'name' => 'Tinta Printer Baru',
        ]);

        $this->assertSame(0, $item->fresh()->stock, 'stock tidak boleh berubah lewat update mass-assignment');
        $this->assertSame('Tinta Printer Baru', $item->fresh()->name, 'field lain tetap harus bisa diupdate');
    }

    public function test_explicit_assignment_still_changes_stock(): void
    {
        $item = Item::create([
            'code' => 'STOCK-MA-003',
            'name' => 'Mouse',
            'category' => 'Elektronik',
            'unit' => 'Unit',
            'source' => 'pembelian',
            'current_condition' => 'baik',
        ]);

        $item->stock = 42;
        $item->save();

        $this->assertSame(42, $item->fresh()->stock, 'assignment eksplisit harus tetap bekerja');

        $item->increment('stock', 8);
        $this->assertSame(50, $item->fresh()->stock, 'increment harus tetap bekerja');
    }

    public function test_create_inventory_flow_persists_initial_stock(): void
    {
        $sarpras = User::factory()->sarpras()->create();

        $response = $this->actingAs($sarpras)->post(route('sarpras.inventory.store'), [
            'code' => 'STOCK-MA-004',
            'name' => 'Spidol Whiteboard',
            'category' => 'ATK',
            'item_type' => 'consumable',
            'unit' => 'Pcs',
            'stock' => 25,
            'minimum_stock' => 3,
            'source' => 'pembelian',
            'current_condition' => 'baik',
        ]);

        $response->assertRedirect()->assertSessionHas('success');

        $item = Item::where('code', 'STOCK-MA-004')->firstOrFail();

        $this->assertSame(25, $item->stock, 'flow create inventaris tetap harus menyimpan stok awal');
        $this->assertSame(3, $item->minimum_stock);
    }
}
