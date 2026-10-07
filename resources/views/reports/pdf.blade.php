<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan {{ ucfirst($type) }} - Sistem Inventaris Sekolah</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #333; margin: 20px; }
        .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
        .header h2 { margin: 0; text-transform: uppercase; font-size: 16px; }
        .header p { margin: 3px 0 0; color: #666; font-size: 11px; }
        .meta { margin-bottom: 15px; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ccc; padding: 6px; text-align: left; }
        th { background-color: #f2f2f2; font-weight: bold; }
        .signature { margin-top: 50px; display: flex; justify-content: space-between; }
        .signature .block { text-align: center; font-size: 11px; }
        .signature .block .empty { height: 70px; }
        .empty-row td { text-align: center; color: #888; padding: 20px; }
    </style>
</head>
<body>
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
        <div><strong>Jumlah Data:</strong> {{ $data->count() }} baris</div>
    </div>

    <table>
        <thead>
            @if($type === 'inventory')
                <tr>
                    <th>#</th>
                    <th>Kode Barang</th>
                    <th>No. Unit</th>
                    <th>No. Seri</th>
                    <th>Nama Barang</th>
                    <th>Kategori</th>
                    <th>Jumlah/Stok</th>
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
                    @php
                        $isUnit = $row->isIndividual() && $row->assetUnits !== null && $row->assetUnits->isNotEmpty();
                        $unitRows = $isUnit ? $row->assetUnits : collect([null]);
                        $rowNo = $loop->index + 1;
                    @endphp
                    @foreach($unitRows as $unit)
                        <tr>
                            <td>{{ $rowNo++ }}</td>
                            <td>{{ $row->code }}</td>
                            <td>{{ $unit?->unit_inventory_number ?: ($row->inventory_number ?: '-') }}</td>
                            <td>{{ $unit?->serial_number ?: ($row->serial_number ?: '-') }}</td>
                            <td>{{ $row->name }}</td>
                            <td>{{ $row->category }}</td>
                            <td>{{ $unit ? '1 '.$row->unit : $row->stock.' '.$row->unit }}</td>
                            <td>{{ ucfirst(str_replace('_', ' ', $unit?->current_condition ?: $row->current_condition)) }}</td>
                            <td>{{ $unit?->location?->name ?: $row->location?->name ?: '-' }}</td>
                            <td>{{ $row->department ?: 'Umum' }}</td>
                        </tr>
                    @endforeach
                @elseif($type === 'incoming')
                    <tr>
                        <td>{{ $idx + 1 }}</td>
                        <td>{{ $row->transaction_number }}</td>
                        <td>{{ $row->item->name ?? '-' }}</td>
                        <td>{{ $row->quantity }}</td>
                        <td>{{ ucfirst($row->source) }} ({{ $row->source_origin ?? '-' }})</td>
                        <td>{{ $row->entry_date->format('d/m/Y') }}</td>
                        <td>{{ $row->user->name ?? '-' }}</td>
                    </tr>
                @elseif($type === 'outgoing')
                    <tr>
                        <td>{{ $idx + 1 }}</td>
                        <td>{{ $row->transaction_number }}</td>
                        <td>{{ $row->item->name ?? '-' }}</td>
                        <td>{{ $row->quantity }}</td>
                        <td>{{ $row->reason }}</td>
                        <td>{{ $row->exit_date->format('d/m/Y') }}</td>
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
                        <td>{{ $row->distribution_date->format('d/m/Y') }}</td>
                    </tr>
                @elseif($type === 'submission')
                    <tr>
                        <td>{{ $idx + 1 }}</td>
                        <td>{{ $row->submission_number }}</td>
                        <td>{{ $row->title }}</td>
                        <td>{{ $row->department }}</td>
                        <td>{{ $row->user->name ?? '-' }}</td>
                        <td>{{ strtoupper(str_replace('_', ' ', $row->status)) }}</td>
                        <td>{{ $row->created_at->format('d/m/Y') }}</td>
                    </tr>
                @endif
            @empty
                <tr class="empty-row">
                    <td colspan="10">Tidak ada data laporan.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="signature">
        <div class="block">
            <div>Mengetahui,</div>
            <div class="empty"></div>
            <div style="text-decoration: underline;">Kepala Sekolah</div>
        </div>
        <div class="block">
            <div>Disusun oleh,</div>
            <div class="empty"></div>
            <div style="text-decoration: underline;">Petugas Sarpras / Logistik</div>
        </div>
    </div>
</body>
</html>