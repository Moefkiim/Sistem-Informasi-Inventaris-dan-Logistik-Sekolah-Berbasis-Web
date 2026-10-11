<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\AssetUnit;
use App\Models\ConditionHistory;
use App\Models\Item;
use App\Models\LocationHistory;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Realisasi pengajuan yang telah disetujui menjadi transaksi inventaris nyata:
 * - setiap baris membuat transaksi Barang Masuk (via IncomingStockService),
 *   sehingga stok bertambah secara konsisten dan tertaut ke pengajuan;
 * - barang individual sekaligus mendaftarkan unit aset baru beserta riwayat
 *   kondisi & lokasi awalnya;
 * - pengajuan hanya dapat direalisasikan sekali oleh Sarpras.
 */
class SubmissionRealizationService
{
    public function __construct(
        private readonly IncomingStockService $incomingStock,
        private readonly SubmissionNotifier $notifier,
    ) {}

    /**
     * @param  array<int,array<string,mixed>>  $lines
     * @param  array{source:string,entry_date:string,realization_notes?:?string}  $meta
     */
    public function realize(Submission $submission, array $lines, array $meta, User $actor): Submission
    {
        $realized = DB::transaction(function () use ($submission, $lines, $meta, $actor) {
            $locked = Submission::whereKey($submission->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->canBeRealizedBySarpras()) {
                throw ValidationException::withMessages([
                    'submission' => 'Pengajuan ini tidak dalam status siap direalisasi.',
                ]);
            }

            $itemsById = $locked->items()->get()->keyBy('id');

            $this->assertExactSubmissionItemSet($locked, $lines);

            foreach ($lines as $line) {
                $submissionItem = $itemsById->get((int) ($line['submission_item_id'] ?? 0));

                if (! $submissionItem) {
                    throw ValidationException::withMessages([
                        'lines' => 'Baris pengajuan tidak valid atau bukan milik pengajuan ini.',
                    ]);
                }

                $quantity = (int) ($line['received_quantity'] ?? 0);
                if ($quantity < 1 || $quantity > $submissionItem->quantity) {
                    throw ValidationException::withMessages([
                        'lines' => "Jumlah diterima untuk '{$submissionItem->item_name}' harus antara 1 dan {$submissionItem->quantity}.",
                    ]);
                }

                [$item, $locationId, $condition] = $this->resolveItem($line, $locked, $meta, $actor);

                $this->incomingStock->record([
                    'item_id' => $item->id,
                    'quantity' => $quantity,
                    'source' => $meta['source'],
                    'entry_date' => $meta['entry_date'],
                    'user_id' => $actor->id,
                    'submission_id' => $locked->id,
                    'notes' => 'Realisasi pengajuan '.$locked->submission_number,
                ]);

                $submissionItem->item_id = $item->id;
                $submissionItem->received_quantity = $quantity;
                $submissionItem->save();

                if ($item->isIndividual()) {
                    $this->registerAssetUnits($item, $quantity, $locationId, $condition, $actor);
                }
            }

            $locked->status = 'realized';
            $locked->realized_by = $actor->id;
            $locked->realized_at = now();
            $locked->realization_notes = $meta['realization_notes'] ?? null;
            $locked->save();

            $locked->recordStatusChange('approved', 'realized', $actor, $meta['realization_notes'] ?? null);

            ActivityLog::log(
                'submission_realized',
                "Pengajuan {$locked->submission_number} direalisasikan oleh {$actor->name}.",
                $locked,
                ['status' => 'approved'],
                ['status' => 'realized'],
                $locked->submission_number,
            );

            return $locked->fresh(['items.item', 'realizedBy']);
        });

        $this->notifier->realized($realized);

        return $realized;
    }

    /**
     * Realisasi wajib mencakup setiap baris pengajuan tepat satu kali,
     * tidak boleh ada baris ganda, hilang, maupun milik pengajuan lain.
     *
     * @param  array<int,array<string,mixed>>  $lines
     */
    private function assertExactSubmissionItemSet(Submission $submission, array $lines): void
    {
        $submissionItemIds = $submission->items()->pluck('id')->all();
        $lineItemIds = array_map('intval', array_column($lines, 'submission_item_id'));

        $missing = array_diff(array_map('intval', $submissionItemIds), $lineItemIds);
        $foreign = array_diff($lineItemIds, array_map('intval', $submissionItemIds));
        $hasDuplicates = count($lineItemIds) !== count(array_unique($lineItemIds));

        if ($missing !== [] || $foreign !== [] || $hasDuplicates) {
            throw ValidationException::withMessages([
                'lines' => 'Setiap baris pengajuan wajib dicakup tepat satu kali dalam realisasi.',
            ]);
        }
    }

    /**
     * @param  array<string,mixed>  $line
     * @return array{0:Item,1:?int,2:string}
     */
    private function resolveItem(array $line, Submission $submission, array $meta, User $actor): array
    {
        if (! empty($line['item_id'])) {
            $item = Item::whereNull('deleted_at')->find($line['item_id']);

            if (! $item) {
                throw ValidationException::withMessages([
                    'lines' => 'Barang inventaris yang dipilih tidak ditemukan.',
                ]);
            }

            return [$item, $item->location_id, $item->current_condition];
        }

        $new = $line['new'] ?? [];
        $locationId = $new['location_id'] ?? null;
        $condition = $new['condition'] ?? 'baik';
        $name = trim((string) ($new['name'] ?? ''));
        $category = trim((string) ($new['category'] ?? ''));
        $category = $category === '' ? 'Umum' : $category;

        if ($name === '') {
            throw ValidationException::withMessages([
                'lines' => 'Nama barang baru untuk realisasi wajib diisi.',
            ]);
        }

        $item = new Item;
        $item->code = $this->generateItemCode($submission->department);
        $item->name = $name;
        $item->category = $category;
        $item->item_type = $new['item_type'];
        $item->unit = $new['unit'];
        $item->source = $meta['source'];
        $item->department = $submission->department;
        $item->location_id = $locationId;
        $item->current_condition = $condition;
        $item->current_status = 'aktif';
        $item->stock = 0;
        $item->save();

        ConditionHistory::create([
            'item_id' => $item->id,
            'from_condition' => $condition,
            'to_condition' => $condition,
            'user_id' => $actor->id,
            'notes' => 'Kondisi awal dari realisasi pengajuan '.$submission->submission_number.'.',
            'recorded_at' => now(),
        ]);

        if ($locationId) {
            LocationHistory::create([
                'item_id' => $item->id,
                'from_location_id' => null,
                'to_location_id' => $locationId,
                'user_id' => $actor->id,
                'notes' => 'Lokasi awal dari realisasi pengajuan '.$submission->submission_number.'.',
                'moved_at' => now(),
            ]);
        }

        ActivityLog::log(
            'item_created',
            "Barang baru dari realisasi pengajuan: {$item->name} ({$item->code}).",
            $item,
            [],
            ['code' => $item->code, 'name' => $item->name, 'stock' => 0],
            $item->code,
        );

        return [$item, $locationId, $condition];
    }

    private function registerAssetUnits(Item $item, int $quantity, ?int $locationId, string $condition, User $actor): void
    {
        for ($i = 0; $i < $quantity; $i++) {
            $unit = AssetUnit::create([
                'item_id' => $item->id,
                'unit_inventory_number' => $this->generateUnitNumber($item),
                'serial_number' => null,
                'current_condition' => $condition,
                'current_status' => 'aktif',
                'location_id' => $locationId,
                'is_legacy_migrated' => false,
            ]);

            ConditionHistory::create([
                'item_id' => $item->id,
                'asset_unit_id' => $unit->id,
                'from_condition' => $condition,
                'to_condition' => $condition,
                'user_id' => $actor->id,
                'notes' => 'Kondisi awal unit saat realisasi pengajuan.',
                'recorded_at' => now(),
            ]);

            if ($locationId) {
                LocationHistory::create([
                    'item_id' => $item->id,
                    'asset_unit_id' => $unit->id,
                    'from_location_id' => null,
                    'to_location_id' => $locationId,
                    'user_id' => $actor->id,
                    'notes' => 'Registrasi unit saat realisasi pengajuan.',
                    'moved_at' => now(),
                ]);
            }
        }
    }

    private function generateItemCode(?string $department): string
    {
        $prefix = 'BRG-'.($department ?: 'UMM').'-';

        // Kunci baris kisaran kode yang sama agar dua realisasi bersamaan
        // tidak menghasilkan kode yang sama (konsisten dengan generator
        // nomor transaksi di LogisticsController::generateDocumentNumber).
        $last = Item::withTrashed()
            ->where('code', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('code')
            ->value('code');

        $next = $last ? (int) substr((string) $last, -4) : 0;

        do {
            $next++;
            $candidate = $prefix.str_pad($next, 4, '0', STR_PAD_LEFT);
        } while (Item::withTrashed()->where('code', $candidate)->exists());

        return $candidate;
    }

    private function generateUnitNumber(Item $item): string
    {
        $sequence = AssetUnit::withTrashed()->where('item_id', $item->id)->count() + 1;

        do {
            $candidate = $item->code.'-'.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
            $sequence++;
        } while (AssetUnit::withTrashed()->where('unit_inventory_number', $candidate)->exists());

        return $candidate;
    }
}
