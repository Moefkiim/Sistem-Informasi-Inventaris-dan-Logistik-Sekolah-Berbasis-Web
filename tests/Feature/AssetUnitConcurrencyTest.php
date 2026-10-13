<?php

namespace Tests\Feature;

use App\Models\AssetUnit;
use App\Models\AssetUnitStatusHistory;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Konkurensi & konsistensi perubahan status unit aset (N1).
 *
 * Instansi model yang basi (mis. dua tab / dua sesi Sarpras) tidak boleh
 * menimpa status terbaru di database maupun mengurangi stok dua kali.
 *
 * Catatan: suite ini berbagi satu koneksi database. Uji paralel dua koneksi
 * sungguhan tidak dibuat karena bergantung pada timing dan database
 * out-of-process (SQLite in-memory tidak dapat dibuka dua koneksi). Alih-alih,
 * invarian inti diuji deterministik lewat instansi basi pada driver yang sama
 * dengan yang dijalankan di CI (SQLite, MySQL 8.0, MariaDB 10.11).
 */
class AssetUnitConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private User $sarpras;

    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sarpras = User::factory()->create(['role' => 'sarpras']);
        $this->item = Item::factory()->individual()->create(['stock' => 3]);
    }

    private function assertRejected(callable $action): void
    {
        try {
            $action();
            $this->fail('Perubahan status seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }
    }

    public function test_menghapuskan_unit_yang_sama_dua_kali_hanya_mengurangi_stok_sekali(): void
    {
        $unit = AssetUnit::factory()->for($this->item)->create(['current_status' => 'aktif']);

        // Instansi basi yang masih mengira statusnya 'aktif'.
        $stale = AssetUnit::find($unit->id);

        AssetUnit::find($unit->id)->changeStatus('disposed', 'Rusak total', $this->sarpras);
        $this->assertSame(2, $this->item->fresh()->stock);

        $this->assertRejected(fn () => $stale->changeStatus('disposed', 'Rusak total lagi', $this->sarpras));

        $this->assertSame('disposed', $unit->fresh()->current_status);
        $this->assertSame(2, $this->item->fresh()->stock);
        $this->assertSame(1, AssetUnitStatusHistory::where('asset_unit_id', $unit->id)->where('to_status', 'disposed')->count());
    }

    public function test_instansi_basi_tidak_menimpa_unit_yang_sudah_dipinjam(): void
    {
        $unit = AssetUnit::factory()->for($this->item)->create(['current_status' => 'aktif']);

        $stale = AssetUnit::find($unit->id);

        AssetUnit::find($unit->id)->applyLoanStatus('dipinjam', 'Dipinjam', $this->sarpras);

        $this->assertRejected(fn () => $stale->changeStatus('tidak_aktif', 'Coba timpa', $this->sarpras));

        $this->assertSame('dipinjam', $unit->fresh()->current_status);
    }

    public function test_pemanggilan_disposed_berulang_pada_instansi_sama_hanya_sekali(): void
    {
        $unit = AssetUnit::factory()->for($this->item)->create(['current_status' => 'aktif']);
        $stale = AssetUnit::find($unit->id);

        $stale->changeStatus('disposed', 'Rusak total', $this->sarpras);
        $this->assertSame(2, $this->item->fresh()->stock);

        $this->assertRejected(fn () => $stale->changeStatus('disposed', 'Rusak total', $this->sarpras));

        $this->assertSame(2, $this->item->fresh()->stock);
        $this->assertSame(1, AssetUnitStatusHistory::where('asset_unit_id', $unit->id)->where('to_status', 'disposed')->count());
    }

    public function test_status_yang_dipakai_adalah_status_terbaru_dari_database(): void
    {
        $unit = AssetUnit::factory()->for($this->item)->create(['current_status' => 'aktif']);
        $stale = AssetUnit::find($unit->id);

        // Baris berubah menjadi tidak_aktif lewat jalur lain.
        AssetUnit::whereKey($unit->id)->update(['current_status' => 'tidak_aktif']);

        // Dari 'tidak_aktif', transisi ke 'dalam_perbaikan' tidak diizinkan,
        // tetapi instansi basi masih mengira statusnya 'aktif'.
        $this->assertRejected(fn () => $stale->changeStatus('dalam_perbaikan', 'Servis', $this->sarpras));

        $this->assertSame('tidak_aktif', $unit->fresh()->current_status);
    }
}
