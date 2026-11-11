<?php

namespace App\Services;

use App\Models\IncomingItem;
use App\Models\Item;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Satu-satunya jalur pencatatan barang masuk: membuat baris IncomingItem dan
 * menambah stok master secara konsisten. Dipakai oleh modul Barang Masuk dan
 * oleh realisasi pengajuan agar aturannya tidak terduplikasi.
 */
class IncomingStockService
{
    /**
     * @param  array{item_id:int,quantity:int,source?:string,source_origin?:?string,entry_date:string,user_id:int,submission_id?:?int,notes?:?string,transaction_number?:?string,allow_individual?:bool}  $data
     *
     * @throws ValidationException Jika item bertipe 'individual' dan allow_individual tidak disetel true;
     *                             daftarkan unit via registrasi inventaris atau gunakan alur realisasi pengajuan.
     */
    public function record(array $data): IncomingItem
    {
        $item = Item::withoutTrashed()->findOrFail($data['item_id']);

        // Barang masuk manual via modul logistik dilarang untuk item individual.
        // Pengecualian: alur realisasi pengajuan (SubmissionRealizationService)
        // yang sekaligus mendaftarkan asset_units, boleh melewati pemeriksaan ini.
        if ($item->isIndividual() && empty($data['allow_individual'])) {
            throw ValidationException::withMessages([
                'item_id' => 'Barang bertipe individual tidak dapat dicatat sebagai barang masuk massal. '
                    .'Silakan daftarkan setiap unit secara terpisah melalui menu Inventaris → Registrasi Unit.',
            ]);
        }

        $quantity = (int) $data['quantity'];

        $incoming = IncomingItem::create([
            'transaction_number' => $data['transaction_number'] ?? $this->generateDocumentNumber(),
            'item_id' => $data['item_id'],
            'quantity' => $quantity,
            'source' => $data['source'] ?? 'pembelian',
            'source_origin' => $data['source_origin'] ?? null,
            'entry_date' => $data['entry_date'],
            'user_id' => $data['user_id'],
            'submission_id' => $data['submission_id'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        Item::where('id', $data['item_id'])
            ->whereNull('deleted_at')
            ->increment('stock', $quantity);

        return $incoming;
    }

    public function generateDocumentNumber(): string
    {
        $fullPrefix = 'IN-'.date('Ymd').'-';

        $last = DB::table('incoming_items')
            ->where('transaction_number', 'like', $fullPrefix.'%')
            ->lockForUpdate()
            ->orderByDesc('transaction_number')
            ->first();

        $next = $last ? ((int) substr((string) $last->transaction_number, -4) + 1) : 1;

        return $fullPrefix.str_pad($next, 4, '0', STR_PAD_LEFT);
    }
}
