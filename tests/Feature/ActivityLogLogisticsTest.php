<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Document;
use App\Models\Item;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * PR-3: F3 — Audit trail untuk logistik, lokasi, dan dokumen.
 */
class ActivityLogLogisticsTest extends TestCase
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

    private function kepalaSekolah(): User
    {
        return User::factory()->kepalaSekolah()->create();
    }

    public function test_barang_masuk_mencatat_activity_log(): void
    {
        $sarpras = $this->sarpras();
        $item = Item::factory()->consumable()->create(['name' => 'Kertas A4', 'stock' => 10]);

        $this->actingAs($sarpras)
            ->post(route('sarpras.logistics.incoming.store'), [
                'item_id' => $item->id,
                'quantity' => 5,
                'source' => 'pembelian',
                'entry_date' => now()->format('Y-m-d'),
                'notes' => 'Pengadaan rutin',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'incoming_recorded',
            'user_id' => $sarpras->id,
        ]);

        $log = ActivityLog::where('action', 'incoming_recorded')->firstOrFail();
        $this->assertStringContainsString('Kertas A4', $log->description);
        $this->assertStringContainsString('5', $log->description);
    }

    public function test_barang_keluar_mencatat_activity_log(): void
    {
        $sarpras = $this->sarpras();
        $item = Item::factory()->consumable()->create(['name' => 'Spidol Boardmarker', 'stock' => 10]);

        $this->actingAs($sarpras)
            ->post(route('sarpras.logistics.outgoing.store'), [
                'item_id' => $item->id,
                'quantity' => 3,
                'exit_date' => now()->format('Y-m-d'),
                'reason' => 'Dipakai di kelas',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'outgoing_recorded',
            'user_id' => $sarpras->id,
        ]);

        $log = ActivityLog::where('action', 'outgoing_recorded')->firstOrFail();
        $this->assertStringContainsString('Spidol Boardmarker', $log->description);
        $this->assertStringContainsString('3', $log->description);
    }

    public function test_distribusi_mencatat_activity_log(): void
    {
        $sarpras = $this->sarpras();
        $item = Item::factory()->consumable()->create(['name' => 'Sapu Ijuk', 'stock' => 10]);
        $location = Location::factory()->create(['name' => 'Ruang Guru 1']);

        $this->actingAs($sarpras)
            ->post(route('sarpras.logistics.distributions.store'), [
                'item_id' => $item->id,
                'quantity' => 2,
                'to_location_id' => $location->id,
                'distribution_date' => now()->format('Y-m-d'),
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'distribution_recorded',
            'user_id' => $sarpras->id,
        ]);

        $log = ActivityLog::where('action', 'distribution_recorded')->firstOrFail();
        $this->assertStringContainsString('Sapu Ijuk', $log->description);
        $this->assertStringContainsString('Ruang Guru 1', $log->description);
    }

    public function test_lokasi_dibuat_dan_dihapus_mencatat_activity_log(): void
    {
        $sarpras = $this->sarpras();

        $this->actingAs($sarpras)
            ->post(route('sarpras.locations.store'), [
                'code' => 'LOK-NEW-01',
                'name' => 'Lab Komputer Baru',
                'building' => 'Gedung B',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'location_created',
            'user_id' => $sarpras->id,
        ]);

        $location = Location::where('code', 'LOK-NEW-01')->firstOrFail();

        $this->actingAs($sarpras)
            ->delete(route('sarpras.locations.destroy', $location))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'location_deleted',
            'user_id' => $sarpras->id,
        ]);
    }

    public function test_dokumen_diunggah_dan_dihapus_mencatat_activity_log(): void
    {
        Storage::fake('local');
        $sarpras = $this->sarpras();

        $file = UploadedFile::fake()->create('bast-surat.pdf', 100, 'application/pdf');

        $this->actingAs($sarpras)
            ->post(route('documents.store'), [
                'title' => 'BAST Pengadaan 2026',
                'category' => 'BAST',
                'file' => $file,
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'document_uploaded',
            'user_id' => $sarpras->id,
        ]);

        $document = Document::where('title', 'BAST Pengadaan 2026')->firstOrFail();

        $this->actingAs($sarpras)
            ->delete(route('documents.destroy', $document))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'document_deleted',
            'user_id' => $sarpras->id,
        ]);
    }

    public function test_akses_halaman_activity_logs_hanya_sarpras(): void
    {
        $sarpras = $this->sarpras();
        $kajur = $this->kajur();
        $kepsek = $this->kepalaSekolah();

        $this->actingAs($sarpras)
            ->get(route('sarpras.activity_logs.index'))
            ->assertOk();

        $this->actingAs($kajur)
            ->get(route('sarpras.activity_logs.index'))
            ->assertForbidden();

        $this->actingAs($kepsek)
            ->get(route('sarpras.activity_logs.index'))
            ->assertForbidden();
    }
}
