@extends('layouts.app')

@section('title', 'Kelola Barang - ' . $item->name)
@section('header-title', 'Detail & Manajemen Histori Barang')

@section('content')
<div class="row g-5 mb-5">
    <div class="col-xl-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h4 class="fw-bolder text-dark mb-4">{{ $item->name }}</h4>
                <div class="d-flex flex-column gap-3 fs-6">
                    <div><span class="text-muted">Kode:</span> <span class="badge badge-light-dark font-monospace">{{ $item->code }}</span></div>
                    <div><span class="text-muted">Kategori:</span> <span class="fw-bold">{{ $item->category }}</span></div>
                    <div><span class="text-muted">Stok:</span> <span class="badge badge-light-primary fs-7">{{ $item->stock }} {{ $item->unit }}</span></div>
                    <div><span class="text-muted">Jurusan:</span> <span class="fw-bold">{{ $item->department ?: 'Umum / Sekolah' }}</span></div>
                    <div><span class="text-muted">Lokasi Sekarang:</span> <span class="fw-bold text-primary">{{ $item->location?->name ?: 'Belum ditentukan' }}</span></div>
                    <div>
                        <span class="text-muted">Kondisi Fisik:</span>
                        <span class="badge badge-light-{{ $item->current_condition === 'baik' ? 'success' : ($item->current_condition === 'rusak_ringan' ? 'warning' : 'danger') }}">
                            {{ strtoupper(str_replace('_', ' ', $item->current_condition)) }}
                        </span>
                    </div>
                </div>

                @if($item->assetUnits && $item->assetUnits->isNotEmpty())
                    <div class="separator separator-dashed my-5"></div>
                    <h5 class="fw-bolder mb-3 text-dark"><i class="bi bi-hdd-stack me-1 text-primary"></i> Daftar Unit Aset ({{ $item->assetUnits->count() }})</h5>
                    <div class="table-responsive mb-1">
                        <table class="table table-sm table-row-bordered table-row-gray-100 align-middle">
                            <thead class="fw-bold text-muted fs-8">
                                <tr>
                                    <th>No. Unit</th>
                                    <th>Kondisi</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($item->assetUnits as $unit)
                                    <tr>
                                        <td class="font-monospace">{{ $unit->unit_inventory_number }}</td>
                                        <td><x-condition-badge :condition="$unit->current_condition" /></td>
                                        <td class="text-capitalize">{{ str_replace('_', ' ', $unit->current_status) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if($item->activeLoans && $item->activeLoans->isNotEmpty())
                    <div class="mt-5">
                        <h6 class="fw-bolder text-uppercase fs-8 text-primary mb-3"><i class="bi bi-person-check me-1"></i> Peminjaman Aktif</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-row-bordered table-row-gray-100 align-middle">
                                <thead class="fw-bold text-muted">
                                    <tr>
                                        <th>Nomor</th>
                                        <th>Peminjam</th>
                                        <th>Status</th>
                                        <th>Jumlah</th>
                                        <th>Tenggat</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($item->activeLoans as $loan)
                                        <tr>
                                            <td class="font-monospace">{{ $loan->loan_number }}</td>
                                            <td>
                                                {{ $loan->borrower_name }}
                                                @if($loan->borrower)
                                                    <span class="text-muted fs-8">(akun: {{ $loan->borrower->name }})</span>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge badge-light-{{ \App\Models\Loan::STATUS_META[$loan->status]['color'] ?? 'secondary' }}">
                                                    {{ \App\Models\Loan::STATUS_META[$loan->status]['label'] ?? $loan->status }}
                                                </span>
                                            </td>
                                            <td>{{ $loan->quantity }}</td>
                                            <td>{{ $loan->due_date?->format('d M Y') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                <div class="separator separator-dashed my-5"></div>

                <!-- Form Pindah Lokasi -->
                <h5 class="fw-bolder mb-3 text-dark"><i class="bi bi-geo-alt me-1 text-primary"></i> Mutasi Lokasi</h5>
                <form action="{{ route('sarpras.inventory.updateLocation', $item) }}" method="POST">
                    @csrf
                    @if($item->isIndividual() && $item->assetUnits && $item->assetUnits->isNotEmpty())
                        <div class="mb-3">
                            <select name="asset_unit_id" class="form-select form-select-solid form-select-sm" required>
                                <option value="">-- Pilih Unit Aset --</option>
                                @foreach($item->assetUnits as $unit)
                                    <option value="{{ $unit->id }}">{{ $unit->unit_inventory_number }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="mb-3">
                        <select name="to_location_id" class="form-select form-select-solid form-select-sm" required>
                            <option value="">-- Pilih Lokasi Baru --</option>
                            @foreach($locations as $loc)
                                <option value="{{ $loc->id }}" {{ $item->location_id == $loc->id ? 'disabled' : '' }}>
                                    {{ $loc->name }} ({{ $loc->code }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <input type="text" name="notes" class="form-control form-control-solid form-control-sm" placeholder="Alasan perpindahan lokasi...">
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary w-100">Catat Mutasi Lokasi</button>
                </form>

                <div class="separator separator-dashed my-5"></div>

                <!-- Form Update Kondisi Fisik -->
                <h5 class="fw-bolder mb-3 text-dark"><i class="bi bi-activity me-1 text-warning"></i> Ubah Kondisi Fisik</h5>
                <form action="{{ route('sarpras.inventory.updateCondition', $item) }}" method="POST">
                    @csrf
                    @if($item->isIndividual() && $item->assetUnits && $item->assetUnits->isNotEmpty())
                        <div class="mb-3">
                            <select name="asset_unit_id" class="form-select form-select-solid form-select-sm" required>
                                <option value="">-- Pilih Unit Aset --</option>
                                @foreach($item->assetUnits as $unit)
                                    <option value="{{ $unit->id }}">{{ $unit->unit_inventory_number }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="mb-3">
                        <select name="to_condition" class="form-select form-select-solid form-select-sm" required>
                            <option value="baik" {{ $item->current_condition === 'baik' ? 'selected' : '' }}>Baik</option>
                            <option value="rusak_ringan" {{ $item->current_condition === 'rusak_ringan' ? 'selected' : '' }}>Rusak Ringan</option>
                            <option value="rusak_berat" {{ $item->current_condition === 'rusak_berat' ? 'selected' : '' }}>Rusak Berat</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <input type="text" name="notes" class="form-control form-control-solid form-control-sm" placeholder="Catatan kondisi fisik baru...">
                    </div>
                    <button type="submit" class="btn btn-sm btn-warning w-100 text-dark">Simpan Riwayat Kondisi</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-xl-8">
        <div class="card border-0 shadow-sm mb-5">
            <div class="card-header pt-4">
                <h4 class="fw-bolder">Audit Trail & Riwayat Mutasi</h4>
            </div>
            <div class="card-body">
                <ul class="nav nav-tabs nav-line-tabs mb-5 fs-6">
                    <li class="nav-item">
                        <a class="nav-link active fw-bold" data-bs-toggle="tab" href="#tab_lokasi">Riwayat Lokasi</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-bold" data-bs-toggle="tab" href="#tab_kondisi">Riwayat Kondisi</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-bold" data-bs-toggle="tab" href="#tab_masuk">Barang Masuk</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-bold" data-bs-toggle="tab" href="#tab_keluar">Barang Keluar</a>
                    </li>
                </ul>

                <div class="tab-content" id="myTabContent">
                    <div class="tab-pane fade show active" id="tab_lokasi" role="tabpanel">
                        <table class="table table-row-bordered fs-7 gy-3">
                            <thead class="bg-light fw-bold">
                                <tr><th>Unit</th><th>Dari</th><th>Ke Lokasi</th><th>Pencatat</th><th>Catatan</th><th>Waktu</th></tr>
                            </thead>
                            <tbody>
                                @forelse($item->locationHistories as $lh)
                                    <tr>
                                        <td class="font-monospace">{{ $lh->assetUnit?->unit_inventory_number ?: '-' }}</td>
                                        <td>{{ $lh->fromLocation?->name ?: '-' }}</td>
                                        <td class="fw-bolder text-primary">{{ $lh->toLocation->name }}</td>
                                        <td>{{ $lh->user?->name }}</td>
                                        <td>{{ $lh->notes ?: '-' }}</td>
                                        <td>{{ $lh->moved_at->format('d/m/Y H:i') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-center text-muted">Belum ada histori lokasi.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="tab-pane fade" id="tab_kondisi" role="tabpanel">
                        <table class="table table-row-bordered fs-7 gy-3">
                            <thead class="bg-light fw-bold">
                                <tr><th>Unit</th><th>Kondisi Asal</th><th>Kondisi Baru</th><th>Pencatat</th><th>Catatan</th><th>Waktu</th></tr>
                            </thead>
                            <tbody>
                                @forelse($item->conditionHistories as $ch)
                                    <tr>
                                        <td class="font-monospace">{{ $ch->assetUnit?->unit_inventory_number ?: '-' }}</td>
                                        <td>{{ str_replace('_', ' ', $ch->from_condition) }}</td>
                                        <td class="fw-bolder">{{ str_replace('_', ' ', $ch->to_condition) }}</td>
                                        <td>{{ $ch->user?->name }}</td>
                                        <td>{{ $ch->notes ?: '-' }}</td>
                                        <td>{{ $ch->recorded_at->format('d/m/Y H:i') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-center text-muted">Belum ada histori kondisi.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="tab-pane fade" id="tab_masuk" role="tabpanel">
                        <table class="table table-row-bordered fs-7 gy-3">
                            <thead class="bg-light fw-bold">
                                <tr><th>No. Transaksi</th><th>Jumlah</th><th>Asal Sumber</th><th>Tanggal</th></tr>
                            </thead>
                            <tbody>
                                @forelse($item->incomingItems as $in)
                                    <tr>
                                        <td>{{ $in->transaction_number }}</td>
                                        <td><span class="badge badge-light-success">+{{ $in->quantity }}</span></td>
                                        <td>{{ $in->source_origin ?: $in->source }}</td>
                                        <td>{{ $in->entry_date->format('d/m/Y') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-muted">Belum ada riwayat masuk.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="tab-pane fade" id="tab_keluar" role="tabpanel">
                        <table class="table table-row-bordered fs-7 gy-3">
                            <thead class="bg-light fw-bold">
                                <tr><th>No. Transaksi</th><th>Jumlah</th><th>Alasan</th><th>Tanggal</th></tr>
                            </thead>
                            <tbody>
                                @forelse($item->outgoingItems as $out)
                                    <tr>
                                        <td>{{ $out->transaction_number }}</td>
                                        <td><span class="badge badge-light-danger">-{{ $out->quantity }}</span></td>
                                        <td>{{ $out->reason }}</td>
                                        <td>{{ $out->exit_date->format('d/m/Y') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-muted">Belum ada riwayat keluar.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <a href="{{ route('sarpras.inventory.index') }}" class="btn btn-light">
                    <i class="bi bi-arrow-left me-1"></i> Kembali ke Master Inventaris
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
