<?php

namespace App\Services;

use App\Models\IncomingItem;
use App\Models\Item;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya jalur pencatatan barang masuk: membuat baris IncomingItem dan
 * menambah stok master secara konsisten. Dipakai oleh modul Barang Masuk dan
 * oleh realisasi pengajuan agar aturannya tidak terduplikasi.
 */
class IncomingStockService
{
    /**
     * @param  array{item_id:int,quantity:int,source?:string,source_origin?:?string,entry_date:string,user_id:int,submission_id?:?int,notes?:?string,transaction_number?:?string}  $data
     */
    public function record(array $data): IncomingItem
    {
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
