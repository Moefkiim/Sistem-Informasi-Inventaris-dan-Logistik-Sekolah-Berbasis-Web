<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan {{ ucfirst($type) }} - Sistem Inventaris Sekolah</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; color: #333; margin: 20px; }
        .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
        .header h2 { margin: 0; text-transform: uppercase; font-size: 16px; }
        .header p { margin: 3px 0 0; color: #666; font-size: 11px; }
        .meta { margin-bottom: 15px; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; font-weight: bold; }
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="no-print" style="margin-bottom: 15px;">
        <button onclick="window.print()" style="padding: 8px 16px; background: #007bff; color: #fff; border: none; border-radius: 4px; cursor: pointer;">Cetak Laporan</button>
        <button onclick="window.close()" style="padding: 8px 16px; background: #6c757d; color: #fff; border: none; border-radius: 4px; cursor: pointer; margin-left: 5px;">Tutup Window</button>
    </div>

    <div class="header">
        <h2>Sistem Informasi Inventaris dan Logistik Sekolah</h2>
        <p>Laporan Rekapitulasi {{ strtoupper($type) }}</p>
    </div>

    <div class="meta">
        <div><strong>Jenis Laporan:</strong> {{ ucfirst($type) }}</div>
        @if($department)
            <div><strong>Filter Jurusan:</strong> {{ $department }}</div>
        @endif
        @if($startDate && $endDate)
            <div><strong>Periode:</strong> {{ $startDate }} s.d. {{ $endDate }}</div>
        @endif
        <div><strong>Tanggal Cetak:</strong> {{ date('d/m/Y H:i') }}</div>
    </div>

    <table>
        <thead>
            @if($type === 'inventory')
                <tr>
                    <th>#</th>
                    <th>Kode Barang</th>
                    <th>Nama Barang</th>
                    <th>Kategori</th>
                    <th>Stok</th>
                    <th>Kondisi</th>
                    <th>Lokasi</th>
                    <th>Jurusan</th>
                </tr>
            @elseif($type === 'incoming')
                <tr>
                    <th>#</th>
                    <th>No. Transaksi</th>
                    <th>Barang</th>
                    <th>Jumlah</th>
                    <th>Asal / Sumber</th>
                    <th>Tanggal Masuk</th>
                    <th>Pencatat</th>
                </tr>
            @elseif($type === 'outgoing')
                <tr>
                    <th>#</th>
                    <th>No. Transaksi</th>
                    <th>Barang</th>
                    <th>Jumlah</th>
                    <th>Alasan Keluar</th>
                    <th>Tanggal Keluar</th>
                    <th>Pencatat</th>
                </tr>
            @elseif($type === 'distribution')
                <tr>
                    <th>#</th>
                    <th>No. Distribusi</th>
                    <th>Barang</th>
                    <th>Jumlah</th>
                    <th>Lokasi Tujuan</th>
                    <th>Jurusan Penerima</th>
                    <th>Tanggal</th>
                </tr>
            @elseif($type === 'submission')
                <tr>
                    <th>#</th>
                    <th>No. Pengajuan</th>
                    <th>Judul</th>
                    <th>Jurusan</th>
                    <th>Pengaju</th>
                    <th>Status</th>
                    <th>Tanggal</th>
                </tr>
            @endif
        </thead>
        <tbody>
            @forelse($data as $idx => $row)
                @if($type === 'inventory')
                    <tr>
                        <td>{{ $idx + 1 }}</td>
                        <td>{{ $row->code }}</td>
                        <td>{{ $row->name }}</td>
                        <td>{{ $row->category }}</td>
                        <td>{{ $row->stock }} {{ $row->unit }}</td>
                        <td>{{ ucfirst(str_replace('_', ' ', $row->current_condition)) }}</td>
                        <td>{{ $row->location->name ?? '-' }}</td>
                        <td>{{ $row->department ?? 'Umum' }}</td>
                    </tr>
                @elseif($type === 'incoming')
                    <tr>
                        <td>{{ $idx + 1 }}</td>
                        <td>{{ $row->transaction_number }}</td>
                        <td>{{ $row->item->name ?? '-' }}</td>
                        <td>{{ $row->quantity }}</td>
                        <td>{{ ucfirst($row->source) }} ({{ $row->source_origin ?? '-' }})</td>
                        <td>{{ $row->entry_date }}</td>
                        <td>{{ $row->user->name ?? '-' }}</td>
                    </tr>
                @elseif($type === 'outgoing')
                    <tr>
                        <td>{{ $idx + 1 }}</td>
                        <td>{{ $row->transaction_number }}</td>
                        <td>{{ $row->item->name ?? '-' }}</td>
                        <td>{{ $row->quantity }}</td>
                        <td>{{ $row->reason }}</td>
                        <td>{{ $row->exit_date }}</td>
                        <td>{{ $row->user->name ?? '-' }}</td>
                    </tr>
                @elseif($type === 'distribution')
                    <tr>
                        <td>{{ $idx + 1 }}</td>
                        <td>{{ $row->distribution_number }}</td>
                        <td>{{ $row->item->name ?? '-' }}</td>
                        <td>{{ $row->quantity }}</td>
                        <td>{{ $row->toLocation->name ?? '-' }}</td>
                        <td>{{ $row->recipient_department ?? '-' }}</td>
                        <td>{{ $row->distribution_date }}</td>
                    </tr>
                @elseif($type === 'submission')
                    <tr>
                        <td>{{ $idx + 1 }}</td>
                        <td>{{ $row->submission_number }}</td>
                        <td>{{ $row->title }}</td>
                        <td>{{ $row->department }}</td>
                        <td>{{ $row->user->name ?? '-' }}</td>
                        <td>{{ strtoupper($row->status) }}</td>
                        <td>{{ $row->created_at->format('d/m/Y') }}</td>
                    </tr>
                @endif
            @empty
                <tr>
                    <td colspan="8" style="text-align: center;">Tidak ada data laporan.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
