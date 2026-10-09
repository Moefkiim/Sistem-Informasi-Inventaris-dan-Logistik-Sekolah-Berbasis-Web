@extends('layouts.app')

@section('title', 'Edit Draft Pengajuan Inventaris')
@section('header-title', 'Edit Draft Pengajuan')

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-header border-0 pt-6">
        <h3 class="fw-bolder">Edit Draft Permohonan: {{ $submission->submission_number }}</h3>
    </div>
    <form action="{{ route('kajur.submissions.update', $submission) }}" method="POST" data-disable-on-submit>
        @csrf
        @method('PUT')
        <div class="card-body pt-0">
            @if($errors->any())
                <div class="alert alert-danger p-4 mb-5">
                    @foreach($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <div class="row g-5 mb-5">
                <div class="col-md-8">
                    <label class="form-label fw-bold required">Judul Pengajuan</label>
                    <input type="text" name="title" class="form-control form-control-solid" placeholder="Judul pengajuan" value="{{ old('title', $submission->title) }}" required>
                </div>
                <div class="col-md-12">
                    <label class="form-label fw-bold">Maksud & Tujuan Pengajuan</label>
                    <textarea name="purpose" class="form-control form-control-solid" rows="3" placeholder="Jelaskan urgensi dan tujuan kebutuhan inventaris ini...">{{ old('purpose', $submission->purpose) }}</textarea>
                </div>
            </div>

            <div class="separator separator-dashed my-6"></div>

            <h4 class="fw-bolder mb-4">Daftar Item Kebutuhan</h4>
            <div id="items-container">
                @foreach($submission->items as $idx => $item)
                <div class="item-row border rounded p-4 mb-4 bg-light position-relative">
                    @if($idx > 0)
                    <button type="button" class="btn btn-sm btn-icon btn-light-danger position-absolute top-0 end-0 m-2 btn-remove-item" title="Hapus Item">
                        <i class="bi bi-x-lg"></i>
                    </button>
                    @endif
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold required">Nama Barang</label>
                            <input type="text" name="items[{{ $idx }}][item_name]" class="form-control form-control-solid" value="{{ old("items.{$idx}.item_name", $item->item_name) }}" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold required">Jumlah</label>
                            <input type="number" name="items[{{ $idx }}][quantity]" class="form-control form-control-solid" min="1" value="{{ old("items.{$idx}.quantity", $item->quantity) }}" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold required">Satuan</label>
                            <input type="text" name="items[{{ $idx }}][unit]" class="form-control form-control-solid" value="{{ old("items.{$idx}.unit", $item->unit) }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Estimasi Harga Satuan (Rp)</label>
                            <input type="number" name="items[{{ $idx }}][estimated_price]" class="form-control form-control-solid" value="{{ old("items.{$idx}.estimated_price", $item->estimated_price) }}">
                        </div>
                        <div class="col-md-12 mt-2">
                            <label class="form-label fw-bold">Spesifikasi Detail</label>
                            <input type="text" name="items[{{ $idx }}][specification]" class="form-control form-control-solid" value="{{ old("items.{$idx}.specification", $item->specification) }}">
                        </div>
                    </div>
                </div>
                @endforeach
            </div>

            <button type="button" class="btn btn-sm btn-light-primary" id="btn-add-item">
                <i class="bi bi-plus-circle me-1"></i> Tambah Item Lainnya
            </button>
        </div>

        <div class="card-footer d-flex justify-content-end gap-3">
            <a href="{{ route('kajur.submissions.show', $submission) }}" class="btn btn-light">Batal</a>
            <button type="submit" name="action" value="draft" class="btn btn-secondary">
                <i class="bi bi-save me-1"></i> Simpan Perubahan Draft
            </button>
            <button type="submit" name="action" value="submit" class="btn btn-primary">
                <i class="bi bi-send me-1"></i> Simpan & Kirimkan ke Sarpras
            </button>
        </div>
    </form>
</div>
@endsection
