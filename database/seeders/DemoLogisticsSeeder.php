<?php

namespace Database\Seeders;

use App\Models\Distribution;
use App\Models\IncomingItem;
use App\Models\Item;
use App\Models\Location;
use App\Models\LocationHistory;
use App\Models\OutgoingItem;
use App\Models\User;
use Database\Seeders\Concerns\GuardsDemoEnvironment;
use Illuminate\Database\Seeder;

/**
 * Transaksi logistik demo untuk barang habis pakai: barang masuk (pembelian
 * dan bantuan), barang keluar, dan distribusi yang tersebar pada beberapa bulan
 * terakhir agar grafik tren dashboard tidak kosong. Stok akhir dihitung dari
 * transaksi sehingga konsisten (tidak ada suntikan angka langsung).
 */
class DemoLogisticsSeeder extends Seeder
{
    use GuardsDemoEnvironment;

    private User $sarpras;

    /** @var array<string,int> */
    private array $sequences = [];

    public function run(): void
    {
        if (! $this->demoDataAllowed()) {
            $this->command?->warn('DemoLogisticsSeeder dilewati: APP_ENV=production.');

            return;
        }

        // Idempoten: bila transaksi logistik sudah pernah dibuat, berhenti.
        if (IncomingItem::exists() || OutgoingItem::exists() || Distribution::exists()) {
            $this->command?->info('Transaksi logistik sudah ada, DemoLogisticsSeeder dilewati.');

            return;
        }

        $this->sarpras = User::where('username', 'sarpras')->firstOrFail();

        $gudang = Location::where('code', 'GDG-SARPRAS')->firstOrFail();
        $labRpl = Location::where('code', 'LAB-RPL-01')->firstOrFail();
        $ruangGuru = Location::where('code', 'R-GURU')->firstOrFail();

        $kertas = Item::where('code', 'BRG-UMM-0001')->firstOrFail();
        $tinta = Item::where('code', 'BRG-UMM-0002')->firstOrFail();
        $spidol = Item::where('code', 'BRG-UMM-0003')->firstOrFail();

        // Kertas A4: 25 masuk - 3 keluar - 4 distribusi = 18 (aman).
        $this->incoming($kertas, 10, 'pembelian', 'Toko ATK Jaya', 6);
        $this->incoming($kertas, 10, 'pembelian', 'Toko ATK Jaya', 3);
        $this->incoming($kertas, 5, 'bantuan', 'Donasi Komite Sekolah', 1);
        $this->outgoing($kertas, 3, 'Rusak / kadaluarsa', 2);
        $this->distribution($kertas, 2, $labRpl, 'RPL', 'Kajur RPL', 1);
        $this->distribution($kertas, 2, $ruangGuru, null, 'Staf TU', 4);

        // Tinta HP 802: 10 masuk - 8 keluar = 2 (menipis, min 5).
        $this->incoming($tinta, 6, 'pembelian', 'Toko ATK Jaya', 7);
        $this->incoming($tinta, 4, 'pembelian', 'Toko ATK Jaya', 2);
        $this->outgoing($tinta, 5, 'Stok lama dikeluarkan', 4);
        $this->outgoing($tinta, 3, 'Dipakai operasional', 1);

        // Spidol Whiteboard: 6 masuk - 6 keluar = 0 (habis, min 3).
        $this->incoming($spidol, 4, 'pembelian', 'Toko ATK Jaya', 8);
        $this->incoming($spidol, 2, 'bantuan', 'Donasi Alumni', 2);
        $this->outgoing($spidol, 6, 'Habis dipakai kegiatan belajar', 1);
    }

    private function incoming(Item $item, int $quantity, string $source, string $origin, int $monthsAgo): void
    {
        $date = now()->subMonths($monthsAgo)->startOfMonth()->addDays(9)->toDateString();

        IncomingItem::create([
            'transaction_number' => $this->number('IN', $date),
            'item_id' => $item->id,
            'quantity' => $quantity,
            'source' => $source,
            'source_origin' => $origin,
            'entry_date' => $date,
            'user_id' => $this->sarpras->id,
            'notes' => 'Pengadaan demo '.$item->name,
        ]);

        Item::whereKey($item->id)->increment('stock', $quantity);
    }

    private function outgoing(Item $item, int $quantity, string $reason, int $monthsAgo): void
    {
        $date = now()->subMonths($monthsAgo)->startOfMonth()->addDays(14)->toDateString();

        OutgoingItem::create([
            'transaction_number' => $this->number('OUT', $date),
            'item_id' => $item->id,
            'quantity' => $quantity,
            'exit_date' => $date,
            'reason' => $reason,
            'user_id' => $this->sarpras->id,
            'notes' => 'Pengeluaran demo '.$item->name,
        ]);

        Item::whereKey($item->id)->decrement('stock', $quantity);
    }

    private function distribution(Item $item, int $quantity, Location $location, ?string $department, ?string $recipient, int $monthsAgo): void
    {
        $date = now()->subMonths($monthsAgo)->startOfMonth()->addDays(19)->toDateString();

        Distribution::create([
            'distribution_number' => $this->number('DIST', $date),
            'item_id' => $item->id,
            'quantity' => $quantity,
            'to_location_id' => $location->id,
            'recipient_department' => $department,
            'recipient_name' => $recipient,
            'distribution_date' => $date,
            'user_id' => $this->sarpras->id,
            'notes' => 'Distribusi demo '.$item->name,
        ]);

        LocationHistory::create([
            'item_id' => $item->id,
            'from_location_id' => $item->location_id,
            'to_location_id' => $location->id,
            'user_id' => $this->sarpras->id,
            'notes' => 'Distribusi barang demo',
            'moved_at' => now()->subMonths($monthsAgo),
        ]);

        if ($quantity > 0) {
            Item::whereKey($item->id)->decrement('stock', $quantity);
        }
    }

    /**
     * Nomor dokumen unik berurutan per tanggal (pola PREFIX-YYYYMMDD-XXXX).
     */
    private function number(string $prefix, string $date): string
    {
        $compact = str_replace('-', '', $date);
        $key = $prefix.'-'.$compact;
        $this->sequences[$key] = ($this->sequences[$key] ?? 0) + 1;

        return $key.'-'.str_pad((string) $this->sequences[$key], 4, '0', STR_PAD_LEFT);
    }
}
