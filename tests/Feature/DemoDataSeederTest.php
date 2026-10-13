<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\AssetUnit;
use App\Models\AssetUnitStatusHistory;
use App\Models\IncomingItem;
use App\Models\Item;
use App\Models\Loan;
use App\Models\OutgoingItem;
use App\Models\Submission;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_demo_idempoten_dan_konsisten(): void
    {
        $this->seed();

        $snapshot = $this->snapshot();

        $this->seed();

        $this->assertSame($snapshot, $this->snapshot(), 'Seeding dua kali harus menghasilkan jumlah baris yang sama (idempoten).');
    }

    public function test_setiap_barang_individual_punya_unit_sebanyak_stok(): void
    {
        $this->seed();

        $individual = Item::where('item_type', 'individual')->get();
        $this->assertNotEmpty($individual);

        foreach ($individual as $item) {
            $nonDisposed = $item->assetUnits()->where('current_status', '!=', 'disposed')->count();
            $this->assertSame(
                $item->stock,
                $nonDisposed,
                "Jumlah unit non-disposed barang {$item->code} harus sama dengan stok."
            );
        }
    }

    public function test_demo_inventory_memiliki_kondisi_dan_status_unit_baru(): void
    {
        $this->seed();

        $this->assertTrue(AssetUnit::where('current_condition', 'tidak_layak_pakai')->exists());
        $this->assertTrue(AssetUnit::where('current_condition', 'hilang')->exists());
        $this->assertTrue(AssetUnit::where('current_status', 'disposed')->exists());
        $this->assertTrue(AssetUnitStatusHistory::exists());
    }

    public function test_stok_tidak_pernah_negatif_dan_konsisten_dengan_transaksi(): void
    {
        $this->seed();

        foreach (Item::all() as $item) {
            $this->assertGreaterThanOrEqual(0, $item->stock, "Stok {$item->code} tidak boleh negatif.");
        }

        $kertas = Item::where('code', 'BRG-UMM-0001')->firstOrFail();
        $masuk = (int) IncomingItem::where('item_id', $kertas->id)->sum('quantity');
        $keluar = (int) OutgoingItem::where('item_id', $kertas->id)->sum('quantity');
        $this->assertSame($masuk, 25);
        $this->assertSame($keluar, 3);
        $this->assertSame(18, $kertas->stock);
        $this->assertSame('aman', $kertas->stockStatus());
    }

    public function test_akun_dasar_dan_penanda_stok_sesuai(): void
    {
        $this->seed();

        foreach (['kajur_rpl', 'sarpras', 'kepsek', 'user_nonaktif', 'kajur_tkj'] as $username) {
            $this->assertDatabaseHas('users', ['username' => $username]);
        }

        $this->assertSame('menipis', Item::where('code', 'BRG-UMM-0002')->firstOrFail()->stockStatus());
        $this->assertSame('habis', Item::where('code', 'BRG-UMM-0003')->firstOrFail()->stockStatus());
    }

    public function test_pengajuan_mencakup_semua_status_penting(): void
    {
        $this->seed();

        foreach (['draft', 'submitted', 'reviewed_sarpras', 'approved', 'rejected', 'realized'] as $status) {
            $this->assertTrue(
                Submission::where('status', $status)->exists(),
                "Harus ada minimal satu pengajuan berstatus {$status}."
            );
        }

        // Minimal satu menunggu Sarpras dan satu menunggu Kepala Sekolah.
        $this->assertTrue(Submission::where('status', 'submitted')->exists());
        $this->assertTrue(Submission::where('status', 'reviewed_sarpras')->exists());
    }

    public function test_peminjaman_mencakup_semua_status_dan_unit_terkait(): void
    {
        $this->seed();

        foreach (['menunggu', 'disetujui', 'dipinjam', 'dikembalikan', 'terlambat'] as $status) {
            $this->assertTrue(
                Loan::where('status', $status)->exists(),
                "Harus ada minimal satu peminjaman berstatus {$status}."
            );
        }

        $dipinjam = Loan::where('status', 'dipinjam')->firstOrFail();
        $this->assertNotNull($dipinjam->asset_unit_id);
        $this->assertSame('dipinjam', AssetUnit::find($dipinjam->asset_unit_id)->current_status);
    }

    public function test_pengajuan_terealisasi_tertaut_barang_masuk_dan_inventaris(): void
    {
        $this->seed();

        $realized = Submission::where('status', 'realized')->firstOrFail();

        $this->assertGreaterThan(0, $realized->incomingItems()->count());
        $this->assertDatabaseHas('submissions', [
            'id' => $realized->id,
            'realized_by' => User::where('username', 'sarpras')->value('id'),
        ]);

        foreach ($realized->load('items.item')->items as $submissionItem) {
            $this->assertNotNull($submissionItem->item_id, 'Baris pengajuan harus tertaut ke master barang.');
            $this->assertSame($submissionItem->quantity, $submissionItem->received_quantity);

            $masuk = (int) IncomingItem::where('submission_id', $realized->id)
                ->where('item_id', $submissionItem->item_id)
                ->sum('quantity');
            $this->assertSame($submissionItem->quantity, $masuk);

            $item = $submissionItem->item;
            if ($item->isIndividual()) {
                $this->assertSame($submissionItem->quantity, $item->assetUnits()->count());
                $this->assertSame($submissionItem->quantity, $item->stock);
            } else {
                $this->assertSame($submissionItem->quantity, $item->stock);
            }
        }
    }

    public function test_activity_log_terisi_untuk_audit_trail(): void
    {
        $this->seed();

        $this->assertGreaterThan(0, ActivityLog::count());
        $this->assertDatabaseHas('activity_logs', ['action' => 'submission_approved']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'loan_returned']);
    }

    public function test_data_demo_tidak_dibuat_di_lingkungan_produksi(): void
    {
        $this->app['env'] = 'production';

        $this->app->make(DatabaseSeeder::class)->run();

        $this->assertSame(0, Item::count(), 'Seeder demo tidak boleh menanam data di produksi.');
        $this->assertGreaterThan(0, User::count(), 'Akun dasar tetap harus dibuat di produksi.');

        $this->app['env'] = 'testing';
    }

    public function test_data_demo_dibuat_di_produksi_bila_diizinkan_eksplisit(): void
    {
        $this->app['env'] = 'production';
        putenv('SIILS_ALLOW_DEMO_SEED=true');
        $_ENV['SIILS_ALLOW_DEMO_SEED'] = 'true';
        $_SERVER['SIILS_ALLOW_DEMO_SEED'] = 'true';

        $this->app->make(DatabaseSeeder::class)->run();

        $this->assertGreaterThan(0, Item::count());

        putenv('SIILS_ALLOW_DEMO_SEED');
        unset($_ENV['SIILS_ALLOW_DEMO_SEED'], $_SERVER['SIILS_ALLOW_DEMO_SEED']);
        $this->app['env'] = 'testing';
    }

    /**
     * @return array<string,int>
     */
    private function snapshot(): array
    {
        return [
            'users' => User::count(),
            'items' => Item::count(),
            'units' => AssetUnit::count(),
            'incoming' => IncomingItem::count(),
            'outgoing' => OutgoingItem::count(),
            'submissions' => Submission::count(),
            'loans' => Loan::count(),
            'logs' => ActivityLog::count(),
        ];
    }
}
