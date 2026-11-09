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
        {{-- Toolbar Pencarian & Filter --}}
        <form action="{{ route('kajur.inventory.index') }}" method="GET" class="card bg-light p-5 border-0 mb-6">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fs-7 fw-bold text-gray-700">Pencarian</label>
                    <div class="position-relative">
                        <i class="bi bi-search position-absolute top-50 translate-middle-y ms-3 text-gray-500"></i>
                        <input type="text" name="search" class="form-control form-control-solid ps-10 form-control-sm" placeholder="Nama, kode, no seri..." value="{{ $search }}">
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label fs-7 fw-bold text-gray-700">Kategori</label>
                    <select name="category" class="form-select form-select-solid form-select-sm">
                        <option value="">Semua Kategori</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat }}" {{ $category === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fs-7 fw-bold text-gray-700">Kondisi Fisik</label>
                    <select name="condition" class="form-select form-select-solid form-select-sm">
                        <option value="">Semua Kondisi</option>
                        <option value="baik" {{ $condition === 'baik' ? 'selected' : '' }}>Baik</option>
                        <option value="rusak_ringan" {{ $condition === 'rusak_ringan' ? 'selected' : '' }}>Rusak Ringan</option>
                        <option value="rusak_berat" {{ $condition === 'rusak_berat' ? 'selected' : '' }}>Rusak Berat</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fs-7 fw-bold text-gray-700">Status Aset</label>
                    <select name="status" class="form-select form-select-solid form-select-sm">
                        <option value="">Semua Status</option>
                        <option value="aktif" {{ $status === 'aktif' ? 'selected' : '' }}>Aktif</option>
                        <option value="dipinjam" {{ $status === 'dipinjam' ? 'selected' : '' }}>Dipinjam</option>
                        <option value="dalam_perbaikan" {{ $status === 'dalam_perbaikan' ? 'selected' : '' }}>Dalam Perbaikan</option>
                        <option value="tidak_aktif" {{ $status === 'tidak_aktif' ? 'selected' : '' }}>Tidak Aktif</option>
                        <option value="disposed" {{ $status === 'disposed' ? 'selected' : '' }}>Dihapuskan</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fs-7 fw-bold text-gray-700">Rentang Tanggal Registrasi</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-0"><i class="bi bi-calendar-range text-gray-500"></i></span>
                        <input type="text" class="form-control form-control-solid js-daterangepicker" placeholder="Semua tanggal" value="{{ $startDate && $endDate ? date('d/m/Y', strtotime($startDate)) . ' - ' . date('d/m/Y', strtotime($endDate)) : '' }}" readonly>
                        <input type="hidden" name="start_date" value="{{ $startDate }}">
                        <input type="hidden" name="end_date" value="{{ $endDate }}">
                    </div>
                </div>
                <div class="col-12 d-flex justify-content-end gap-2 mt-2">
                    @if($search || $category || $condition || $status || $stok || $startDate || $endDate)
                        <a href="{{ route('kajur.inventory.index') }}" class="btn btn-sm btn-light">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Filter
                        </a>
                    @endif
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-filter me-1"></i> Terapkan Filter
                    </button>
                </div>
            </div>
        </form>

        @if(request('stok'))
            <div class="d-flex align-items-center gap-2 mb-4">
                <span class="text-muted fs-7">Filter stok aktif:</span>
                <span class="badge badge-light-{{ request('stok') === 'habis' ? 'danger' : 'warning' }} d-inline-flex align-items-center">
                    Stok: {{ ucfirst(request('stok')) }}
                    <a href="{{ request()->fullUrlWithQuery(['stok' => null]) }}" class="ms-1 text-muted text-hover-dark text-decoration-none">×</a>
                </span>
            </div>
        @endif

        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5">
                <thead>
                    <tr class="text-start text-muted fw-bolder fs-7 text-uppercase gs-0">
                        <th class="w-50px text-center">No</th>
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
                                @if($unitIdx === 0)
                                    <td rowspan="{{ $rowCount }}" class="text-center text-gray-700 fw-bold" style="vertical-align: middle;">{{ $items->firstItem() + $loop->parent->index }}</td>
                                @endif
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
                            <td colspan="11">
                                <x-empty-state
                                    icon="bi-box-seam"
                                    title="Belum ada barang inventaris di jurusan {{ $department }}"
                                    description="Inventaris yang teralokasi ke jurusan Anda akan tampil di sini." />
                            </td>
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
