@extends('layouts.app')

@section('title', 'Distribusi Barang')
@section('header-title', 'Penyaluran / Distribusi Logistik')

@section('content')
<div class="row g-5">
    <div class="col-xl-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header pt-6">
                <h4 class="fw-bolder">Catat Distribusi Baru</h4>
            </div>
            <form action="{{ route('sarpras.logistics.distributions.store') }}" method="POST" data-disable-on-submit>
                @csrf
                <div class="card-body">
                    @if($errors->any())
                        <div class="alert alert-danger p-3 mb-4">
                            @foreach($errors->all() as $err)<div>{{ $err }}</div>@endforeach
                        </div>
                    @endif

                    <div class="mb-4">
                        <label class="form-label fw-bold required">Barang Inventaris</label>
                        <select name="item_id" class="form-select form-select-solid" required>
                            <option value="">-- Pilih Barang --</option>
                            @foreach($items as $it)
                                <option value="{{ $it->id }}" {{ old('item_id') == $it->id ? 'selected' : '' }}>{{ $it->name }} (Stok: {{ $it->stock }} {{ $it->unit }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold required">Jumlah Distribusi</label>
                        <input type="number" name="quantity" class="form-control form-control-solid" min="1" value="{{ old('quantity', 1) }}" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold required">Lokasi Penempatan Tujuan</label>
                        <select name="to_location_id" class="form-select form-select-solid" required>
                            <option value="">-- Pilih Lokasi --</option>
                            @foreach($locations as $loc)
                                <option value="{{ $loc->id }}" {{ old('to_location_id') == $loc->id ? 'selected' : '' }}>{{ $loc->name }} ({{ $loc->code }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Jurusan / Unit Penerima</label>
                        <input type="text" name="recipient_department" class="form-control form-control-solid" value="{{ old('recipient_department') }}" placeholder="Contoh: RPL, TKJ, Lab IPA" list="department-list">
                        <datalist id="department-list">
                            @foreach(config('departments') as $code => $name)
                                <option value="{{ $code }}">{{ $name }}</option>
                            @endforeach
                        </datalist>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Nama Penerima / Guru Penanggung Jawab</label>
                        <input type="text" name="recipient_name" class="form-control form-control-solid" value="{{ old('recipient_name') }}" placeholder="Nama penerima">
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold required">Tanggal Penyaluran</label>
                        <input type="date" name="distribution_date" class="form-control form-control-solid" value="{{ old('distribution_date', date('Y-m-d')) }}" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Catatan Penyaluran</label>
                        <textarea name="notes" class="form-control form-control-solid" rows="2" placeholder="Keterangan tambahan...">{{ old('notes') }}</textarea>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-truck me-1"></i> Simpan Log Distribusi
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="col-xl-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header border-0 pt-6">
                <h3 class="fw-bolder">Riwayat Penyaluran / Distribusi</h3>
            </div>
            <div class="card-body pt-0">
                @if(session('success'))
                    <div class="alert alert-success d-flex align-items-center p-3 mb-4">
                        <i class="bi bi-check-circle fs-3 text-success me-2"></i>
                        <div>{{ session('success') }}</div>
                    </div>
                @endif

                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-7 gy-4">
                        <thead>
                            <tr class="text-start text-muted fw-bolder fs-7 text-uppercase gs-0">
                                <th>No. Distribusi</th>
                                <th>Barang</th>
                                <th>Kuantitas</th>
                                <th>Lokasi Tujuan</th>
                                <th>Penerima</th>
                                <th>Tanggal</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-600 fw-bold">
                            @forelse($distributions as $dist)
                                <tr>
                                    <td><span class="text-dark fw-bolder">{{ $dist->distribution_number }}</span></td>
                                    <td>{{ $dist->item?->name ?? 'Barang Telah Dihapus' }}</td>
                                    <td><span class="badge badge-light-primary">{{ $dist->quantity }} {{ $dist->item?->unit ?? 'Unit' }}</span></td>
                                    <td>{{ $dist->toLocation?->name ?? 'Lokasi Telah Dihapus' }}</td>
                                    <td>{{ $dist->recipient_name ?: ($dist->recipient_department ?: '-') }}</td>
                                    <td>{{ $dist->distribution_date->format('d/m/Y') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted py-6">Belum ada catatan distribusi.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $distributions->withQueryString()->links() }}</div>
            </div>
        </div>
    </div>
</div>
@endsection
