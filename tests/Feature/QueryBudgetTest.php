<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\AssetUnit;
use App\Models\ConditionHistory;
use App\Models\Distribution;
use App\Models\IncomingItem;
use App\Models\Item;
use App\Models\Loan;
use App\Models\Location;
use App\Models\OutgoingItem;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Anggaran query: jumlah query untuk halaman-halaman utama harus TETAP sama
 * saat jumlah baris data bertambah (5 baris vs 25 baris). Bila jumlah query
 * ikut bertambah, berarti halaman tersebut mengalami N+1 dan test ini gagal.
 *
 * Pengukuran memakai DB::listen (bukan waktu eksekusi) sehingga stabil di CI.
 *
 * Angka batas query terukur per halaman (identik pada 5 dan 25 baris):
 * - Dashboard (kajur/sarpras/kepsek): 14 query
 * - Daftar inventaris: 7 query  ·  Detail barang: 15 query
 * - Daftar peminjaman: 8 query
 * - Riwayat aktivitas: 7 query  ·  Notifikasi: 5 query
 * - Laporan: inventaris 12 · incoming 10 · outgoing 10 · distribution 11
 *   · submission 11 · loan 11 · condition 10 query
 *
 * Angka-angka ini adalah plafon anggaran: bila suatu perubahan menambah query
 * per baris data, test akan gagal karena jumlah 5 baris != 25 baris.
 */
class QueryBudgetTest extends TestCase
{
    use RefreshDatabase;

    private int $queries = 0;

    private int $submissionSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        DB::listen(function (): void {
            $this->queries++;
        });
    }

    /**
     * Hitung jumlah query yang dieksekusi selama satu request HTTP.
     */
    private function countQueries(\Closure $request): int
    {
        $this->queries = 0;
        $request();

        return $this->queries;
    }

    private function assertStable(string $label, int $fiveRows, int $twentyFiveRows): void
    {
        $this->assertGreaterThan(0, $fiveRows, "{$label}: request 5 baris tidak mengeksekusi query.");
        $this->assertSame(
            $fiveRows,
            $twentyFiveRows,
            "{$label}: jumlah query berubah (N+1) saat data tumbuh — 5 baris={$fiveRows}, 25 baris={$twentyFiveRows}."
        );
    }

    // ==== Penyiapan data terkontrol ====

    private function makeItems(int $count, ?Location $location, string $department = 'RPL'): void
    {
        for ($i = 0; $i < $count; $i++) {
            $item = Item::factory()->individual()->create([
                'department' => $department,
                'location_id' => $location?->id,
                'stock' => 1,
                'current_status' => 'aktif',
                'current_condition' => 'baik',
            ]);

            AssetUnit::factory()->create([
                'item_id' => $item->id,
                'location_id' => $location?->id,
                'current_status' => 'aktif',
                'current_condition' => 'baik',
            ]);
        }
    }

    private function makeLoans(int $count, User $recorder): void
    {
        Loan::factory()->count($count)->create([
            'recorded_by' => $recorder->id,
        ]);
    }

    private function makeSubmissions(int $count, User $kajur): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->submissionSequence++;

            $submission = Submission::create([
                'submission_number' => 'REQ-QB-'.str_pad((string) $this->submissionSequence, 5, '0', STR_PAD_LEFT),
                'user_id' => $kajur->id,
                'department' => $kajur->department,
                'title' => 'Pengajuan uji '.$this->submissionSequence,
                'purpose' => 'Pengukuran anggaran query',
            ]);
            $submission->status = 'submitted';
            $submission->save();

            $submission->items()->create([
                'item_name' => 'Barang Uji '.$i,
                'quantity' => 1,
                'unit' => 'Pcs',
                'estimated_price' => 1000,
                'specification' => '-',
            ]);
        }
    }

    private function makeActivityLogs(int $count, User $actor): void
    {
        for ($i = 0; $i < $count; $i++) {
            ActivityLog::create([
                'user_id' => $actor->id,
                'user_name' => $actor->name,
                'user_role' => $actor->role,
                'action' => 'draft',
                'description' => 'Aktivitas uji '.$i,
                'logged_at' => now(),
            ]);
        }
    }

    private function makeNotifications(int $count, User $user): void
    {
        for ($i = 0; $i < $count; $i++) {
            $user->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => 'App\\Notifications\\QueryBudget',
                'data' => ['message' => 'Notifikasi uji '.$i, 'url' => '/home'],
                'read_at' => null,
            ]);
        }
    }

    private function makeConditionHistories(int $count, Item $item, User $actor): void
    {
        for ($i = 0; $i < $count; $i++) {
            ConditionHistory::create([
                'item_id' => $item->id,
                'asset_unit_id' => null,
                'from_condition' => 'baik',
                'to_condition' => $i % 2 === 0 ? 'rusak_ringan' : 'baik',
                'user_id' => $actor->id,
                'recorded_at' => now(),
            ]);
        }
    }

    private function makeIncoming(int $count, Item $item, User $actor): void
    {
        for ($i = 0; $i < $count; $i++) {
            IncomingItem::create([
                'transaction_number' => 'IN-QB-'.Str::upper(Str::random(8)),
                'item_id' => $item->id,
                'quantity' => 1,
                'source' => 'pembelian',
                'entry_date' => now()->toDateString(),
                'user_id' => $actor->id,
            ]);
        }
    }

    private function makeOutgoing(int $count, Item $item, User $actor): void
    {
        for ($i = 0; $i < $count; $i++) {
            OutgoingItem::create([
                'transaction_number' => 'OUT-QB-'.Str::upper(Str::random(8)),
                'item_id' => $item->id,
                'quantity' => 1,
                'exit_date' => now()->toDateString(),
                'reason' => 'Pemakaian uji',
                'user_id' => $actor->id,
            ]);
        }
    }

    private function makeDistributions(int $count, Item $item, Location $location, User $actor): void
    {
        for ($i = 0; $i < $count; $i++) {
            Distribution::create([
                'distribution_number' => 'DS-QB-'.Str::upper(Str::random(8)),
                'item_id' => $item->id,
                'quantity' => 1,
                'to_location_id' => $location->id,
                'recipient_department' => 'RPL',
                'recipient_name' => 'Penerima Uji',
                'distribution_date' => now()->toDateString(),
                'user_id' => $actor->id,
            ]);
        }
    }

    // ==== Pengujian per halaman ====

    public function test_dashboard_jumlah_query_stabil_untuk_setiap_peran(): void
    {
        $location = Location::factory()->create();
        $kajur = User::factory()->kajur()->create();
        $sarpras = User::factory()->sarpras()->create();
        $kepsek = User::factory()->kepalaSekolah()->create();

        $this->makeItems(5, $location);

        $five = [
            'kajur' => $this->countQueries(fn () => $this->actingAs($kajur)->get(route('home'))->assertOk()),
            'sarpras' => $this->countQueries(fn () => $this->actingAs($sarpras)->get(route('home'))->assertOk()),
            'kepsek' => $this->countQueries(fn () => $this->actingAs($kepsek)->get(route('home'))->assertOk()),
        ];

        $this->makeItems(20, $location);

        $twentyFive = [
            'kajur' => $this->countQueries(fn () => $this->actingAs($kajur)->get(route('home'))->assertOk()),
            'sarpras' => $this->countQueries(fn () => $this->actingAs($sarpras)->get(route('home'))->assertOk()),
            'kepsek' => $this->countQueries(fn () => $this->actingAs($kepsek)->get(route('home'))->assertOk()),
        ];

        foreach (['kajur', 'sarpras', 'kepsek'] as $role) {
            $this->assertStable("Dashboard {$role}", $five[$role], $twentyFive[$role]);
        }
    }

    public function test_daftar_inventaris_jumlah_query_stabil(): void
    {
        $location = Location::factory()->create();
        $sarpras = User::factory()->sarpras()->create();

        $this->makeItems(5, $location);
        $five = $this->countQueries(fn () => $this->actingAs($sarpras)->get(route('sarpras.inventory.index'))->assertOk());

        $this->makeItems(20, $location);
        $twentyFive = $this->countQueries(fn () => $this->actingAs($sarpras)->get(route('sarpras.inventory.index'))->assertOk());

        $this->assertStable('Daftar inventaris', $five, $twentyFive);
    }

    public function test_detail_barang_jumlah_query_stabil_saat_riwayat_bertambah(): void
    {
        $location = Location::factory()->create();
        $sarpras = User::factory()->sarpras()->create();

        $item = Item::factory()->individual()->create([
            'department' => 'RPL',
            'location_id' => $location->id,
            'stock' => 1,
        ]);
        $item->assetUnits()->createMany(
            $this->unitAttributes(5, $location)
        );
        $this->makeConditionHistories(5, $item, $sarpras);

        $five = $this->countQueries(fn () => $this->actingAs($sarpras)->get(route('sarpras.inventory.show', $item))->assertOk());

        $item->assetUnits()->createMany($this->unitAttributes(20, $location));
        $this->makeConditionHistories(20, $item, $sarpras);

        $twentyFive = $this->countQueries(fn () => $this->actingAs($sarpras)->get(route('sarpras.inventory.show', $item))->assertOk());

        $this->assertStable('Detail barang', $five, $twentyFive);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function unitAttributes(int $count, Location $location): array
    {
        $units = [];
        for ($i = 0; $i < $count; $i++) {
            $units[] = [
                'unit_inventory_number' => 'UNIT-QB-'.Str::upper(Str::random(8)),
                'current_condition' => 'baik',
                'current_status' => 'aktif',
                'location_id' => $location->id,
            ];
        }

        return $units;
    }

    public function test_daftar_peminjaman_jumlah_query_stabil(): void
    {
        $sarpras = User::factory()->sarpras()->create();

        $this->makeLoans(5, $sarpras);
        $five = $this->countQueries(fn () => $this->actingAs($sarpras)->get(route('sarpras.loans.index'))->assertOk());

        $this->makeLoans(20, $sarpras);
        $twentyFive = $this->countQueries(fn () => $this->actingAs($sarpras)->get(route('sarpras.loans.index'))->assertOk());

        $this->assertStable('Daftar peminjaman', $five, $twentyFive);
    }

    public function test_riwayat_aktivitas_jumlah_query_stabil(): void
    {
        $sarpras = User::factory()->sarpras()->create();

        $this->makeActivityLogs(5, $sarpras);
        $five = $this->countQueries(fn () => $this->actingAs($sarpras)->get(route('sarpras.activity_logs.index'))->assertOk());

        $this->makeActivityLogs(20, $sarpras);
        $twentyFive = $this->countQueries(fn () => $this->actingAs($sarpras)->get(route('sarpras.activity_logs.index'))->assertOk());

        $this->assertStable('Riwayat aktivitas', $five, $twentyFive);
    }

    public function test_notifikasi_jumlah_query_stabil(): void
    {
        $sarpras = User::factory()->sarpras()->create();

        $this->makeNotifications(5, $sarpras);
        $five = $this->countQueries(fn () => $this->actingAs($sarpras)->get(route('notifications.index'))->assertOk());

        $this->makeNotifications(20, $sarpras);
        $twentyFive = $this->countQueries(fn () => $this->actingAs($sarpras)->get(route('notifications.index'))->assertOk());

        $this->assertStable('Notifikasi', $five, $twentyFive);
    }

    public function test_laporan_inventaris_jumlah_query_stabil(): void
    {
        $location = Location::factory()->create();
        $sarpras = User::factory()->sarpras()->create();

        $this->makeItems(5, $location);
        $five = $this->countQueries(fn () => $this->actingAs($sarpras)->get(route('reports.index', ['type' => 'inventory']))->assertOk());

        $this->makeItems(20, $location);
        $twentyFive = $this->countQueries(fn () => $this->actingAs($sarpras)->get(route('reports.index', ['type' => 'inventory']))->assertOk());

        $this->assertStable('Laporan inventaris', $five, $twentyFive);
    }

    public function test_laporan_transaksi_logistik_jumlah_query_stabil(): void
    {
        $location = Location::factory()->create();
        $sarpras = User::factory()->sarpras()->create();
        $item = Item::factory()->individual()->create([
            'department' => 'RPL',
            'location_id' => $location->id,
        ]);

        $this->makeIncoming(5, $item, $sarpras);
        $this->makeOutgoing(5, $item, $sarpras);
        $this->makeDistributions(5, $item, $location, $sarpras);

        $five = [
            'incoming' => $this->countQueries(fn () => $this->actingAs($sarpras)->get(route('reports.index', ['type' => 'incoming']))->assertOk()),
            'outgoing' => $this->countQueries(fn () => $this->actingAs($sarpras)->get(route('reports.index', ['type' => 'outgoing']))->assertOk()),
            'distribution' => $this->countQueries(fn () => $this->actingAs($sarpras)->get(route('reports.index', ['type' => 'distribution']))->assertOk()),
        ];

        $this->makeIncoming(20, $item, $sarpras);
        $this->makeOutgoing(20, $item, $sarpras);
        $this->makeDistributions(20, $item, $location, $sarpras);

        $twentyFive = [
            'incoming' => $this->countQueries(fn () => $this->actingAs($sarpras)->get(route('reports.index', ['type' => 'incoming']))->assertOk()),
            'outgoing' => $this->countQueries(fn () => $this->actingAs($sarpras)->get(route('reports.index', ['type' => 'outgoing']))->assertOk()),
            'distribution' => $this->countQueries(fn () => $this->actingAs($sarpras)->get(route('reports.index', ['type' => 'distribution']))->assertOk()),
        ];

        foreach (['incoming', 'outgoing', 'distribution'] as $type) {
            $this->assertStable("Laporan {$type}", $five[$type], $twentyFive[$type]);
        }
    }

    public function test_laporan_pengajuan_peminjaman_dan_kondisi_jumlah_query_stabil(): void
    {
        $location = Location::factory()->create();
        $sarpras = User::factory()->sarpras()->create();
        $kajur = User::factory()->kajur()->create();
        $item = Item::factory()->individual()->create([
            'department' => 'RPL',
            'location_id' => $location->id,
        ]);

        $this->makeSubmissions(5, $kajur);
        $this->makeLoans(5, $sarpras);
        $this->makeConditionHistories(5, $item, $sarpras);

        $five = [
            'submission' => $this->countQueries(fn () => $this->actingAs($sarpras)->get(route('reports.index', ['type' => 'submission']))->assertOk()),
            'loan' => $this->countQueries(fn () => $this->actingAs($sarpras)->get(route('reports.index', ['type' => 'loan']))->assertOk()),
            'condition' => $this->countQueries(fn () => $this->actingAs($sarpras)->get(route('reports.index', ['type' => 'condition']))->assertOk()),
        ];

        $this->makeSubmissions(20, $kajur);
        $this->makeLoans(20, $sarpras);
        $this->makeConditionHistories(20, $item, $sarpras);

        $twentyFive = [
            'submission' => $this->countQueries(fn () => $this->actingAs($sarpras)->get(route('reports.index', ['type' => 'submission']))->assertOk()),
            'loan' => $this->countQueries(fn () => $this->actingAs($sarpras)->get(route('reports.index', ['type' => 'loan']))->assertOk()),
            'condition' => $this->countQueries(fn () => $this->actingAs($sarpras)->get(route('reports.index', ['type' => 'condition']))->assertOk()),
        ];

        foreach (['submission', 'loan', 'condition'] as $type) {
            $this->assertStable("Laporan {$type}", $five[$type], $twentyFive[$type]);
        }
    }
}
