@extends('layouts.app')

@section('title', 'Daftar Peminjaman')
@section('header-title', 'Modul Peminjaman Barang')

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-header border-0 pt-6">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h3 class="fw-bolder mb-1">Daftar Peminjaman</h3>
                <p class="text-muted mb-0">Kelola peminjaman dan pengembalian barang inventaris</p>
            </div>
            <a href="{{ route('sarpras.loans.create') }}" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Catat Peminjaman Baru
            </a>
        </div>
    </div>

    <div class="card-body pt-0">
        @if(session('success'))
            <div class="alert alert-success d-flex align-items-center p-4 mb-5">
                <i class="fas fa-check-circle fs-2 text-success me-3"></i>
                <div>{{ session('success') }}</div>
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger d-flex align-items-start p-4 mb-5">
                <i class="fas fa-exclamation-triangle fs-2 text-danger me-3 mt-1"></i>
                <div>
                    @foreach($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Ringkasan per status (sumber label: Loan::STATUS_META) --}}
        <div class="row g-3 mb-6">
            @foreach(\App\Models\Loan::STATUS_META as $key => $meta)
                <div class="col-xl-2 col-md-4 col-6">
                    <a href="{{ route('sarpras.loans.index', array_filter(['status' => $key])) }}"
                       class="text-decoration-none d-block border border-2 rounded p-3 text-center bg-white
                              {{ request('status') === $key ? 'border-primary' : 'border-transparent' }}">
                        <div class="fs-2 fw-bold text-{{ $meta['color'] }}">{{ $statusCounts[$key] ?? 0 }}</div>
                        <div class="text-muted fs-7">{{ $meta['label'] }}</div>
                    </a>
                </div>
            @endforeach
        </div>

        {{-- Pencarian & filter --}}
        <form method="GET" action="{{ route('sarpras.loans.index') }}" class="mb-6">
            <div class="row g-3 align-items-end">
                <div class="col-lg-5">
                    <label class="form-label text-muted fs-7 fw-bold" for="loan-search">Pencarian</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0">
                            <i class="fas fa-search text-muted"></i>
                        </span>
                        <input type="text" name="search" id="loan-search"
                               class="form-control form-control-solid ps-0"
                               placeholder="Nomor peminjaman, peminjam, atau nama/kode barang"
                               value="{{ request('search') }}">
                    </div>
                </div>
                <div class="col-lg-3">
                    <label class="form-label text-muted fs-7 fw-bold" for="loan-status">Status</label>
                    <select name="status" id="loan-status" class="form-select form-select-solid">
                        <option value="">Semua Status</option>
                        @foreach(\App\Models\Loan::STATUS_META as $key => $meta)
                            <option value="{{ $key }}" {{ request('status') === $key ? 'selected' : '' }}>
                                {{ $meta['label'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2">
                    <label class="form-label text-muted fs-7 fw-bold" for="loan-due">Rencana Kembali</label>
                    <select name="due" id="loan-due" class="form-select form-select-solid">
                        <option value="">Semua</option>
                        <option value="overdue" {{ request('due') === 'overdue' ? 'selected' : '' }}>Terlambat</option>
                        <option value="today" {{ request('due') === 'today' ? 'selected' : '' }}>Jatuh tempo hari ini</option>
                        <option value="week" {{ request('due') === 'week' ? 'selected' : '' }}>7 hari ke depan</option>
                    </select>
                </div>
                <div class="col-lg-2">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="fas fa-filter me-2"></i>Filter
                        </button>
                        @if(request()->hasAny(['search', 'status', 'due']))
                            <a href="{{ route('sarpras.loans.index') }}" class="btn btn-light" title="Reset filter">
                                <i class="fas fa-rotate-left"></i>
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5">
                <thead>
                    <tr class="text-start text-muted fw-bolder fs-7 text-uppercase gs-0">
                        <th>No. Peminjaman</th>
                        <th>Barang</th>
                        <th>Peminjam</th>
                        <th>Jurusan</th>
                        <th>Tgl Pinjam</th>
                        <th>Rencana Kembali</th>
                        <th>Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody class="text-gray-600 fw-bold">
                    @forelse($loans as $loan)
                        <tr>
                            <td>
                                <span class="text-dark fw-bolder font-monospace">{{ $loan->loan_number }}</span>
                            </td>
                            <td>
                                <div class="fw-bolder text-dark">{{ $loan->item?->name ?? 'Barang telah dihapus' }}</div>
                                <div class="text-muted fs-7 font-monospace">{{ $loan->item?->code }}</div>
                                <span class="badge badge-light-secondary">
                                    {{ $loan->quantity }} {{ $loan->item?->unit ?? 'unit' }}
                                </span>
                            </td>
                            <td>{{ $loan->borrower_name }}</td>
                            <td>{{ $loan->borrower_department ?? '-' }}</td>
                            <td>{{ $loan->loan_date?->format('d/m/Y') }}</td>
                            <td>
                                {{ $loan->due_date?->format('d/m/Y') }}
                                @if($loan->isOverdue())
                                    <span class="badge badge-light-danger fs-8">
                                        Terlambat {{ $loan->overdueDays() }} hari
                                    </span>
                                @endif
                            </td>
                            <td>
                                <x-loan-status-badge :status="$loan->status" />
                            </td>
                            <td class="text-end">
                                <a href="{{ route('sarpras.loans.show', $loan) }}"
                                   class="btn btn-sm btn-light btn-active-light-primary">
                                    Detail
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-10">
                                <i class="fas fa-box-open fs-2x d-block mb-3 opacity-25"></i>
                                @if(request()->hasAny(['search', 'status', 'due']))
                                    <p class="text-muted mb-3">Tidak ada peminjaman yang cocok dengan filter.</p>
                                    <a href="{{ route('sarpras.loans.index') }}" class="btn btn-sm btn-light">Reset Filter</a>
                                @else
                                    <p class="text-muted mb-3">Belum ada data peminjaman.</p>
                                    <a href="{{ route('sarpras.loans.create') }}" class="btn btn-sm btn-primary">
                                        Catat Peminjaman Pertama
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $loans->withQueryString()->links() }}
        </div>
    </div>
</div>
@endsection
