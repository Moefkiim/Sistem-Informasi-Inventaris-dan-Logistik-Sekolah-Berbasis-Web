@extends('layouts.app')

@section('title', 'Daftar Pengajuan Saya')
@section('header-title', 'Pengajuan Inventaris (Kajur)')

@section('content')
<x-page-header
    title="Riwayat Permohonan Pengajuan Barang"
    description="Kelola pengajuan inventaris jurusan Anda; draf dapat diubah hingga dikirim ke Sarpras.">
    <a href="{{ route('kajur.submissions.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Buat Pengajuan Baru
    </a>
</x-page-header>

<div class="card border-0 shadow-sm">
    <div class="card-body pt-6">
        @if(session('success'))
            <div class="alert alert-success d-flex align-items-center p-4 mb-5">
                <i class="bi bi-check-circle fs-2 text-success me-3"></i>
                <div class="d-flex flex-column">
                    <span>{{ session('success') }}</span>
                </div>
            </div>
        @endif

        {{-- Toolbar Pencarian & Filter --}}
        <form action="{{ route('kajur.submissions.index') }}" method="GET" class="card bg-light p-5 border-0 mb-6">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label fs-7 fw-bold text-gray-700">Pencarian</label>
                    <div class="position-relative">
                        <i class="bi bi-search position-absolute top-50 translate-middle-y ms-3 text-gray-500"></i>
                        <input type="text" name="search" class="form-control form-control-solid ps-10 form-control-sm" placeholder="No. Pengajuan, judul, maksud..." value="{{ $search }}">
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label fs-7 fw-bold text-gray-700">Status Pengajuan</label>
                    <select name="status" class="form-select form-select-solid form-select-sm">
                        <option value="">Semua Status</option>
                        <option value="draft" {{ $status === 'draft' ? 'selected' : '' }}>Draf (Belum Dikirim)</option>
                        <option value="submitted" {{ $status === 'submitted' ? 'selected' : '' }}>Menunggu Review Sarpras</option>
                        <option value="reviewed_sarpras" {{ $status === 'reviewed_sarpras' ? 'selected' : '' }}>Menunggu Keputusan Kepsek</option>
                        <option value="approved" {{ $status === 'approved' ? 'selected' : '' }}>Disetujui Kepala Sekolah</option>
                        <option value="rejected" {{ $status === 'rejected' ? 'selected' : '' }}>Ditolak</option>
                        <option value="realized" {{ $status === 'realized' ? 'selected' : '' }}>Terealisasi</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fs-7 fw-bold text-gray-700">Rentang Tanggal Dibuat</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-0"><i class="bi bi-calendar-range text-gray-500"></i></span>
                        <input type="text" class="form-control form-control-solid js-daterangepicker" placeholder="Semua tanggal" value="{{ $startDate && $endDate ? date('d/m/Y', strtotime($startDate)) . ' - ' . date('d/m/Y', strtotime($endDate)) : '' }}" readonly>
                        <input type="hidden" name="start_date" value="{{ $startDate }}">
                        <input type="hidden" name="end_date" value="{{ $endDate }}">
                    </div>
                </div>
                <div class="col-12 d-flex justify-content-end gap-2 mt-2">
                    @if($search || $status || $startDate || $endDate)
                        <a href="{{ route('kajur.submissions.index') }}" class="btn btn-sm btn-light">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Filter
                        </a>
                    @endif
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-filter me-1"></i> Terapkan Filter
                    </button>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5">
                <thead>
                    <tr class="text-start text-muted fw-bolder fs-7 text-uppercase gs-0">
                        <th>No. Pengajuan</th>
                        <th>Judul Permohonan</th>
                        <th>Jumlah Item</th>
                        <th>Status</th>
                        <th>Tanggal Dibuat</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody class="text-gray-600 fw-bold">
                    @forelse($submissions as $sub)
                        <tr>
                            <td>
                                <a href="{{ route('kajur.submissions.show', $sub) }}" class="text-dark fw-bolder text-hover-primary">
                                    {{ $sub->submission_number }}
                                </a>
                            </td>
                            <td>{{ $sub->title }}</td>
                            <td><span class="badge badge-light-info">{{ $sub->items->count() }} Item</span></td>
                            <td>
                                <x-submission-status-badge :status="$sub->status" />
                            </td>
                            <td>{{ $sub->created_at->format('d M Y H:i') }}</td>
                            <td class="text-end">
                                <a href="{{ route('kajur.submissions.show', $sub) }}" class="btn btn-sm btn-light btn-active-light-primary">
                                    Detail
                                </a>
                                @if($sub->isDraft())
                                    <form action="{{ route('kajur.submissions.submitDraft', $sub) }}" method="POST" class="d-inline" data-disable-on-submit>
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-light-success" data-confirm="Ajukan draf pengajuan ini ke pihak Sarpras? Draf tidak dapat diubah lagi setelah dikirim.">
                                            Kirim
                                        </button>
                                    </form>
                                    <form action="{{ route('kajur.submissions.cancel', $sub) }}" method="POST" class="d-inline" data-disable-on-submit>
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-light-danger" data-confirm-delete="Apakah Anda yakin ingin membatalkan draf pengajuan ini?">
                                            Batal
                                        </button>
                                    </form>
                                @elseif($sub->isRejected())
                                    <form action="{{ route('kajur.submissions.revise', $sub) }}" method="POST" class="d-inline" data-disable-on-submit>
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-light-primary" data-confirm="Buat draf revisi dari pengajuan yang ditolak ini? Baris barang akan disalin ke draf baru.">
                                            Ajukan Ulang
                                        </button>
                                    </form>
                                @endif
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-light-primary" onclick="return confirm('Buat draf revisi dari pengajuan yang ditolak ini?')">
                                            Ajukan Ulang
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <x-empty-state
                                    icon="bi-clipboard-plus"
                                    title="Belum ada pengajuan inventaris"
                                    description="Buat pengajuan pertama Anda lewat tombol Buat Pengajuan Baru." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $submissions->withQueryString()->links() }}
        </div>
    </div>
</div>
@endsection
