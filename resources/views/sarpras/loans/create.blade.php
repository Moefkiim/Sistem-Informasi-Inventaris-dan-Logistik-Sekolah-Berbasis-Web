@extends('layouts.app')

@section('title', 'Catat Peminjaman Baru')
@section('header-title', 'Form Peminjaman Barang')

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-header border-0 pt-6">
        <h3 class="fw-bolder">Catat Peminjaman Barang</h3>
        <p class="text-muted mb-0">Isi formulir di bawah untuk mencatat permohonan peminjaman barang inventaris.</p>
    </div>

    @if($items->isEmpty())
        <div class="card-body pt-0">
            <div class="text-center py-10">
                <i class="fas fa-box-open fs-2x d-block mb-3 opacity-25"></i>
                <p class="text-muted mb-3">Tidak ada barang yang dapat dipinjam. Pastikan sudah ada barang aktif di inventaris.</p>
                <a href="{{ route('sarpras.inventory.create') }}" class="btn btn-primary">Tambah Barang</a>
            </div>
        </div>
    @else
        <form action="{{ route('sarpras.loans.store') }}" method="POST" data-disable-on-submit>
            @csrf
            <div class="card-body pt-0">
                @if($errors->any())
                    <div class="alert alert-danger d-flex align-items-start p-4 mb-6">
                        <i class="fas fa-exclamation-triangle fs-2 text-danger me-3 mt-1"></i>
                        <div>
                            <div class="fw-bold mb-1">Periksa kembali isian berikut:</div>
                            @foreach($errors->all() as $error)
                                <div>{{ $error }}</div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="row g-5">
                    {{-- 1. Data Peminjam --}}
                    <div class="col-12">
                        <h5 class="fw-bold text-primary border-bottom pb-2 mb-4">1. Data Peminjam</h5>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required" for="borrower_name">Nama Peminjam</label>
                        <input type="text" name="borrower_name" id="borrower_name"
                               class="form-control form-control-solid @error('borrower_name') is-invalid @enderror"
                               placeholder="Nama lengkap peminjam"
                               value="{{ old('borrower_name') }}" required>
                        @error('borrower_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="borrower_department">Jurusan / Unit Peminjam</label>
                        <input type="text" name="borrower_department" id="borrower_department"
                               class="form-control form-control-solid @error('borrower_department') is-invalid @enderror"
                               placeholder="Contoh: Rekayasa Perangkat Lunak"
                               value="{{ old('borrower_department') }}">
                        @error('borrower_department')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="borrower_user_id">
                            Akun Peminjam
                            <span class="text-muted fw-normal">(opsional — jika peminjam punya akun sistem)</span>
                        </label>
                        <select name="borrower_user_id" id="borrower_user_id"
                                class="form-select form-select-solid @error('borrower_user_id') is-invalid @enderror">
                            <option value="">-- Peminjam di luar sistem / tanpa akun --</option>
                            @foreach($borrowers as $borrower)
                                <option value="{{ $borrower->id }}"
                                        {{ (int) old('borrower_user_id') === (int) $borrower->id ? 'selected' : '' }}>
                                    {{ $borrower->name }} &middot; {{ $borrower->role }}
                                    @if($borrower->department) &middot; {{ $borrower->department }}@endif
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">
                            Peminjam di luar sistem (guru/siswa tanpa akun) cukup diisi namanya, tanpa memilih akun.
                        </div>
                        @error('borrower_user_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    {{-- 2. Barang yang Dipinjam --}}
                    <div class="col-12">
                        <h5 class="fw-bold text-primary border-bottom pb-2 mb-4">2. Barang yang Dipinjam</h5>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label required" for="item_id">Pilih Barang</label>
                        <select name="item_id" id="item_id"
                                class="form-select form-select-solid @error('item_id') is-invalid @enderror" required>
                            <option value="">-- Pilih Barang --</option>
                            @foreach($items as $item)
                                <option value="{{ $item->id }}"
                                        data-type="{{ $item->item_type }}"
                                        data-stock="{{ $item->stock }}"
                                        data-unit="{{ $item->unit }}"
                                        data-condition="{{ $item->current_condition }}"
                                        data-status="{{ $item->current_status }}"
                                        {{ (int) old('item_id') === (int) $item->id ? 'selected' : '' }}>
                                    [{{ $item->code }}] {{ $item->name }} — stok {{ $item->stock }} {{ $item->unit }}
                                </option>
                            @endforeach
                        </select>
                        @error('item_id')<div class="invalid-feedback">{{ $message }}</div>@enderror

                        <div id="item-info" class="mt-3 p-3 bg-light rounded border" style="display: none">
                            <div id="item-info-text" class="fs-7 text-muted"></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label required" for="quantity">Jumlah</label>
                        <input type="number" name="quantity" id="quantity"
                               class="form-control form-control-solid @error('quantity') is-invalid @enderror"
                               min="1" value="{{ old('quantity', 1) }}" required>
                        @error('quantity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text" id="quantity-hint">Jumlah barang yang dipinjam.</div>
                    </div>

                    {{-- 3. Tanggal & Keperluan --}}
                    <div class="col-12">
                        <h5 class="fw-bold text-primary border-bottom pb-2 mb-4">3. Tanggal & Keperluan</h5>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label required" for="loan_date">Tanggal Pinjam</label>
                        <input type="date" name="loan_date" id="loan_date"
                               class="form-control form-control-solid @error('loan_date') is-invalid @enderror"
                               value="{{ old('loan_date', date('Y-m-d')) }}" required>
                        @error('loan_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label required" for="due_date">Rencana Tanggal Kembali</label>
                        <input type="date" name="due_date" id="due_date"
                               class="form-control form-control-solid @error('due_date') is-invalid @enderror"
                               value="{{ old('due_date') }}" min="{{ old('loan_date', date('Y-m-d')) }}" required>
                        @error('due_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="duration-info">Durasi Peminjaman</label>
                        <div id="duration-info" class="form-control form-control-solid bg-white fw-bold">-</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label required" for="purpose">Keperluan / Tujuan Peminjaman</label>
                        <textarea name="purpose" id="purpose" rows="3"
                                  class="form-control form-control-solid @error('purpose') is-invalid @enderror"
                                  placeholder="Jelaskan keperluan peminjaman barang ini..." required>{{ old('purpose') }}</textarea>
                        @error('purpose')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="notes">Catatan Tambahan</label>
                        <textarea name="notes" id="notes" rows="2"
                                  class="form-control form-control-solid @error('notes') is-invalid @enderror"
                                  placeholder="Catatan opsional, misal kelengkapan saat diserahkan.">{{ old('notes') }}</textarea>
                        @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-end gap-3">
                <a href="{{ route('sarpras.loans.index') }}" class="btn btn-light">Batal</a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-check me-2"></i>Catat Peminjaman
                </button>
            </div>
        </form>
    @endif
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const itemSelect   = document.getElementById('item_id');
    const qtyInput     = document.getElementById('quantity');
    const qtyHint      = document.getElementById('quantity-hint');
    const infoBox      = document.getElementById('item-info');
    const infoText     = document.getElementById('item-info-text');
    const loanDate     = document.getElementById('loan_date');
    const dueDate      = document.getElementById('due_date');
    const durationInfo = document.getElementById('duration-info');

    if (!itemSelect) return;

    const conditionLabels = {
        baik: 'Baik',
        rusak_ringan: 'Rusak Ringan',
        rusak_berat: 'Rusak Berat'
    };
    const statusLabels = {
        aktif: 'Aktif',
        dipinjam: 'Dipinjam',
        tidak_aktif: 'Tidak Aktif'
    };
    const typeLabels = {
        individual: 'Aset Individual',
        consumable: 'Barang Stok / Consumable'
    };

    function refreshItemInfo() {
        const option = itemSelect.options[itemSelect.selectedIndex];

        if (!itemSelect.value || !option || !option.dataset.type) {
            infoBox.style.display = 'none';
            qtyInput.removeAttribute('readonly');
            qtyInput.removeAttribute('max');
            qtyHint.textContent = 'Jumlah barang yang dipinjam.';
            return;
        }

        const type      = option.dataset.type;
        const stock     = parseInt(option.dataset.stock, 10) || 0;
        const unit      = option.dataset.unit || 'unit';
        const condition = conditionLabels[option.dataset.condition] || option.dataset.condition;
        const status    = statusLabels[option.dataset.status] || option.dataset.status;

        infoText.innerHTML = [
            '<strong>Tipe:</strong> ' + (typeLabels[type] || type),
            '<strong>Stok tersedia:</strong> ' + stock + ' ' + unit,
            '<strong>Kondisi saat ini:</strong> ' + condition,
            '<strong>Status:</strong> ' + status
        ].join(' &nbsp;|&nbsp; ');
        infoBox.style.display = 'block';

        if (type === 'individual') {
            // Aset individual dipinjam per unit, jumlah tidak dapat ditambah.
            qtyInput.value = 1;
            qtyInput.max = 1;
            qtyInput.readOnly = true;
            qtyHint.textContent = 'Aset individual selalu dipinjam sebagai 1 unit.';
        } else {
            qtyInput.max = stock;
            qtyInput.readOnly = false;
            qtyHint.textContent = 'Maksimal ' + stock + ' ' + unit + ' (stok saat ini).';
        }
    }

    function refreshDuration() {
        if (!loanDate.value || !dueDate.value) {
            durationInfo.textContent = '-';
            return;
        }

        const start = new Date(loanDate.value);
        const end   = new Date(dueDate.value);
        const days  = Math.round((end - start) / 86400000);

        if (days < 0) {
            durationInfo.textContent = 'Tanggal kembali tidak valid';
            durationInfo.classList.add('text-danger');
            return;
        }

        durationInfo.classList.remove('text-danger');
        durationInfo.textContent = days + ' hari';
    }

    itemSelect.addEventListener('change', refreshItemInfo);
    loanDate.addEventListener('change', function () {
        dueDate.min = loanDate.value || dueDate.min;
        refreshDuration();
    });
    dueDate.addEventListener('change', refreshDuration);

    refreshItemInfo();
    refreshDuration();
});
</script>
@endpush
