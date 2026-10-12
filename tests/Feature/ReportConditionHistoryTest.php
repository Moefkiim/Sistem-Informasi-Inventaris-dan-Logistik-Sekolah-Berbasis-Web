<?php

namespace Tests\Feature;

use App\Models\ConditionHistory;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportConditionHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function sarpras(): User
    {
        return User::factory()->sarpras()->create();
    }

    private function history(array $attributes = []): ConditionHistory
    {
        return ConditionHistory::create(array_merge([
            'item_id' => Item::factory()->create(['department' => 'RPL'])->id,
            'user_id' => User::factory()->sarpras()->create()->id,
            'from_condition' => 'baik',
            'to_condition' => 'rusak_ringan',
            'recorded_at' => '2026-10-01 08:00:00',
            'notes' => 'Terjadi pada praktik',
        ], $attributes));
    }

    public function test_laporan_riwayat_kondisi_menampilkan_kolom(): void
    {
        $history = $this->history();

        $response = $this->actingAs($this->sarpras())
            ->get(route('reports.index', ['type' => 'condition']));

        $response->assertOk()
            ->assertSee('Riwayat Kondisi Barang')
            ->assertSee('Dari')
            ->assertSee('Ke')
            ->assertSee('Pencatat')
            ->assertSee('Terjadi pada praktik');
    }

    public function test_kajur_hanya_melihat_perubahan_kondisi_jurusannya(): void
    {
        $kajur = User::factory()->kajur()->create(['department' => 'RPL']);
        $rpl = $this->history(['item_id' => Item::factory()->create(['department' => 'RPL', 'name' => 'Barang RPL Kondisi'])->id]);
        $tkj = $this->history(['item_id' => Item::factory()->create(['department' => 'TKJ', 'name' => 'Barang TKJ Kondisi'])->id]);

        $response = $this->actingAs($kajur)
            ->get(route('reports.index', ['type' => 'condition']));

        $response->assertOk()
            ->assertSee('Barang RPL Kondisi')
            ->assertDontSee('Barang TKJ Kondisi');
    }

    public function test_kepala_sekolah_hanya_membaca_laporan_riwayat_kondisi(): void
    {
        $this->history();

        $this->actingAs(User::factory()->kepalaSekolah()->create())
            ->get(route('reports.index', ['type' => 'condition']))
            ->assertOk();
    }

    public function test_filter_kondisi_tujuan(): void
    {
        $ringan = $this->history(['to_condition' => 'rusak_ringan']);
        $berat = $this->history(['to_condition' => 'rusak_berat']);

        $response = $this->actingAs($this->sarpras())
            ->get(route('reports.index', ['type' => 'condition', 'condition' => 'rusak_berat']));

        $response->assertOk()
            ->assertSee($berat->item->name)
            ->assertDontSee($ringan->item->name);
    }

    public function test_filter_periode_perubahan(): void
    {
        $september = $this->history(['recorded_at' => '2026-09-15 10:00:00', 'item_id' => Item::factory()->create(['department' => 'RPL', 'name' => 'Barang Sep'])->id]);
        $oktober = $this->history(['recorded_at' => '2026-10-15 10:00:00', 'item_id' => Item::factory()->create(['department' => 'RPL', 'name' => 'Barang Okt'])->id]);

        $response = $this->actingAs($this->sarpras())
            ->get(route('reports.index', [
                'type' => 'condition',
                'start_date' => '2026-09-01',
                'end_date' => '2026-09-30',
            ]));

        $response->assertOk()
            ->assertSee('Barang Sep')
            ->assertDontSee('Barang Okt');
    }

    public function test_hari_terlambat_kolom_peminjaman_ikut_filter_dan_ekspor(): void
    {
        $this->history();

        $index = $this->actingAs($this->sarpras())
            ->get(route('reports.index', ['type' => 'condition', 'condition' => 'rusak_ringan']));
        $index->assertOk();
        $html = $index->getContent();
        $this->assertStringContainsString('type=condition', $html);
        $this->assertStringContainsString('condition=rusak_ringan', $html);
        $this->assertStringContainsString('export=print', $html);

        $pdf = $this->actingAs($this->sarpras())
            ->get(route('reports.pdf', ['type' => 'condition', 'condition' => 'rusak_ringan']));
        $pdf->assertOk();
        $this->assertStringContainsString('application/pdf', $pdf->headers->get('content-type'));

        $excel = $this->actingAs($this->sarpras())
            ->get(route('reports.excel', ['type' => 'condition', 'condition' => 'rusak_ringan']));
        $excel->assertOk();
        $this->assertStringContainsString('laporan-condition-', $excel->headers->get('content-disposition'));

        $print = $this->actingAs($this->sarpras())
            ->get(route('reports.index', ['type' => 'condition', 'condition' => 'rusak_ringan', 'export' => 'print']));
        $print->assertOk()->assertSee('Ke Kondisi');
    }

    public function test_pencatat_tercantum_pada_laporan(): void
    {
        $user = User::factory()->sarpras()->create(['name' => 'Petugas Kondisi Uji']);
        $this->history(['user_id' => $user->id]);

        $this->actingAs($this->sarpras())
            ->get(route('reports.index', ['type' => 'condition']))
            ->assertOk()
            ->assertSee('Petugas Kondisi Uji');
    }
}
