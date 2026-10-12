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
                        <x-condition-badge :condition="$item->current_condition" />
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
                                        <td><x-unit-status-badge :status="$unit->current_status" /></td>
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
                                                <span class="badge badge-light-{{ \App\Models\Loan::STATUS_META[$loan->effective_status]['color'] ?? 'secondary' }}">
                                                    {{ \App\Models\Loan::STATUS_META[$loan->effective_status]['label'] ?? $loan->effective_status }}
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
                <form action="{{ route('sarpras.inventory.updateLocation', $item) }}" method="POST" data-disable-on-submit>
                    @csrf
                    @if($item->isIndividual() && $item->assetUnits && $item->assetUnits->isNotEmpty())
                        <div class="mb-3">
                            <select name="asset_unit_id" class="form-select form-select-solid form-select-sm @error('asset_unit_id') is-invalid @enderror" required>
                                <option value="">-- Pilih Unit Aset --</option>
                                @foreach($item->assetUnits as $unit)
                                    <option value="{{ $unit->id }}" {{ old('asset_unit_id') == $unit->id ? 'selected' : '' }}>{{ $unit->unit_inventory_number }}</option>
                                @endforeach
                            </select>
                            @error('asset_unit_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    @endif
                    <div class="mb-3">
                        <select name="to_location_id" class="form-select form-select-solid form-select-sm @error('to_location_id') is-invalid @enderror" required>
                            <option value="">-- Pilih Lokasi Baru --</option>
                            @foreach($locations as $loc)
                                <option value="{{ $loc->id }}" {{ old('to_location_id') == $loc->id ? 'selected' : '' }} {{ $item->location_id == $loc->id ? 'disabled' : '' }}>
                                    {{ $loc->name }} ({{ $loc->code }})
                                </option>
                            @endforeach
                        </select>
                        @error('to_location_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <input type="text" name="notes" value="{{ old('notes') }}" class="form-control form-control-solid form-control-sm @error('notes') is-invalid @enderror" placeholder="Alasan perpindahan lokasi...">
                        @error('notes')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary w-100">Catat Mutasi Lokasi</button>
                </form>

                <div class="separator separator-dashed my-5"></div>

                <!-- Form Update Kondisi Fisik -->
                <h5 class="fw-bolder mb-3 text-dark"><i class="bi bi-activity me-1 text-warning"></i> Ubah Kondisi Fisik</h5>
                <form action="{{ route('sarpras.inventory.updateCondition', $item) }}" method="POST" data-disable-on-submit>
                    @csrf
                    @if($item->isIndividual() && $item->assetUnits && $item->assetUnits->isNotEmpty())
                        <div class="mb-3">
                            <select name="asset_unit_id" class="form-select form-select-solid form-select-sm @error('asset_unit_id') is-invalid @enderror" required>
                                <option value="">-- Pilih Unit Aset --</option>
                                @foreach($item->assetUnits as $unit)
                                    <option value="{{ $unit->id }}" {{ old('asset_unit_id') == $unit->id ? 'selected' : '' }}>{{ $unit->unit_inventory_number }}</option>
                                @endforeach
                            </select>
                            @error('asset_unit_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    @endif
                    <div class="mb-3">
                        <select name="to_condition" class="form-select form-select-solid form-select-sm @error('to_condition') is-invalid @enderror" required>
                            @foreach(\App\Support\AssetCondition::all() as $value => $label)
                                <option value="{{ $value }}" {{ old('to_condition', $item->current_condition) === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('to_condition')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <input type="text" name="notes" value="{{ old('notes') }}" class="form-control form-control-solid form-control-sm @error('notes') is-invalid @enderror" placeholder="Catatan kondisi fisik baru...">
                        @error('notes')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-sm btn-warning w-100 text-dark">Simpan Riwayat Kondisi</button>
                </form>

                @if($item->isIndividual() && $item->assetUnits && $item->assetUnits->isNotEmpty())
                <div class="separator separator-dashed my-5"></div>

                <!-- Siklus Status Unit (tombol aksi sesuai transisi yang diizinkan) -->
                <h5 class="fw-bolder mb-1 text-dark"><i class="bi bi-sliders me-1 text-primary"></i> Siklus Status Unit</h5>
                <p class="text-muted fs-7 mb-3">Pilih aksi sesuai transisi yang diizinkan. Setiap perubahan wajib memberi alasan dan tercatat otomatis di riwayat status. Hanya Sarpras yang dapat mengubah status.</p>

                <div class="d-flex flex-column gap-3">
                    @foreach($item->assetUnits as $unit)
                        <div class="border border-gray-300 border-dashed rounded p-3">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div>
                                    <span class="fw-bolder font-monospace">{{ $unit->unit_inventory_number }}</span>
                                    <x-condition-badge :condition="$unit->current_condition" class="ms-2" />
                                    <x-unit-status-badge :status="$unit->current_status" class="ms-1" />
                                </div>
                                <div class="d-flex flex-wrap gap-2 justify-content-end">
                                    @if($unit->current_status === 'dipinjam')
                                        <span class="text-muted fs-7 align-self-center">
                                            <i class="bi bi-hourglass-split me-1"></i> Selesaikan pengembalian peminjaman terlebih dahulu
                                        </span>
                                    @elseif($unit->current_status === 'disposed')
                                        <span class="text-muted fs-7 align-self-center">
                                            <i class="bi bi-lock me-1"></i> Status final (tidak dapat diubah)
                                        </span>
                                    @else
                                        @foreach(\App\Models\AssetUnit::STATUS_TRANSITIONS[$unit->current_status] ?? [] as $to)
                                            <button type="button"
                                                class="btn btn-sm {{ $to === 'disposed' ? 'btn-light-danger' : 'btn-light-primary' }} btn-status-action"
                                                data-bs-toggle="modal" data-bs-target="#statusUnitModal"
                                                data-unit-id="{{ $unit->id }}"
                                                data-unit-label="{{ $unit->unit_inventory_number }}"
                                                data-from-status="{{ $unit->current_status }}"
                                                data-to-status="{{ $to }}"
                                                data-from-label="{{ \App\Models\AssetUnit::STATUS_LABELS[$unit->current_status] ?? $unit->current_status }}"
                                                data-to-label="{{ \App\Models\AssetUnit::STATUS_LABELS[$to] ?? $to }}">
                                                <i class="bi bi-arrow-right-circle me-1"></i> {{ \App\Models\AssetUnit::STATUS_LABELS[$to] ?? $to }}
                                            </button>
                                        @endforeach
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                @endif
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
                        <a class="nav-link fw-bold" data-bs-toggle="tab" href="#tab_status">Riwayat Status Unit</a>
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
                        <div class="table-responsive">
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
                    </div>

                    <div class="tab-pane fade" id="tab_kondisi" role="tabpanel">
                        <div class="table-responsive">
                        <table class="table table-row-bordered fs-7 gy-3">
                            <thead class="bg-light fw-bold">
                                <tr><th>Unit</th><th>Kondisi Asal</th><th>Kondisi Baru</th><th>Pencatat</th><th>Catatan</th><th>Waktu</th></tr>
                            </thead>
                            <tbody>
                                @forelse($item->conditionHistories as $ch)
                                    <tr>
                                        <td class="font-monospace">{{ $ch->assetUnit?->unit_inventory_number ?: '-' }}</td>
                                        <td>{{ \App\Support\AssetCondition::label($ch->from_condition) ?? '-' }}</td>
                                        <td class="fw-bolder">{{ \App\Support\AssetCondition::label($ch->to_condition) ?? '-' }}</td>
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
                    </div>

                    <div class="tab-pane fade" id="tab_status" role="tabpanel">
                        <div class="table-responsive">
                        <table class="table table-row-bordered fs-7 gy-3">
                            <thead class="bg-light fw-bold">
                                <tr><th>Unit</th><th>Status Asal</th><th>Status Baru</th><th>Pencatat</th><th>Alasan</th><th>Waktu</th></tr>
                            </thead>
                            <tbody>
                                @forelse($item->statusHistories as $sh)
                                    <tr>
                                        <td class="font-monospace">{{ $sh->assetUnit?->unit_inventory_number ?: '-' }}</td>
                                        <td>{{ $sh->from_status ? (\App\Models\AssetUnit::STATUS_LABELS[$sh->from_status] ?? $sh->from_status) : '-' }}</td>
                                        <td class="fw-bolder">{{ \App\Models\AssetUnit::STATUS_LABELS[$sh->to_status] ?? $sh->to_status }}</td>
                                        <td>{{ $sh->user?->name }}</td>
                                        <td>{{ $sh->reason ?: '-' }}</td>
                                        <td>{{ $sh->recorded_at->format('d/m/Y H:i') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-center text-muted">Belum ada riwayat perubahan status unit.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="tab_masuk" role="tabpanel">
                        <div class="table-responsive">
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
                    </div>

                    <div class="tab-pane fade" id="tab_keluar" role="tabpanel">
                        <div class="table-responsive">
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

@if($item->isIndividual() && $item->assetUnits && $item->assetUnits->isNotEmpty())
<div class="modal fade" id="statusUnitModal" tabindex="-1" aria-labelledby="statusUnitModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="{{ route('sarpras.inventory.updateStatus', $item) }}" method="POST" class="modal-content" data-disable-on-submit>
            @csrf
            <input type="hidden" name="asset_unit_id" id="status-unit-id">
            <input type="hidden" name="to_status" id="status-to-status">
            <div class="modal-header">
                <h5 class="modal-title" id="statusUnitModalLabel">Ubah Status Unit</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted fs-7">
                    Unit <span class="fw-bolder font-monospace" id="status-unit-label"></span>:
                    <span class="fw-bold" id="status-from-label"></span>
                    <i class="bi bi-arrow-right mx-1"></i>
                    <span class="fw-bolder text-primary" id="status-to-label"></span>
                </p>
                <div class="mb-3">
                    <label for="status-reason" class="form-label fw-bold">Alasan <span class="text-danger">*</span></label>
                    <textarea name="reason" id="status-reason" rows="3" class="form-control form-control-solid" required
                        placeholder="Contoh: layar rusak ringan, perlu pemeriksaan"></textarea>
                </div>
                <div class="mb-3 d-none" id="status-condition-wrap">
                    <label for="status-result-condition" class="form-label fw-bold">Kondisi hasil perbaikan</label>
                    <select name="result_condition" id="status-result-condition" class="form-select form-select-solid">
                        @foreach(\App\Support\AssetCondition::all() as $value => $label)
                            <option value="{{ $value }}" {{ $value === \App\Support\AssetCondition::BAIK ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="d-none" id="status-confirm-wrap">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="confirmed" value="1" id="status-confirm">
                        <label class="form-check-label fs-7 text-danger" for="status-confirm">
                            Saya yakin menghapuskan unit aset ini secara permanen (tidak dapat dibatalkan).
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>
@endif

@push('scripts')
<script>
    (function () {
        const modalEl = document.getElementById('statusUnitModal');
        if (!modalEl) return;

        const idField = document.getElementById('status-unit-id');
        const toField = document.getElementById('status-to-status');
        const unitLabel = document.getElementById('status-unit-label');
        const fromLabel = document.getElementById('status-from-label');
        const toLabel = document.getElementById('status-to-label');
        const conditionWrap = document.getElementById('status-condition-wrap');
        const confirmWrap = document.getElementById('status-confirm-wrap');
        const reasonField = document.getElementById('status-reason');
        const confirmField = document.getElementById('status-confirm');

        modalEl.addEventListener('show.bs.modal', function (event) {
            const btn = event.relatedTarget;
            if (!btn) return;

            const from = btn.getAttribute('data-from-status');
            const to = btn.getAttribute('data-to-status');

            idField.value = btn.getAttribute('data-unit-id');
            toField.value = to;
            unitLabel.textContent = btn.getAttribute('data-unit-label');
            fromLabel.textContent = btn.getAttribute('data-from-label');
            toLabel.textContent = btn.getAttribute('data-to-label');

            conditionWrap.classList.toggle('d-none', !(from === 'dalam_perbaikan' && to === 'aktif'));
            confirmWrap.classList.toggle('d-none', to !== 'disposed');

            reasonField.value = '';
            confirmField.checked = false;
        });
    })();
</script>
@endpush
