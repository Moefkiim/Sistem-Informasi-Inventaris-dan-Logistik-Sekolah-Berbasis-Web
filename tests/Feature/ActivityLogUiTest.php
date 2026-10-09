<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogUiTest extends TestCase
{
    use RefreshDatabase;

    private function sarpras(): User
    {
        return User::factory()->sarpras()->create();
    }

    private function log(array $attributes = []): ActivityLog
    {
        return ActivityLog::create(array_merge([
            'action' => 'item_created',
            'description' => 'Barang baru didaftarkan',
            'user_name' => 'Budi',
            'user_role' => 'sarpras',
            'logged_at' => now(),
        ], $attributes));
    }

    public function test_action_labels_are_humanized_and_grouped(): void
    {
        $this->log(['action' => 'item_created', 'description' => 'Barang didaftarkan']);
        $this->log(['action' => 'loan_approved', 'description' => 'Peminjaman disetujui']);

        $this->actingAs($this->sarpras())
            ->get(route('sarpras.activity_logs.index'))
            ->assertOk()
            ->assertSee('Barang Ditambahkan')
            ->assertSee('Peminjaman Disetujui')
            ->assertSee('Inventaris')
            ->assertSee('Peminjaman');
    }

    public function test_unknown_action_falls_back_without_error(): void
    {
        $this->log(['action' => 'custom_unknown_action', 'description' => 'Aksi kustom']);

        $this->actingAs($this->sarpras())
            ->get(route('sarpras.activity_logs.index'))
            ->assertOk()
            ->assertSee('Custom Unknown Action');
    }

    public function test_changed_fields_show_indonesian_labels_and_values(): void
    {
        $this->log([
            'action' => 'item_condition_updated',
            'description' => 'Kondisi diubah',
            'old_values' => ['current_condition' => 'baik'],
            'new_values' => ['current_condition' => 'rusak_ringan'],
        ]);

        $this->actingAs($this->sarpras())
            ->get(route('sarpras.activity_logs.index'))
            ->assertOk()
            ->assertSee('Kondisi')
            ->assertSee('Baik')
            ->assertSee('Rusak Ringan');
    }

    public function test_change_values_are_escaped(): void
    {
        $this->log([
            'action' => 'item_created',
            'description' => 'Payload berbahaya',
            'old_values' => ['name' => null],
            'new_values' => ['name' => '<script>alert(1)</script>'],
        ]);

        $response = $this->actingAs($this->sarpras())
            ->get(route('sarpras.activity_logs.index'));

        $response->assertOk();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $response->getContent());
        $response->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_per_page_option_is_applied(): void
    {
        foreach (range(1, 30) as $i) {
            $this->log(['description' => "Aktivitas {$i}"]);
        }

        $this->actingAs($this->sarpras())
            ->get(route('sarpras.activity_logs.index', ['per_page' => 50]))
            ->assertOk()
            ->assertSee('Aktivitas 1');
    }

    public function test_empty_states_are_differentiated(): void
    {
        $this->actingAs($this->sarpras())
            ->get(route('sarpras.activity_logs.index'))
            ->assertOk()
            ->assertSee('Belum ada aktivitas');

        $this->log(['action' => 'item_created']);

        $this->actingAs($this->sarpras())
            ->get(route('sarpras.activity_logs.index', ['action' => 'loan_created']))
            ->assertOk()
            ->assertSee('Tidak ada hasil untuk filter ini');
    }

    public function test_related_data_links_to_item_detail_when_present(): void
    {
        $item = Item::factory()->create();

        $this->log([
            'action' => 'item_created',
            'auditable_type' => Item::class,
            'auditable_id' => $item->id,
            'auditable_label' => $item->code,
        ]);

        $this->actingAs($this->sarpras())
            ->get(route('sarpras.activity_logs.index'))
            ->assertOk()
            ->assertSee(route('sarpras.inventory.show', $item), false);
    }
}
