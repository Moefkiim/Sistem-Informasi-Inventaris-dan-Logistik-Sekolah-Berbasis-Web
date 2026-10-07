@extends('layouts.app')

@section('title', 'Inventaris Jurusan ' . $department)
@section('header-title', 'Inventaris Jurusan ' . $department)

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <h3 class="fw-bolder">Daftar Barang & Sarana Milik Jurusan {{ $department }}</h3>
        </div>
    </div>
    <div class="card-body pt-0">
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5">
                <thead>
                    <tr class="text-start text-muted fw-bolder fs-7 text-uppercase gs-0">
                        <th>Kode Barang</th>
                        <th>No. Unit</th>
                        <th>No. Seri</th>
                        <th>Nama Barang</th>
                        <th>Kategori</th>
                        <th>Kondisi</th>
                        <th>Status</th>
                        <th>Lokasi</th>
                        <th>Jumlah/Stok</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody class="text-gray-600 fw-bold">
                    @php
                        $statusMap = [
                            'aktif' => 'success',
                            'dipinjam' => 'primary',
                            'dalam_perbaikan' => 'warning',
                            'tidak_aktif' => 'secondary',
                            'disposed' => 'danger',
                        ];
                    @endphp
                    @forelse($items as $item)
                        @php
                            $unitRows = $item->isIndividual() && $item->assetUnits && $item->assetUnits->isNotEmpty()
                                ? $item->assetUnits
                                : collect([null]);
                            $rowCount = $unitRows->count();
                        @endphp
                        @foreach($unitRows as $unitIdx => $unit)
                            @php
                                $status = $unit?->current_status ?? $item->current_status;
                                $statusLabel = \App\Models\AssetUnit::STATUS_LABELS[$status] ?? ucfirst(str_replace('_', ' ', $status));
                                $statusColor = $statusMap[$status] ?? 'secondary';
                                $locationName = $unit?->location?->name ?: ($item->location?->name ?: 'Belum Ditempatkan');
                            @endphp
                            <tr>
                                <td><span class="badge badge-light-dark font-monospace">{{ $item->code }}</span></td>
                                <td>
                                    @if($unit)
                                        <span class="font-monospace">{{ $unit->unit_inventory_number }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>{{ $unit?->serial_number ?: ($item->serial_number ?: '—') }}</td>
                                <td class="text-dark fw-bolder">{{ $item->name }}</td>
                                <td>{{ $item->category }}</td>
                                <td><x-condition-badge :condition="$unit?->current_condition ?? $item->current_condition" /></td>
                                <td>
                                    <span class="badge badge-light-{{ $statusColor }}">{{ $statusLabel }}</span>
                                </td>
                                <td>
                                    <span class="text-gray-800">{{ $locationName }}</span>
                                </td>
                                <td>
                                    <span class="badge badge-light-primary fs-7">{{ $unit ? '1 '.$item->unit : $item->stock.' '.$item->unit }}</span>
                                </td>
                                @if($unitIdx === 0)
                                    <td rowspan="{{ $rowCount }}" class="text-end" style="vertical-align: middle;">
                                        <a href="{{ route('kajur.inventory.show', $item) }}" class="btn btn-sm btn-light btn-active-light-primary">
                                            Histori & Detail
                                        </a>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    @empty
                        <tr>
                            <td colspan="10" class="text-center text-muted py-8">Belum ada barang inventaris yang teralokasi ke jurusan {{ $department }}.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $items->withQueryString()->links() }}
        </div>
    </div>
</div>
@endsection
