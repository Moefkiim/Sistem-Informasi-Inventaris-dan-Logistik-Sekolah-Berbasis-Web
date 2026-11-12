<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\IncomingItem;
use App\Models\Item;
use App\Models\OutgoingItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PR-4: F5 — Koreksi append-only untuk transaksi barang masuk dan barang keluar.
 */
class LogisticsCorrectionTest extends TestCase
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

    public function test_koreksi_barang_masuk_membalik_stok_dan_membuat_transaksi_pembalik(): void
    {
        $sarpras = $this->sarpras();
        $item = Item::factory()->consumable()->create(['stock' => 10]);

        $this->actingAs($sarpras)
            ->post(route('sarpras.logistics.incoming.store'), [
                'item_id' => $item->id,
                'quantity' => 5,
                'source' => 'pembelian',
                'entry_date' => now()->format('Y-m-d'),
                'notes' => 'Barang awal',
            ])
            ->assertSessionHas('success');

        $incoming = IncomingItem::where('item_id', $item->id)->firstOrFail();
        $this->assertSame(15, $item->fresh()->stock);

        // Lakukan koreksi
        $this->actingAs($sarpras)
            ->post(route('sarpras.logistics.incoming.reverse', $incoming), [
                'reason' => 'Salah input faktur ganda',
            ])
            ->assertSessionHas('success');

        // Stok harus berbalik berkurang 5 (15 - 5 = 10)
        $this->assertSame(10, $item->fresh()->stock);

        // Baris asli ditandai sudah dikoreksi
        $incoming->refresh();
        $this->assertTrue($incoming->isReversed());
        $this->assertSame('Salah input faktur ganda', $incoming->reversal_reason);

        // Baris baru pembalik tercatat di incoming_items
        $reversal = IncomingItem::where('reverses_id', $incoming->id)->firstOrFail();
        $this->assertSame($incoming->item_id, $reversal->item_id);
        $this->assertSame($incoming->quantity, $reversal->quantity);
        $this->assertSame('Salah input faktur ganda', $reversal->reversal_reason);
        $this->assertNotNull($reversal->reversed_at);
        $this->assertNotSame($incoming->transaction_number, $reversal->transaction_number);

        // ActivityLog tercatat
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'incoming_reversed',
            'user_id' => $sarpras->id,
        ]);
    }

    public function test_koreksi_barang_masuk_ditolak_jika_stok_tidak_mencukupi(): void
    {
        $sarpras = $this->sarpras();
        $item = Item::factory()->consumable()->create(['stock' => 0]);

        $this->actingAs($sarpras)
            ->post(route('sarpras.logistics.incoming.store'), [
                'item_id' => $item->id,
                'quantity' => 5,
                'source' => 'pembelian',
                'entry_date' => now()->format('Y-m-d'),
            ]);

        $incoming = IncomingItem::where('item_id', $item->id)->firstOrFail();

        // Buat barang keluar 4 unit sehingga sisa stok = 1
        $this->actingAs($sarpras)
            ->post(route('sarpras.logistics.outgoing.store'), [
                'item_id' => $item->id,
                'quantity' => 4,
                'exit_date' => now()->format('Y-m-d'),
                'reason' => 'Pemakaian kantor',
            ]);

        $this->assertSame(1, $item->fresh()->stock);

        // Coba koreksi barang masuk (qty 5) saat stok hanya 1 -> harus ditolak
        $this->actingAs($sarpras)
            ->post(route('sarpras.logistics.incoming.reverse', $incoming), [
                'reason' => 'Koreksi salah jumlah',
            ])
            ->assertSessionHasErrors('reason');

        // Stok tidak berubah
        $this->assertSame(1, $item->fresh()->stock);
        $this->assertFalse($incoming->fresh()->isReversed());
    }

    public function test_koreksi_barang_masuk_ditolak_jika_sudah_dikoreksi(): void
    {
        $sarpras = $this->sarpras();
        $item = Item::factory()->consumable()->create(['stock' => 10]);

        $this->actingAs($sarpras)
            ->post(route('sarpras.logistics.incoming.store'), [
                'item_id' => $item->id,
                'quantity' => 3,
                'source' => 'pembelian',
                'entry_date' => now()->format('Y-m-d'),
            ]);

        $incoming = IncomingItem::where('item_id', $item->id)->firstOrFail();

        $this->actingAs($sarpras)
            ->post(route('sarpras.logistics.incoming.reverse', $incoming), [
                'reason' => 'Koreksi pertama',
            ])
            ->assertSessionHas('success');

        // Coba koreksi kedua kali pada baris yang sama
        $this->actingAs($sarpras)
            ->post(route('sarpras.logistics.incoming.reverse', $incoming), [
                'reason' => 'Koreksi kedua',
            ])
            ->assertSessionHasErrors('reason');
    }

    public function test_koreksi_barang_keluar_membalik_stok_dan_membuat_transaksi_pembalik(): void
    {
        $sarpras = $this->sarpras();
        $item = Item::factory()->consumable()->create(['stock' => 10]);

        $this->actingAs($sarpras)
            ->post(route('sarpras.logistics.outgoing.store'), [
                'item_id' => $item->id,
                'quantity' => 4,
                'exit_date' => now()->format('Y-m-d'),
                'reason' => 'Dipakai di lab',
            ])
            ->assertSessionHas('success');

        $outgoing = OutgoingItem::where('item_id', $item->id)->firstOrFail();
        $this->assertSame(6, $item->fresh()->stock);

        // Lakukan koreksi barang keluar
        $this->actingAs($sarpras)
            ->post(route('sarpras.logistics.outgoing.reverse', $outgoing), [
                'reason' => 'Pengeluaran dibatalkan panitia',
            ])
            ->assertSessionHas('success');

        // Stok harus berbalik bertambah 4 (6 + 4 = 10)
        $this->assertSame(10, $item->fresh()->stock);

        // Baris asli ditandai
        $outgoing->refresh();
        $this->assertTrue($outgoing->isReversed());
        $this->assertSame('Pengeluaran dibatalkan panitia', $outgoing->reversal_reason);

        // Baris baru pembalik tercatat di outgoing_items
        $reversal = OutgoingItem::where('reverses_id', $outgoing->id)->firstOrFail();
        $this->assertSame($outgoing->item_id, $reversal->item_id);
        $this->assertSame($outgoing->quantity, $reversal->quantity);
        $this->assertSame('Pengeluaran dibatalkan panitia', $reversal->reversal_reason);
        $this->assertNotNull($reversal->reversed_at);

        // ActivityLog tercatat
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'outgoing_reversed',
            'user_id' => $sarpras->id,
        ]);
    }

    public function test_koreksi_barang_keluar_ditolak_jika_sudah_dikoreksi(): void
    {
        $sarpras = $this->sarpras();
        $item = Item::factory()->consumable()->create(['stock' => 10]);

        $this->actingAs($sarpras)
            ->post(route('sarpras.logistics.outgoing.store'), [
                'item_id' => $item->id,
                'quantity' => 2,
                'exit_date' => now()->format('Y-m-d'),
                'reason' => 'Dipakai',
            ]);

        $outgoing = OutgoingItem::where('item_id', $item->id)->firstOrFail();

        $this->actingAs($sarpras)
            ->post(route('sarpras.logistics.outgoing.reverse', $outgoing), [
                'reason' => 'Koreksi pertama',
            ])
            ->assertSessionHas('success');

        // Coba koreksi lagi
        $this->actingAs($sarpras)
            ->post(route('sarpras.logistics.outgoing.reverse', $outgoing), [
                'reason' => 'Koreksi kedua',
            ])
            ->assertSessionHasErrors('reason');
    }

    public function test_hanya_sarpras_yang_dapat_mengakses_koreksi(): void
    {
        $sarpras = $this->sarpras();
        $kajur = $this->kajur();
        $item = Item::factory()->consumable()->create(['stock' => 10]);

        $this->actingAs($sarpras)
            ->post(route('sarpras.logistics.incoming.store'), [
                'item_id' => $item->id,
                'quantity' => 2,
                'source' => 'pembelian',
                'entry_date' => now()->format('Y-m-d'),
            ]);

        $incoming = IncomingItem::where('item_id', $item->id)->firstOrFail();

        $this->actingAs($kajur)
            ->post(route('sarpras.logistics.incoming.reverse', $incoming), [
                'reason' => 'Percobaan kajur',
            ])
            ->assertForbidden();
    }
}
