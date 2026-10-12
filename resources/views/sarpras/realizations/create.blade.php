@extends('layouts.app')

@section('title', 'Realisasikan Pengajuan - Sarpras')
@section('header-title', 'Realisasi Pengajuan')

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <h3 class="fw-bolder">Realisasi {{ $submission->submission_number }}</h3>
        </div>
    </div>
    <div class="card-body pt-0">
        @if(session('_old_errors'))
            <div class="alert alert-danger d-flex align-items-center p-4 mb-5">
                <i class="bi bi-exclamation-triangle fs-2 text-danger me-3"></i>
                <div>Periksa kembali isian formulir sebelum menyimpan.</div>
            </div>
        @endif

        <div class="alert alert-light-primary p-4 mb-5">
            <div class="row">
                <div class="col-md-6">
                    <p class="text-muted mb-0 fs-7">Pengaju</p>
                    <p class="fw-bolder mb-0">{{ $submission->user->name }} ({{ $submission->department }})</p>
                </div>
                <div class="col-md-6">
                    <p class="text-muted mb-0 fs-7">Judul Kebutuhan</p>
                    <p class="fw-bolder mb-0">{{ $submission->title }}</p>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('sarpras.submissions.realize', $submission) }}" id="realization-form">
            @csrf

            <div class="row g-4 mb-6">
                <div class="col-md-4">
                    <label class="form-label required" for="source">Sumber Perolehan</label>
                    <select class="form-select {{ $errors->has('source') ? 'is-invalid' : '' }}" id="source" name="source" required>
                        <option value="" disabled {{ old('source') ? '' : 'selected' }}>-- Pilih sumber --</option>
                        <option value="pembelian" {{ old('source') === 'pembelian' ? 'selected' : '' }}>Pembelian</option>
                        <option value="bantuan" {{ old('source') === 'bantuan' ? 'selected' : '' }}>Bantuan</option>
                    </select>
                    @error('source')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label required" for="entry_date">Tanggal Barang Masuk</label>
                    <input type="date" class="form-control {{ $errors->has('entry_date') ? 'is-invalid' : '' }}" id="entry_date" name="entry_date" value="{{ old('entry_date', now()->toDateString()) }}" required>
                    @error('entry_date')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="realization_notes">Catatan Realisasi</label>
                    <input type="text" class="form-control" id="realization_notes" name="realization_notes" value="{{ old('realization_notes') }}" maxlength="255">
                </div>
            </div>

            <h5 class="fw-bolder mb-3">{{ $submission->items()->count() }} Barang yang Diminta</h5>
            <p class="text-muted fs-7 mb-5">
                Untuk setiap baris tentukan jumlah diterima dan satuan barangnya: pilih barang inventaris yang sudah ada,
                atau daftarkan barang baru sekaligus.
            </p>

            @foreach($submission->items as $index => $submissionItem)
                <div class="border rounded-3 p-4 mb-4 bg-light">
                    <input type="hidden" name="lines[{{ $index }}][submission_item_id]" value="{{ $submissionItem->id }}">

                    <div class="d-flex justify-content-between mb-3">
                        <div>
                            <span class="fw-bolder text-dark">{{ $submissionItem->item_name }}</span>
                            <span class="badge badge-light-secondary ms-2">{{ $submissionItem->quantity }} {{ $submissionItem->unit }}</span>
                        </div>
                        @if(! empty($submissionItem->specification))
                            <span class="text-muted fs-7">{{ $submissionItem->specification }}</span>
                        @endif
                    </div>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label required">Jumlah Diterima</label>
                            <input type="number" class="form-control @error("lines.{$index}.received_quantity") is-invalid @enderror" name="lines[{{ $index }}][received_quantity]" value="{{ old("lines.{$index}.received_quantity", $submissionItem->quantity) }}" min="1" max="{{ $submissionItem->quantity }}" required>
                            <span class="form-text">Maks. {{ $submissionItem->quantity }} (sesuai permintaan)</span>
                            @error("lines.{$index}.received_quantity")
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label required">Satuan Barang</label>
                            <select class="form-select mode-select" name="lines[{{ $index }}][mode]" data-index="{{ $index }}">
                                <option value="existing" {{ old("lines.{$index}.mode", 'new') === 'existing' ? 'selected' : '' }}>Barang inventaris yang sudah ada</option>
                                <option value="new" {{ old("lines.{$index}.mode", 'new') === 'new' ? 'selected' : '' }}>Barang baru (daftarkan)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Pilih Barang Inventaris</label>
                            <select class="form-select @error("lines.{$index}.item_id") is-invalid @enderror" name="lines[{{ $index }}][item_id]" data-mode-panel="existing" data-control="select2" data-placeholder="-- Pilih barang --">
                                <option value="" {{ empty(old("lines.{$index}.item_id")) ? 'selected' : '' }}>-- Pilih barang --</option>
                                @foreach($items as $item)
                                    <option value="{{ $item->id }}" {{ old("lines.{$index}.item_id") == $item->id ? 'selected' : '' }}>
                                        {{ $item->name }} ({{ $item->code }})
                                    </option>
                                @endforeach
                            </select>
                            @error("lines.{$index}.item_id")
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="new-item-panel mt-4" data-mode-panel="new">
                        <div class="row g-3">
                            <div class="col-md-5">
                                <label class="form-label required">Nama Barang</label>
                                <input type="text" class="form-control @error("lines.{$index}.new_name") is-invalid @enderror" name="lines[{{ $index }}][new_name]" value="{{ old("lines.{$index}.new_name", $submissionItem->item_name) }}" maxlength="255">
                                @error("lines.{$index}.new_name")
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-3">
                                <label class="form-label required">Kategori</label>
                                <input type="text" class="form-control @error("lines.{$index}.new_category") is-invalid @enderror" name="lines[{{ $index }}][new_category]" value="{{ old("lines.{$index}.new_category") }}" maxlength="191" placeholder="mis. Elektronik">
                                @error("lines.{$index}.new_category")
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-2">
                                <label class="form-label required">Jenis</label>
                                <select class="form-select @error("lines.{$index}.new_item_type") is-invalid @enderror" name="lines[{{ $index }}][new_item_type]">
                                    <option value="individual" {{ old("lines.{$index}.new_item_type", 'individual') === 'individual' ? 'selected' : '' }}>Individual</option>
                                    <option value="consumable" {{ old("lines.{$index}.new_item_type") === 'consumable' ? 'selected' : '' }}>Habis Pakai</option>
                                </select>
                                @error("lines.{$index}.new_item_type")
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-2">
                                <label class="form-label required">Satuan</label>
                                <input type="text" class="form-control @error("lines.{$index}.new_unit") is-invalid @enderror" name="lines[{{ $index }}][new_unit]" value="{{ old("lines.{$index}.new_unit", $submissionItem->unit) }}" maxlength="50">
                                @error("lines.{$index}.new_unit")
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="row g-3 mt-0">
                            <div class="col-md-4">
                                <label class="form-label">Lokasi Awal</label>
                                <select class="form-select @error("lines.{$index}.new_location_id") is-invalid @enderror" name="lines[{{ $index }}][new_location_id]" data-control="select2" data-placeholder="-- Pilih lokasi --">
                                    <option value="">-- Pilih lokasi --</option>
                                    @foreach($locations as $location)
                                        <option value="{{ $location->id }}" {{ old("lines.{$index}.new_location_id") == $location->id ? 'selected' : '' }}>{{ $location->name }}</option>
                                    @endforeach
                                </select>
                                @error("lines.{$index}.new_location_id")
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label required">Kondisi Awal</label>
                                <select class="form-select @error("lines.{$index}.new_condition") is-invalid @enderror" name="lines[{{ $index }}][new_condition]">
                                    <option value="baik" {{ old("lines.{$index}.new_condition", 'baik') === 'baik' ? 'selected' : '' }}>Baik</option>
                                    <option value="rusak_ringan" {{ old("lines.{$index}.new_condition") === 'rusak_ringan' ? 'selected' : '' }}>Rusak Ringan</option>
                                    <option value="rusak_berat" {{ old("lines.{$index}.new_condition") === 'rusak_berat' ? 'selected' : '' }}>Rusak Berat</option>
                                </select>
                                @error("lines.{$index}.new_condition")
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <span class="text-muted fs-7">
                                    Jurusan otomatis: <span class="badge badge-light-primary">{{ $submission->department }}</span>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach

            <div class="alert alert-warning p-4 d-flex align-items-center mb-6">
                <i class="bi bi-info-circle fs-2 text-warning me-3"></i>
                <div>
                    Realisasi bersifat final dan tercatat sebagai Barang Masuk. Barang individual akan didaftarkan sebagai unit aset baru,
                    barang habis pakai akan menambah stok. Setelah direalisasikan pengajuan tidak dapat diubah.
                </div>
            </div>

            <div class="d-flex justify-content-end gap-3">
                <a href="{{ route('sarpras.realizations.index') }}" class="btn btn-light">Batal</a>
                <button type="submit" class="btn btn-primary" data-disable-on-submit>
                    <i class="bi bi-check-circle me-1"></i> Realisasikan Sekarang
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.mode-select').forEach(function (select) {
            const index = select.getAttribute('data-index');
            const itemColumn = document.querySelector(`select[name="lines[${index}][item_id]"]`)?.closest('.col-md-4');
            const newPanel = document.querySelector(`input[name="lines[${index}][new_name]"]`)?.closest('.new-item-panel');

            const apply = function () {
                const isNew = select.value === 'new';
                if (itemColumn) {
                    itemColumn.style.display = isNew ? 'none' : '';
                }
                if (newPanel) {
                    newPanel.style.display = isNew ? '' : 'none';
                }
            };

            select.addEventListener('change', apply);
            apply();
        });
    });
</script>
@endpush