<?php

namespace Tests\Feature;

use App\Models\AssetUnit;
use App\Models\AssetUnitStatusHistory;
use App\Models\Item;
use App\Models\Location;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Stage 4A: Siklus hidup unit aset (S1).
 *
 * Verifikasi:
 * 1. Sarpras dapat mengubah status unit dengan alasan (riwayat append-only).
 * 2. Transisi yang tidak diizinkan / unit dipinjam / unit disposed ditolak.
 * 3. Penghapusan aset (disposed) memerlukan alasan + konfirmasi, mengurangi
 *    stok master tanpa menghapus baris unit, dan bersifat final.
 * 4. Kondisi 'hilang' / 'tidak_layak_pakai' otomatis mengubah status ke
 *    'tidak_aktif' (selama unit tidak sedang dipinjam).
 * 5. Hak akses: hanya Sarpras yang dapat mengubah status unit.
 * 6. Unit disposed disembunyikan dari daftar/laporan kecuali filter dipilih.
 */
class AssetUnitStatusLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $sarpras;

    private User $kajur;

    private User $kepalaSekolah;

    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sarpras = User::factory()->create(['role' => 'sarpras']);
        $this->kajur = User::factory()->create(['role' => 'kajur', 'department' => 'RPL']);
        $this->kepalaSekolah = User::factory()->create(['role' => 'kepala_sekolah']);
        $this->item = Item::factory()->individual()->create(['stock' => 3]);
        Location::factory()->create(['name' => 'Gudang Sarpras', 'code' => 'GDG-SARPRAS']);
    }

    private function postStatus(AssetUnit $unit, string $toStatus, array $extra = [], ?User $actor = null): TestResponse
    {
        return $this->actingAs($actor ?? $this->sarpras)
            ->post(route('sarpras.inventory.updateStatus', $this->item), array_merge([
                'asset_unit_id' => $unit->id,
                'to_status' => $toStatus,
                'reason' => 'Pengecekan fisik rutin',
            ], $extra));
    }

    // =========================================================================
    // 1. Transisi valid + riwayat append-only
    // =========================================================================

    public function test_sarpras_mengubah_status_aktif_ke_dalam_perbaikan(): void
    {
        $unit = AssetUnit::factory()->for($this->item)->create(['current_status' => 'aktif']);

        $this->postStatus($unit, 'dalam_perbaikan', ['reason' => 'Komponen perlu servis'])->assertRedirect();

        $this->assertSame('dalam_perbaikan', $unit->fresh()->current_status);
        $this->assertDatabaseHas('asset_unit_status_histories', [
            'asset_unit_id' => $unit->id,
            'item_id' => $this->item->id,
            'from_status' => 'aktif',
            'to_status' => 'dalam_perbaikan',
            'reason' => 'Komponen perlu servis',
            'user_id' => $this->sarpras->id,
        ]);
        // Agregat master mengikuti seluruh unit (satu unit pada item ini).
        $this->assertSame('dalam_perbaikan', $this->item->fresh()->recalculateAggregateStatus());
    }

    public function test_transisi_valid_membalik_ke_aktif(): void
    {
        $unit = AssetUnit::factory()->for($this->item)->create(['current_status' => 'dalam_perbaikan']);

        $this->postStatus($unit, 'aktif')->assertRedirect();

        $this->assertSame('aktif', $unit->fresh()->current_status);
        $this->assertDatabaseHas('asset_unit_status_histories', [
            'asset_unit_id' => $unit->id,
            'from_status' => 'dalam_perbaikan',
            'to_status' => 'aktif',
        ]);
    }

    public function test_tidak_aktif_dapat_dikembalikan_ke_aktif(): void
    {
        $unit = AssetUnit::factory()->for($this->item)->create(['current_status' => 'tidak_aktif']);

        $this->postStatus($unit, 'aktif')->assertRedirect();

        $this->assertSame('aktif', $unit->fresh()->current_status);
    }

    // =========================================================================
    // 2. Transisi tidak valid
    // =========================================================================

    public function test_status_sama_ditolak(): void
    {
        $unit = AssetUnit::factory()->for($this->item)->create(['current_status' => 'aktif']);

        $this->postStatus($unit, 'aktif')->assertSessionHasErrors('to_status');
        $this->assertSame('aktif', $unit->fresh()->current_status);
    }

    public function test_unit_dipinjam_tidak_dapat_diubah_statusnya(): void
    {
        $unit = AssetUnit::factory()->for($this->item)->create(['current_status' => 'dipinjam']);

        $this->postStatus($unit, 'tidak_aktif')->assertSessionHasErrors('to_status');
        $this->assertSame('dipinjam', $unit->fresh()->current_status);
        $this->assertDatabaseMissing('asset_unit_status_histories', ['asset_unit_id' => $unit->id]);
    }

    public function test_unit_disposed_tidak_dapat_diubah_statusnya(): void
    {
        $unit = AssetUnit::factory()->for($this->item)->create(['current_status' => 'disposed']);

        $this->postStatus($unit, 'aktif')->assertSessionHasErrors();
        $this->assertSame('disposed', $unit->fresh()->current_status);
    }

    public function test_transisi_tidak_ada_dalam_matriks_ditolak(): void
    {
        // dari 'disposed' ke 'tidak_aktif' tidak ada di matriks.
        $unit = AssetUnit::factory()->for($this->item)->create(['current_status' => 'aktif']);

        $this->postStatus($unit, 'dipinjam')->assertSessionHasErrors('to_status');
        $this->assertSame('aktif', $unit->fresh()->current_status);
    }

    // =========================================================================
    // 3. Penghapusan aset (disposed)
    // =========================================================================

    public function test_disposed_memerlukan_konfirmasi(): void
    {
        $this->item->stock = 3;
        $this->item->save();
        $unit = AssetUnit::factory()->for($this->item)->create(['current_status' => 'aktif']);

        $this->postStatus($unit, 'disposed', ['confirmed' => 0])->assertSessionHasErrors('confirmed');

        $this->assertSame('aktif', $unit->fresh()->current_status);
        $this->assertSame(3, $this->item->fresh()->stock);
    }

    public function test_disposed_mengurangi_stok_dan_menjaga_baris_unit(): void
    {
        $this->item->stock = 3;
        $this->item->save();
        $unit = AssetUnit::factory()->for($this->item)->create(['current_status' => 'aktif']);

        $this->postStatus($unit, 'disposed', [
            'confirmed' => 1,
            'reason' => 'Aset rusak total dan tidak ekonomis diperbaiki',
        ])->assertRedirect();

        $this->assertSame('disposed', $unit->fresh()->current_status);
        // Baris unit tetap ada (tidak dihapus) demi audit trail.
        $this->assertDatabaseHas('asset_units', ['id' => $unit->id, 'current_status' => 'disposed', 'deleted_at' => null]);
        // Stok master berkurang 1 dan tidak di bawah 0.
        $this->assertSame(2, $this->item->fresh()->stock);
        $this->assertDatabaseHas('asset_unit_status_histories', [
            'asset_unit_id' => $unit->id,
            'to_status' => 'disposed',
            'reason' => 'Aset rusak total dan tidak ekonomis diperbaiki',
        ]);
    }

    public function test_disposed_dengan_stok_satu_tidak_turun_di_bawah_nol(): void
    {
        $this->item->stock = 1;
        $this->item->save();
        $unit = AssetUnit::factory()->for($this->item)->create(['current_status' => 'aktif']);

        $this->postStatus($unit, 'disposed', [
            'confirmed' => 1,
            'reason' => 'Segera ditarik dari penggunaan',
        ])->assertRedirect();

        $this->assertSame(0, $this->item->fresh()->stock);
        $this->assertSame('disposed', $unit->fresh()->current_status);
    }

    // =========================================================================
    // 4. Kondisi otomatis mengubah status
    // =========================================================================

    public function test_kondisi_hilang_membuat_unit_tidak_aktif(): void
    {
        $unit = AssetUnit::factory()->for($this->item)->create([
            'current_status' => 'aktif',
            'current_condition' => 'baik',
        ]);

        $this->actingAs($this->sarpras)
            ->post(route('sarpras.inventory.updateCondition', $this->item), [
                'asset_unit_id' => $unit->id,
                'to_condition' => 'hilang',
                'notes' => 'Rekonsiliasi fisik tahunan',
            ])
            ->assertRedirect();

        $this->assertSame('hilang', $unit->fresh()->current_condition);
        $this->assertSame('tidak_aktif', $unit->fresh()->current_status);
        $this->assertDatabaseHas('asset_unit_status_histories', [
            'asset_unit_id' => $unit->id,
            'from_status' => 'aktif',
            'to_status' => 'tidak_aktif',
            'reason' => 'Kondisi: Hilang',
        ]);
    }

    public function test_kondisi_tidak_layak_pakai_membuat_unit_tidak_aktif(): void
    {
        $unit = AssetUnit::factory()->for($this->item)->create([
            'current_status' => 'dalam_perbaikan',
            'current_condition' => 'rusak_berat',
        ]);

        $this->actingAs($this->sarpras)
            ->post(route('sarpras.inventory.updateCondition', $this->item), [
                'asset_unit_id' => $unit->id,
                'to_condition' => 'tidak_layak_pakai',
                'notes' => 'Sudah memenuhi batas umur teknis',
            ])
            ->assertRedirect();

        $this->assertSame('tidak_layak_pakai', $unit->fresh()->current_condition);
        $this->assertSame('tidak_aktif', $unit->fresh()->current_status);
    }

    public function test_kondisi_hilang_tidak_mengubah_status_unit_yang_dipinjam(): void
    {
        $unit = AssetUnit::factory()->for($this->item)->create([
            'current_status' => 'dipinjam',
            'current_condition' => 'baik',
        ]);

        $this->actingAs($this->sarpras)
            ->post(route('sarpras.inventory.updateCondition', $this->item), [
                'asset_unit_id' => $unit->id,
                'to_condition' => 'hilang',
                'notes' => 'Laporan peminjam kehilangan',
            ])
            ->assertRedirect();

        $this->assertSame('hilang', $unit->fresh()->current_condition);
        // Status tetap dipinjam; penyelesaian melalui proses pengembalian.
        $this->assertSame('dipinjam', $unit->fresh()->current_status);
        $this->assertDatabaseMissing('asset_unit_status_histories', [
            'asset_unit_id' => $unit->id,
            'to_status' => 'tidak_aktif',
        ]);
    }

    public function test_kondisi_normal_tidak_mengubah_status(): void
    {
        $unit = AssetUnit::factory()->for($this->item)->create([
            'current_status' => 'aktif',
            'current_condition' => 'baik',
        ]);

        $this->actingAs($this->sarpras)
            ->post(route('sarpras.inventory.updateCondition', $this->item), [
                'asset_unit_id' => $unit->id,
                'to_condition' => 'rusak_ringan',
                'notes' => 'Klep lecet',
            ])
            ->assertRedirect();

        $this->assertSame('rusak_ringan', $unit->fresh()->current_condition);
        $this->assertSame('aktif', $unit->fresh()->current_status);
        $this->assertDatabaseMissing('asset_unit_status_histories', ['asset_unit_id' => $unit->id]);
    }

    // =========================================================================
    // 5. Hak akses
    // =========================================================================

    public function test_kajur_tidak_bisa_mengubah_status_unit(): void
    {
        $unit = AssetUnit::factory()->for($this->item)->create(['current_status' => 'aktif']);

        $this->postStatus($unit, 'tidak_aktif', [], $this->kajur)->assertForbidden();
        $this->assertSame('aktif', $unit->fresh()->current_status);
    }

    public function test_kepala_sekolah_tidak_bisa_mengubah_status_unit(): void
    {
        $unit = AssetUnit::factory()->for($this->item)->create(['current_status' => 'aktif']);

        $this->postStatus($unit, 'tidak_aktif', [], $this->kepalaSekolah)->assertForbidden();
        $this->assertSame('aktif', $unit->fresh()->current_status);
    }

    // =========================================================================
    // 6. Riwayat append-only
    // =========================================================================

    public function test_riwayat_status_tidak_memiliki_rute_edit_dan_hapus(): void
    {
        $rules = app('router')->getRoutes();
        $names = collect($rules->getRoutes())
            ->map(fn ($route) => $route->getName())
            ->filter(fn ($name) => $name && str_contains((string) $name, 'status_histories'))
            ->values()
            ->all();

        $this->assertSame([], $names);
    }

    public function test_model_status_history_tidak_dapat_dibuat_tanpa_status_tujuan(): void
    {
        $unit = AssetUnit::factory()->for($this->item)->create(['current_status' => 'aktif']);

        $this->expectException(QueryException::class);
        AssetUnitStatusHistory::create([
            'asset_unit_id' => $unit->id,
            'item_id' => $this->item->id,
            'from_status' => 'aktif',
            'to_status' => null,
            'user_id' => $this->sarpras->id,
        ]);
    }

    // =========================================================================
    // 7. Unit disposed disembunyikan kecuali filter dipilih
    // =========================================================================

    public function test_laporan_inventaris_menyembunyikan_unit_disposed_tanpa_filter(): void
    {
        $unit = AssetUnit::factory()->for($this->item)->create([
            'current_status' => 'disposed',
            'current_condition' => 'rusak_berat',
        ]);

        $url = route('reports.index', ['type' => 'inventory']);

        $this->actingAs($this->sarpras)->get($url)->assertOk()->assertDontSee($unit->unit_inventory_number);

        // Dengan filter unit_status=disposed, unit muncul.
        $filtered = route('reports.index', ['type' => 'inventory', 'unit_status' => 'disposed']);
        $this->actingAs($this->sarpras)->get($filtered)->assertOk()->assertSee($unit->unit_inventory_number);
    }

    public function test_laporan_inventaris_tetap_menampilkan_unit_non_disposed(): void
    {
        $unit = AssetUnit::factory()->for($this->item)->create(['current_status' => 'aktif']);

        $url = route('reports.index', ['type' => 'inventory']);

        $this->actingAs($this->sarpras)->get($url)->assertOk()->assertSee($unit->unit_inventory_number);
    }

    public function test_store_item_menerima_kondisi_baru(): void
    {
        $response = $this->actingAs($this->sarpras)->post(route('sarpras.inventory.store'), [
            'code' => 'BRG-NEW-0001',
            'inventory_number' => 'INV-NEW-0001',
            'name' => 'Alat Praktik Baru',
            'category' => 'Praktik',
            'item_type' => 'individual',
            'unit' => 'Unit',
            'stock' => 1,
            'minimum_stock' => 0,
            'source' => 'pembelian',
            'department' => 'RPL',
            'location_id' => null,
            'current_condition' => 'tidak_layak_pakai',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('items', [
            'code' => 'BRG-NEW-0001',
            'current_condition' => 'tidak_layak_pakai',
        ]);
    }
}
