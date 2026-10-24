<?php

namespace Tests\Feature;

use App\Models\AssetUnit;
use App\Models\Item;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActionPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_kartu_tindakan_menghitung_angka_stok_dan_keterlambatan_dengan_benar_untuk_sarpras(): void
    {
        $sarpras = User::factory()->sarpras()->create();

        // 1 consumable stok normal
        Item::factory()->consumable()->create([
            'stock' => 10,
            'minimum_stock' => 2,
        ]);

        // 2 consumable stok menipis
        Item::factory()->consumable()->create([
            'stock' => 2,
            'minimum_stock' => 5,
        ]);
        Item::factory()->consumable()->create([
            'stock' => 1,
            'minimum_stock' => 1,
        ]);

        // 1 consumable stok habis
        Item::factory()->consumable()->create([
            'stock' => 0,
            'minimum_stock' => 5,
        ]);

        // 1 unit dalam perbaikan
        $item = Item::factory()->individual()->create();
        AssetUnit::factory()->create([
            'item_id' => $item->id,
            'current_status' => 'dalam_perbaikan',
        ]);

        // 1 peminjaman tepat waktu, 2 peminjaman terlambat
        Loan::factory()->create([
            'item_id' => $item->id,
            'status' => 'dipinjam',
            'due_date' => now()->addDays(2)->toDateString(),
        ]);
        Loan::factory()->create([
            'item_id' => $item->id,
            'status' => 'dipinjam',
            'due_date' => now()->subDays(2)->toDateString(),
        ]);
        Loan::factory()->create([
            'item_id' => $item->id,
            'status' => 'dipinjam',
            'due_date' => now()->subDays(5)->toDateString(),
        ]);

        $response = $this->actingAs($sarpras)->get(route('home'));

        $response->assertOk();
        $response->assertViewHas('actionCounts', function (array $counts) {
            return $counts['low_stock'] === 2
                && $counts['out_of_stock'] === 1
                && $counts['overdue_loans'] === 2
                && $counts['in_repair'] === 1;
        });

        // Konten dashboard menampilkan angka dan tautan filter
        $response->assertSee(route('sarpras.inventory.index', ['stok' => 'menipis']));
        $response->assertSee(route('sarpras.inventory.index', ['stok' => 'habis']));
        $response->assertSee(route('sarpras.loans.index', ['status' => 'terlambat']));
        $response->assertSee(route('sarpras.inventory.index', ['status' => 'dalam_perbaikan']));
    }

    public function test_kartu_tindakan_menghitung_angka_stok_dan_keterlambatan_sesuai_jurusan_untuk_kajur(): void
    {
        $kajurRpl = User::factory()->kajur()->create(['department' => 'RPL']);
        $kajurTkj = User::factory()->kajur()->create(['department' => 'TKJ']);

        // RPL: 1 menipis, 1 habis
        Item::factory()->consumable()->create([
            'department' => 'RPL',
            'stock' => 2,
            'minimum_stock' => 5,
        ]);
        Item::factory()->consumable()->create([
            'department' => 'RPL',
            'stock' => 0,
            'minimum_stock' => 5,
        ]);

        // TKJ: 3 menipis, 0 habis
        Item::factory()->consumable()->create([
            'department' => 'TKJ',
            'stock' => 1,
            'minimum_stock' => 3,
        ]);
        Item::factory()->consumable()->create([
            'department' => 'TKJ',
            'stock' => 1,
            'minimum_stock' => 3,
        ]);
        Item::factory()->consumable()->create([
            'department' => 'TKJ',
            'stock' => 1,
            'minimum_stock' => 3,
        ]);

        // Peminjaman terlambat RPL: 1, TKJ: 2
        $itemRpl = Item::factory()->individual()->create(['department' => 'RPL']);
        $itemTkj = Item::factory()->individual()->create(['department' => 'TKJ']);

        Loan::factory()->create([
            'item_id' => $itemRpl->id,
            'borrower_department' => 'RPL',
            'status' => 'dipinjam',
            'due_date' => now()->subDay()->toDateString(),
        ]);
        Loan::factory()->create([
            'item_id' => $itemTkj->id,
            'borrower_department' => 'TKJ',
            'status' => 'dipinjam',
            'due_date' => now()->subDay()->toDateString(),
        ]);
        Loan::factory()->create([
            'item_id' => $itemTkj->id,
            'borrower_department' => 'TKJ',
            'status' => 'dipinjam',
            'due_date' => now()->subDays(2)->toDateString(),
        ]);

        // Uji Kajur RPL
        $responseRpl = $this->actingAs($kajurRpl)->get(route('home'));
        $responseRpl->assertOk();
        $responseRpl->assertViewHas('actionCounts', function (array $counts) {
            return $counts['low_stock'] === 1
                && $counts['out_of_stock'] === 1
                && $counts['overdue_loans'] === 1;
        });

        // Uji Kajur TKJ
        $responseTkj = $this->actingAs($kajurTkj)->get(route('home'));
        $responseTkj->assertOk();
        $responseTkj->assertViewHas('actionCounts', function (array $counts) {
            return $counts['low_stock'] === 3
                && $counts['out_of_stock'] === 0
                && $counts['overdue_loans'] === 2;
        });
    }

    public function test_kajur_tanpa_jurusan_mendapat_403_pada_dashboard(): void
    {
        $kajur = User::factory()->create([
            'role' => 'kajur',
            'department' => null,
            'is_active' => true,
        ]);

        $response = $this->actingAs($kajur)->get(route('home'));

        $response->assertForbidden();
    }

    public function test_filter_stok_menipis_dan_habis_pada_daftar_inventaris_sarpras(): void
    {
        $sarpras = User::factory()->sarpras()->create();

        $normal = Item::factory()->consumable()->create(['name' => 'Kertas Normal', 'stock' => 10, 'minimum_stock' => 2]);
        $menipis = Item::factory()->consumable()->create(['name' => 'Spidol Menipis', 'stock' => 2, 'minimum_stock' => 5]);
        $habis = Item::factory()->consumable()->create(['name' => 'Tinta Habis', 'stock' => 0, 'minimum_stock' => 2]);

        // Filter menipis
        $response = $this->actingAs($sarpras)->get(route('sarpras.inventory.index', ['stok' => 'menipis']));
        $response->assertOk();
        $response->assertSee('Spidol Menipis');
        $response->assertDontSee('Kertas Normal');
        $response->assertDontSee('Tinta Habis');

        // Filter habis
        $response = $this->actingAs($sarpras)->get(route('sarpras.inventory.index', ['stok' => 'habis']));
        $response->assertOk();
        $response->assertSee('Tinta Habis');
        $response->assertDontSee('Kertas Normal');
        $response->assertDontSee('Spidol Menipis');
    }

    public function test_filter_status_unit_dalam_perbaikan_pada_daftar_inventaris_sarpras(): void
    {
        $sarpras = User::factory()->sarpras()->create();

        $itemNormal = Item::factory()->individual()->create(['name' => 'Laptop Aktif']);
        AssetUnit::factory()->create(['item_id' => $itemNormal->id, 'current_status' => 'aktif']);

        $itemRepair = Item::factory()->individual()->create(['name' => 'Proyektor Servis']);
        AssetUnit::factory()->create(['item_id' => $itemRepair->id, 'current_status' => 'dalam_perbaikan']);

        $response = $this->actingAs($sarpras)->get(route('sarpras.inventory.index', ['status' => 'dalam_perbaikan']));
        $response->assertOk();
        $response->assertSee('Proyektor Servis');
        $response->assertDontSee('Laptop Aktif');
    }

    public function test_filter_stok_menipis_dan_habis_pada_daftar_inventaris_kajur(): void
    {
        $kajur = User::factory()->kajur()->create(['department' => 'RPL']);

        Item::factory()->consumable()->create(['name' => 'Kertas RPL Normal', 'department' => 'RPL', 'stock' => 10, 'minimum_stock' => 2]);
        Item::factory()->consumable()->create(['name' => 'Spidol RPL Menipis', 'department' => 'RPL', 'stock' => 2, 'minimum_stock' => 5]);
        Item::factory()->consumable()->create(['name' => 'Tinta RPL Habis', 'department' => 'RPL', 'stock' => 0, 'minimum_stock' => 2]);

        // Filter menipis
        $response = $this->actingAs($kajur)->get(route('kajur.inventory.index', ['stok' => 'menipis']));
        $response->assertOk();
        $response->assertSee('Spidol RPL Menipis');
        $response->assertDontSee('Kertas RPL Normal');
        $response->assertDontSee('Tinta RPL Habis');

        // Filter habis
        $response = $this->actingAs($kajur)->get(route('kajur.inventory.index', ['stok' => 'habis']));
        $response->assertOk();
        $response->assertSee('Tinta RPL Habis');
        $response->assertDontSee('Kertas RPL Normal');
        $response->assertDontSee('Spidol RPL Menipis');
    }

    public function test_filter_terlambat_pada_daftar_peminjaman_sarpras(): void
    {
        $sarpras = User::factory()->sarpras()->create();
        $item = Item::factory()->individual()->create();

        $loanOnTime = Loan::factory()->create([
            'item_id' => $item->id,
            'borrower_name' => 'Peminjam Tepat Waktu',
            'status' => 'dipinjam',
            'due_date' => now()->addDays(3)->toDateString(),
        ]);

        $loanOverdue = Loan::factory()->create([
            'item_id' => $item->id,
            'borrower_name' => 'Peminjam Terlambat',
            'status' => 'dipinjam',
            'due_date' => now()->subDays(3)->toDateString(),
        ]);

        $response = $this->actingAs($sarpras)->get(route('sarpras.loans.index', ['status' => 'terlambat']));
        $response->assertOk();
        $response->assertSee('Peminjam Terlambat');
        $response->assertDontSee('Peminjam Tepat Waktu');
    }
}
