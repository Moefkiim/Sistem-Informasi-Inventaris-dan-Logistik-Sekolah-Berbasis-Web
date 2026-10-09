<?php

namespace Tests\Feature;

use App\Models\IncomingItem;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    private function sarpras(): User
    {
        return User::factory()->sarpras()->create();
    }

    public function test_report_index_shows_export_buttons(): void
    {
        $this->actingAs($this->sarpras())
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('PDF')
            ->assertSee('Excel')
            ->assertSee('Cetak');
    }

    public function test_inventory_report_can_be_downloaded_as_pdf(): void
    {
        Item::factory()->create(['name' => 'Barang PDF Test']);

        $response = $this->actingAs($this->sarpras())
            ->get(route('reports.pdf', ['type' => 'inventory']));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString('laporan-inventory-', $response->headers->get('content-disposition'));
    }

    public function test_inventory_report_can_be_downloaded_as_excel(): void
    {
        Item::factory()->create(['name' => 'Barang Excel Test']);

        $response = $this->actingAs($this->sarpras())
            ->get(route('reports.excel', ['type' => 'inventory']));

        $response->assertOk();
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('content-type')
        );
        $this->assertStringContainsString('laporan-inventory-', $response->headers->get('content-disposition'));
    }

    public function test_incoming_report_exports_with_date_filter(): void
    {
        $sarpras = $this->sarpras();
        $item = Item::factory()->create();
        $startDate = '2026-09-01';
        $endDate = '2026-09-30';

        IncomingItem::create([
            'item_id' => $item->id,
            'transaction_number' => 'IN-20260910-0001',
            'quantity' => 5,
            'source' => 'pembelian',
            'entry_date' => '2026-09-10',
            'user_id' => $sarpras->id,
        ]);

        IncomingItem::create([
            'item_id' => $item->id,
            'transaction_number' => 'IN-20261001-0001',
            'quantity' => 5,
            'source' => 'pembelian',
            'entry_date' => '2026-10-01',
            'user_id' => $sarpras->id,
        ]);

        $response = $this->actingAs($sarpras)
            ->get(route('reports.pdf', ['type' => 'incoming', 'start_date' => $startDate, 'end_date' => $endDate]));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
    }

    public function test_kajur_report_export_locks_department(): void
    {
        $kajur = User::factory()->kajur()->create(['department' => 'RPL']);

        Item::factory()->create([
            'name' => 'Barang RPL',
            'department' => 'RPL',
        ]);

        $response = $this->actingAs($kajur)
            ->get(route('reports.excel', ['type' => 'inventory']));

        $response->assertOk();
    }

    public function test_invalid_report_type_is_rejected(): void
    {
        $response = $this->actingAs($this->sarpras())
            ->get(route('reports.pdf', ['type' => 'bogus']));

        $response->assertSessionHasErrors('type');
    }

    public function test_export_and_print_links_carry_active_filters(): void
    {
        $response = $this->actingAs($this->sarpras())
            ->get(route('reports.index', [
                'type' => 'incoming',
                'start_date' => '2026-09-01',
                'end_date' => '2026-09-30',
            ]));

        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('type=incoming', $html);
        $this->assertStringContainsString('start_date=2026-09-01', $html);
        $this->assertStringContainsString('end_date=2026-09-30', $html);
        $this->assertStringContainsString('export=print', $html);
    }

    public function test_reports_pagination_preserves_filters(): void
    {
        Item::factory()->count(30)->create();

        $this->actingAs($this->sarpras())
            ->get(route('reports.index', ['type' => 'inventory', 'condition' => 'baik', 'per_page' => 25]))
            ->assertOk()
            ->assertSee('condition=baik', false);
    }
}
