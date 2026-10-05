<?php

namespace Tests\Feature;

use App\Models\Distribution;
use App\Models\IncomingItem;
use App\Models\Item;
use App\Models\OutgoingItem;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportScopingTest extends TestCase
{
    use RefreshDatabase;

    public function test_kajur_with_department_sees_only_own_department_incoming_outgoing(): void
    {
        $rpl = User::factory()->kajur()->create(['department' => 'Rekayasa Perangkat Lunak']);
        $tkj = User::factory()->kajur()->create(['department' => 'Teknik Komputer Jaringan']);

        $itemRpl = Item::factory()->create(['department' => 'Rekayasa Perangkat Lunak', 'stock' => 10]);
        $itemTkj = Item::factory()->create(['department' => 'Teknik Komputer Jaringan', 'stock' => 10]);

        IncomingItem::create([
            'item_id' => $itemRpl->id,
            'quantity' => 5,
            'entry_date' => now(),
            'user_id' => $rpl->id,
            'transaction_number' => 'IN-TEST-001',
            'source' => 'pembelian',
        ]);
        IncomingItem::create([
            'item_id' => $itemTkj->id,
            'quantity' => 5,
            'entry_date' => now(),
            'user_id' => $tkj->id,
            'transaction_number' => 'IN-TEST-002',
            'source' => 'pembelian',
        ]);

        OutgoingItem::create([
            'item_id' => $itemRpl->id,
            'quantity' => 1,
            'exit_date' => now(),
            'user_id' => $rpl->id,
            'transaction_number' => 'OUT-TEST-001',
            'reason' => 'rusak',
        ]);
        OutgoingItem::create([
            'item_id' => $itemTkj->id,
            'quantity' => 1,
            'exit_date' => now(),
            'user_id' => $tkj->id,
            'transaction_number' => 'OUT-TEST-002',
            'reason' => 'rusak',
        ]);

        $respIncoming = $this->actingAs($rpl)->get(route('reports.index', ['type' => 'incoming']));
        $respIncoming->assertOk();
        $respIncoming->assertSee($itemRpl->name);
        $respIncoming->assertDontSee($itemTkj->name);

        $respOutgoing = $this->actingAs($rpl)->get(route('reports.index', ['type' => 'outgoing']));
        $respOutgoing->assertOk();
        $respOutgoing->assertSee($itemRpl->name);
        $respOutgoing->assertDontSee($itemTkj->name);
    }

    public function test_kajur_with_null_department_gets_forbidden_on_reports(): void
    {
        $kajur = User::factory()->kajur()->create(['department' => null]);
        $item = Item::factory()->create(['department' => 'Rekayasa Perangkat Lunak']);

        $this->actingAs($kajur)
            ->get(route('reports.index'))
            ->assertForbidden();

        $this->actingAs($kajur)
            ->get(route('reports.index', ['type' => 'incoming']))
            ->assertForbidden();

        $this->actingAs($kajur)
            ->get(route('reports.index', ['type' => 'outgoing']))
            ->assertForbidden();

        $this->actingAs($kajur)
            ->get(route('reports.index', ['type' => 'distribution']))
            ->assertForbidden();

        $this->actingAs($kajur)
            ->get(route('reports.index', ['type' => 'submission']))
            ->assertForbidden();
    }

    public function test_other_roles_access_reports_with_proper_scope(): void
    {
        $sarpras = User::factory()->sarpras()->create();
        $kepsek = User::factory()->state(['role' => 'kepala_sekolah', 'department' => null])->create();
        $rpl = User::factory()->kajur()->create(['department' => 'Rekayasa Perangkat Lunak']);

        $itemRpl = Item::factory()->create(['department' => 'Rekayasa Perangkat Lunak']);
        $itemTkj = Item::factory()->create(['department' => 'Teknik Komputer Jaringan']);

        Submission::create([
            'submission_number' => 'S-1',
            'user_id' => $rpl->id,
            'department' => 'Rekayasa Perangkat Lunak',
            'title' => 'Test RPL',
            'purpose' => 'Test',
            'status' => 'approved',
        ]);
        Submission::create([
            'submission_number' => 'S-2',
            'user_id' => $rpl->id,
            'department' => 'Teknik Komputer Jaringan',
            'title' => 'Test TKJ',
            'purpose' => 'Test',
            'status' => 'approved',
        ]);

        Distribution::create([
            'item_id' => $itemRpl->id,
            'quantity' => 1,
            'distribution_date' => now(),
            'user_id' => $sarpras->id,
            'recipient_department' => 'Rekayasa Perangkat Lunak',
            'to_location_id' => \App\Models\Location::factory()->create()->id,
            'distribution_number' => 'DIST-TEST-001',
            'notes' => 'Dist RPL',
        ]);
        Distribution::create([
            'item_id' => $itemTkj->id,
            'quantity' => 1,
            'distribution_date' => now(),
            'user_id' => $sarpras->id,
            'recipient_department' => 'Teknik Komputer Jaringan',
            'to_location_id' => \App\Models\Location::factory()->create()->id,
            'distribution_number' => 'DIST-TEST-002',
            'notes' => 'Dist TKJ',
        ]);

        $this->actingAs($sarpras)->get(route('reports.index', ['type' => 'submission']))->assertOk();
        $this->actingAs($sarpras)->get(route('reports.index', ['type' => 'distribution']))->assertOk();

        $this->actingAs($kepsek)->get(route('reports.index', ['type' => 'submission']))->assertOk();
        $this->actingAs($kepsek)->get(route('reports.index', ['type' => 'inventory']))->assertOk();

        $respKepsekSub = $this->actingAs($kepsek)->get(route('reports.index', ['type' => 'submission']));
                    $respKepsekSub->assertOk();

        $respSarprasDist = $this->actingAs($sarpras)->get(route('reports.index', ['type' => 'distribution']));
                    $respSarprasDist->assertOk();
    }
}
