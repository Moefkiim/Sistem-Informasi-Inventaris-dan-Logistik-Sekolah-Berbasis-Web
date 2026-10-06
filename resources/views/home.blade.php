@extends('layouts.app')

@section('title', 'Dashboard')
@section('header-title', 'Dashboard')



@section('content')
{{-- Welcome Banner --}}
<div class="card border-0 shadow-sm mb-6" style="background: linear-gradient(135deg, #1e1e2d 0%, #1a1a27 50%, #0066cc 100%); overflow: hidden;">
    <div class="card-body p-8 position-relative">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <div class="d-flex align-items-center mb-4">
                    <div class="symbol symbol-55px me-4">
                        <span class="symbol-label fs-2 fw-bolder text-white" style="background: rgba(255,255,255,0.15);">
                            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                        </span>
                    </div>
                    <div>
                        <h2 class="text-white fw-bolder fs-1 mb-1">Selamat Datang, {{ auth()->user()->name }}!</h2>
                        <div class="d-flex align-items-center gap-3">
                            <span class="badge badge-light text-dark fw-bold px-3 py-2">
                                <i class="bi bi-shield-check me-1 text-primary"></i>
                                {{ strtoupper(str_replace('_', ' ', auth()->user()->role)) }}
                            </span>
                            @if(auth()->user()->department)
                                <span class="badge badge-light text-dark fw-bold px-3 py-2">
                                    <i class="bi bi-building me-1 text-info"></i>
                                    {{ auth()->user()->department }}
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
                <p class="text-gray-300 fs-6 mb-0">
                    @if(auth()->user()->isKajur())
                        Anda dapat mengelola pengajuan barang untuk jurusan <strong class="text-white">{{ auth()->user()->department }}</strong> dan memantau inventaris terkait.
                    @elseif(auth()->user()->isSarpras())
                        Anda dapat mengelola master inventaris, logistik barang masuk/keluar, dan memverifikasi pengajuan dari seluruh Kajur.
                    @else
                        Anda dapat memantau laporan keseluruhan dan menyetujui atau menolak pengajuan yang telah ditelaah oleh Sarpras.
                    @endif
                </p>
            </div>
        </div>
        <i class="bi bi-box-seam text-white d-none d-lg-block" style="font-size: 7rem; opacity: 0.1; position: absolute; right: 2rem; top: 50%; transform: translateY(-50%);"></i>
    </div>
</div>

{{-- Statistik Ringkas --}}
<div class="row g-5 mb-6">
    @if(auth()->user()->isKajur())
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex flex-column p-6">
                    <div class="d-flex align-items-center justify-content-between mb-4">
                        <div class="symbol symbol-50px"><span class="symbol-label bg-light-primary"><i class="bi bi-file-earmark-text fs-2 text-primary"></i></span></div>
                        <span class="badge badge-light-primary fw-bolder">Total</span>
                    </div>
                    <div class="fs-2 fw-bolder text-dark mb-1">{{ $pendingSubmissions }}</div>
                    <div class="fs-7 text-muted">Pengajuan Aktif</div>
                    <a href="{{ route('kajur.submissions.index') }}" class="btn btn-sm btn-light-primary mt-4 w-100">Lihat Semua</a>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex flex-column p-6">
                    <div class="d-flex align-items-center justify-content-between mb-4">
                        <div class="symbol symbol-50px"><span class="symbol-label bg-light-success"><i class="bi bi-boxes fs-2 text-success"></i></span></div>
                        <span class="badge badge-light-success fw-bolder">Inventaris</span>
                    </div>
                    <div class="fs-2 fw-bolder text-dark mb-1">{{ $totalItems }}</div>
                    <div class="fs-7 text-muted">Jenis Barang Tersedia</div>
                    <a href="{{ route('kajur.inventory.index') }}" class="btn btn-sm btn-light-success mt-4 w-100">Lihat Inventaris</a>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex flex-column p-6">
                    <div class="d-flex align-items-center justify-content-between mb-4">
                        <div class="symbol symbol-50px"><span class="symbol-label bg-light-warning"><i class="bi bi-clock-history fs-2 text-warning"></i></span></div>
                        <span class="badge badge-light-warning fw-bolder">Menunggu</span>
                    </div>
                    <div class="fs-2 fw-bolder text-dark mb-1">{{ $pendingSubmissions }}</div>
                    <div class="fs-7 text-muted">Perlu Tindak Lanjut</div>
                    <a href="{{ route('kajur.submissions.create') }}" class="btn btn-sm btn-light-warning mt-4 w-100">Buat Pengajuan</a>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex flex-column p-6">
                    <div class="d-flex align-items-center justify-content-between mb-4">
                        <div class="symbol symbol-50px"><span class="symbol-label bg-light-info"><i class="bi bi-graph-up fs-2 text-info"></i></span></div>
                        <span class="badge badge-light-info fw-bolder">Laporan</span>
                    </div>
                    <div class="fs-2 fw-bolder text-dark mb-1">{{ auth()->user()->department }}</div>
                    <div class="fs-7 text-muted">Jurusan Anda</div>
                    <a href="{{ route('reports.index') }}" class="btn btn-sm btn-light-info mt-4 w-100">Lihat Laporan</a>
                </div>
            </div>
        </div>
    @elseif(auth()->user()->isSarpras())
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex flex-column p-6">
                    <div class="d-flex align-items-center justify-content-between mb-4">
                        <div class="symbol symbol-50px"><span class="symbol-label bg-light-primary"><i class="bi bi-archive fs-2 text-primary"></i></span></div>
                        <span class="badge badge-light-primary fw-bolder">Total</span>
                    </div>
                    <div class="fs-2 fw-bolder text-dark mb-1">{{ $totalItems }}</div>
                    <div class="fs-7 text-muted">Jenis Barang Inventaris</div>
                    <a href="{{ route('sarpras.inventory.index') }}" class="btn btn-sm btn-light-primary mt-4 w-100">Kelola Inventaris</a>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex flex-column p-6">
                    <div class="d-flex align-items-center justify-content-between mb-4">
                        <div class="symbol symbol-50px"><span class="symbol-label bg-light-warning"><i class="bi bi-clipboard-check fs-2 text-warning"></i></span></div>
                        <span class="badge badge-light-warning fw-bolder">Perlu Review</span>
                    </div>
                    <div class="fs-2 fw-bolder text-dark mb-1">{{ $pendingSubmissions }}</div>
                    <div class="fs-7 text-muted">Pengajuan Menunggu</div>
                    <a href="{{ route('sarpras.submissions.index') }}" class="btn btn-sm btn-light-warning mt-4 w-100">Review Sekarang</a>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex flex-column p-6">
                    <div class="d-flex align-items-center justify-content-between mb-4">
                        <div class="symbol symbol-50px"><span class="symbol-label bg-light-success"><i class="bi bi-geo-alt fs-2 text-success"></i></span></div>
                        <span class="badge badge-light-success fw-bolder">Ruangan</span>
                    </div>
                    <div class="fs-2 fw-bolder text-dark mb-1">{{ $totalLocations }}</div>
                    <div class="fs-7 text-muted">Total Lokasi Terdaftar</div>
                    <a href="{{ route('sarpras.locations.index') }}" class="btn btn-sm btn-light-success mt-4 w-100">Kelola Lokasi</a>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex flex-column p-6">
                    <div class="d-flex align-items-center justify-content-between mb-4">
                        <div class="symbol symbol-50px"><span class="symbol-label bg-light-info"><i class="bi bi-people fs-2 text-info"></i></span></div>
                        <span class="badge badge-light-info fw-bolder">Akun</span>
                    </div>
                    <div class="fs-2 fw-bolder text-dark mb-1">Kelola</div>
                    <div class="fs-7 text-muted">Manajemen Pengguna</div>
                    <a href="{{ route('sarpras.users.index') }}" class="btn btn-sm btn-light-info mt-4 w-100">Lihat Pengguna</a>
                </div>
            </div>
        </div>
    @else
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex flex-column p-6">
                    <div class="d-flex align-items-center justify-content-between mb-4">
                        <div class="symbol symbol-50px"><span class="symbol-label bg-light-warning"><i class="bi bi-person-check fs-2 text-warning"></i></span></div>
                        <span class="badge badge-light-warning fw-bolder">Perlu Keputusan</span>
                    </div>
                    <div class="fs-2 fw-bolder text-dark mb-1">{{ $pendingSubmissions }}</div>
                    <div class="fs-7 text-muted">Pengajuan Menunggu Persetujuan</div>
                    <a href="{{ route('kepala_sekolah.approval.index') }}" class="btn btn-sm btn-light-warning mt-4 w-100">Proses Persetujuan</a>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex flex-column p-6">
                    <div class="d-flex align-items-center justify-content-between mb-4">
                        <div class="symbol symbol-50px"><span class="symbol-label bg-light-primary"><i class="bi bi-archive fs-2 text-primary"></i></span></div>
                        <span class="badge badge-light-primary fw-bolder">Inventaris</span>
                    </div>
                    <div class="fs-2 fw-bolder text-dark mb-1">{{ $totalItems }}</div>
                    <div class="fs-7 text-muted">Total Jenis Barang</div>
                    <a href="{{ route('reports.index') }}" class="btn btn-sm btn-light-primary mt-4 w-100">Lihat Laporan</a>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex flex-column p-6">
                    <div class="d-flex align-items-center justify-content-between mb-4">
                        <div class="symbol symbol-50px"><span class="symbol-label bg-light-success"><i class="bi bi-geo-alt fs-2 text-success"></i></span></div>
                        <span class="badge badge-light-success fw-bolder">Lokasi</span>
                    </div>
                    <div class="fs-2 fw-bolder text-dark mb-1">{{ $totalLocations }}</div>
                    <div class="fs-7 text-muted">Ruangan Terdaftar</div>
                    <a href="{{ route('reports.index') }}" class="btn btn-sm btn-light-success mt-4 w-100">Lihat Detail</a>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex flex-column p-6">
                    <div class="d-flex align-items-center justify-content-between mb-4">
                        <div class="symbol symbol-50px"><span class="symbol-label bg-light-info"><i class="bi bi-graph-up fs-2 text-info"></i></span></div>
                        <span class="badge badge-light-info fw-bolder">Monitoring</span>
                    </div>
                    <div class="fs-2 fw-bolder text-dark mb-1">Rekap</div>
                    <div class="fs-7 text-muted">Laporan & Rekapitulasi</div>
                    <a href="{{ route('reports.index') }}" class="btn btn-sm btn-light-info mt-4 w-100">Lihat Laporan</a>
                </div>
            </div>
        </div>
    @endif
</div>

{{-- Quick Actions --}}
<div class="card border-0 shadow-sm">
    <div class="card-header pt-6 border-0">
        <h3 class="fw-bolder text-dark">Aksi Cepat</h3>
    </div>
    <div class="card-body pt-2 pb-6">
        <div class="row g-4">
            @if(auth()->user()->isKajur())
                <div class="col-md-6 col-lg-3">
                    <a href="{{ route('kajur.submissions.create') }}" class="card border border-dashed border-primary bg-hover-light-primary text-decoration-none h-100 d-flex align-items-center justify-content-center p-6">
                        <div class="text-center">
                            <i class="bi bi-plus-circle-fill text-primary" style="font-size: 2.5rem;"></i>
                            <div class="fw-bolder text-dark mt-3">Buat Pengajuan Baru</div>
                            <div class="text-muted fs-7">Ajukan kebutuhan barang jurusan</div>
                        </div>
                    </a>
                </div>
                <div class="col-md-6 col-lg-3">
                    <a href="{{ route('kajur.submissions.index') }}" class="card border border-dashed border-warning bg-hover-light-warning text-decoration-none h-100 d-flex align-items-center justify-content-center p-6">
                        <div class="text-center">
                            <i class="bi bi-list-ul text-warning" style="font-size: 2.5rem;"></i>
                            <div class="fw-bolder text-dark mt-3">Daftar Pengajuan</div>
                            <div class="text-muted fs-7">Pantau status semua pengajuan</div>
                        </div>
                    </a>
                </div>
                <div class="col-md-6 col-lg-3">
                    <a href="{{ route('kajur.inventory.index') }}" class="card border border-dashed border-success bg-hover-light-success text-decoration-none h-100 d-flex align-items-center justify-content-center p-6">
                        <div class="text-center">
                            <i class="bi bi-boxes text-success" style="font-size: 2.5rem;"></i>
                            <div class="fw-bolder text-dark mt-3">Inventaris Jurusan</div>
                            <div class="text-muted fs-7">Lihat barang milik jurusan</div>
                        </div>
                    </a>
                </div>
                <div class="col-md-6 col-lg-3">
                    <a href="{{ route('reports.index') }}" class="card border border-dashed border-info bg-hover-light-info text-decoration-none h-100 d-flex align-items-center justify-content-center p-6">
                        <div class="text-center">
                            <i class="bi bi-graph-up text-info" style="font-size: 2.5rem;"></i>
                            <div class="fw-bolder text-dark mt-3">Laporan Jurusan</div>
                            <div class="text-muted fs-7">Rekap data per periode</div>
                        </div>
                    </a>
                </div>
            @elseif(auth()->user()->isSarpras())
                <div class="col-md-6 col-lg-3">
                    <a href="{{ route('sarpras.inventory.create') }}" class="card border border-dashed border-primary bg-hover-light-primary text-decoration-none h-100 d-flex align-items-center justify-content-center p-6">
                        <div class="text-center">
                            <i class="bi bi-plus-square-fill text-primary" style="font-size: 2.5rem;"></i>
                            <div class="fw-bolder text-dark mt-3">Registrasi Barang</div>
                            <div class="text-muted fs-7">Tambah master data inventaris</div>
                        </div>
                    </a>
                </div>
                <div class="col-md-6 col-lg-3">
                    <a href="{{ route('sarpras.logistics.incoming') }}" class="card border border-dashed border-success bg-hover-light-success text-decoration-none h-100 d-flex align-items-center justify-content-center p-6">
                        <div class="text-center">
                            <i class="bi bi-box-arrow-in-down text-success" style="font-size: 2.5rem;"></i>
                            <div class="fw-bolder text-dark mt-3">Catat Barang Masuk</div>
                            <div class="text-muted fs-7">Pencatatan logistik masuk</div>
                        </div>
                    </a>
                </div>
                <div class="col-md-6 col-lg-3">
                    <a href="{{ route('sarpras.submissions.index') }}" class="card border border-dashed border-warning bg-hover-light-warning text-decoration-none h-100 d-flex align-items-center justify-content-center p-6">
                        <div class="text-center">
                            <i class="bi bi-clipboard-check text-warning" style="font-size: 2.5rem;"></i>
                            <div class="fw-bolder text-dark mt-3">Review Pengajuan</div>
                            <div class="text-muted fs-7">Verifikasi pengajuan Kajur</div>
                        </div>
                    </a>
                </div>
                <div class="col-md-6 col-lg-3">
                    <a href="{{ route('sarpras.users.index') }}" class="card border border-dashed border-info bg-hover-light-info text-decoration-none h-100 d-flex align-items-center justify-content-center p-6">
                        <div class="text-center">
                            <i class="bi bi-people text-info" style="font-size: 2.5rem;"></i>
                            <div class="fw-bolder text-dark mt-3">Kelola Pengguna</div>
                            <div class="text-muted fs-7">Tambah & atur akun pengguna</div>
                        </div>
                    </a>
                </div>
            @else
                <div class="col-md-6 col-lg-4">
                    <a href="{{ route('kepala_sekolah.approval.index') }}" class="card border border-dashed border-warning bg-hover-light-warning text-decoration-none h-100 d-flex align-items-center justify-content-center p-6">
                        <div class="text-center">
                            <i class="bi bi-person-check-fill text-warning" style="font-size: 2.5rem;"></i>
                            <div class="fw-bolder text-dark mt-3">Proses Persetujuan</div>
                            <div class="text-muted fs-7">Setujui atau tolak pengajuan</div>
                        </div>
                    </a>
                </div>
                <div class="col-md-6 col-lg-4">
                    <a href="{{ route('reports.index') }}" class="card border border-dashed border-primary bg-hover-light-primary text-decoration-none h-100 d-flex align-items-center justify-content-center p-6">
                        <div class="text-center">
                            <i class="bi bi-graph-up-arrow text-primary" style="font-size: 2.5rem;"></i>
                            <div class="fw-bolder text-dark mt-3">Laporan & Monitoring</div>
                            <div class="text-muted fs-7">Data inventaris & logistik</div>
                        </div>
                    </a>
                </div>
                <div class="col-md-6 col-lg-4">
                    <a href="{{ route('reports.index') }}?type=submission" class="card border border-dashed border-info bg-hover-light-info text-decoration-none h-100 d-flex align-items-center justify-content-center p-6">
                        <div class="text-center">
                            <i class="bi bi-journals text-info" style="font-size: 2.5rem;"></i>
                            <div class="fw-bolder text-dark mt-3">Riwayat Pengajuan</div>
                            <div class="text-muted fs-7">Lihat seluruh riwayat pengajuan</div>
                        </div>
                    </a>
                </div>
            @endif
        </div>
    </div>
</div>

{{-- Grafik Dashboard (Section 6) --}}
<div class="row g-5 g-xl-8 mt-2">
    @if(count($charts['conditions']['labels']))
        <div class="col-md-6 col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header border-0 pt-6">
                    <h3 class="fw-bolder text-dark">Kondisi Aset</h3>
                </div>
                <div class="card-body d-flex flex-column align-items-center justify-content-center">
                    <div style="height: 250px; width: 100%;"><canvas id="chartKondisi"></canvas></div>
                </div>
            </div>
        </div>
    @endif

    @if(count($charts['locations']['labels']))
        <div class="col-md-6 col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header border-0 pt-6">
                    <h3 class="fw-bolder text-dark">Distribusi Aset per Lokasi</h3>
                </div>
                <div class="card-body d-flex flex-column align-items-center justify-content-center">
                    <div style="height: 250px; width: 100%;"><canvas id="chartLokasi"></canvas></div>
                </div>
            </div>
        </div>
    @endif

    @if($charts['departments'] && count($charts['departments']['labels']))
        <div class="col-md-6 col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header border-0 pt-6">
                    <h3 class="fw-bolder text-dark">Persebaran Aset per Jurusan</h3>
                </div>
                <div class="card-body d-flex flex-column align-items-center justify-content-center">
                    <div style="height: 250px; width: 100%;"><canvas id="chartJurusan"></canvas></div>
                </div>
            </div>
        </div>
    @endif

    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header border-0 pt-6">
                <h3 class="fw-bolder text-dark">Tren Barang Masuk & Keluar</h3>
            </div>
            <div class="card-body">
                <div style="height: 280px;"><canvas id="chartTren"></canvas></div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
    <script src="{{ asset('plugins/chartjs/chart.umd.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const palette = ['#4e73df', '#1cc88a', '#f6c23e', '#e74a3b', '#36b9cc', '#858796', '#6f42c1', '#fd7e14'];
            const data = @json($charts);

            function makeChart(id, config) {
                const el = document.getElementById(id);
                if (!el) return;
                new Chart(el, config);
            }

            makeChart('chartKondisi', {
                type: 'doughnut',
                data: {
                    labels: data.conditions.labels,
                    datasets: [{
                        data: data.conditions.data,
                        backgroundColor: palette,
                        borderWidth: 2
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
            });

            makeChart('chartLokasi', {
                type: 'pie',
                data: {
                    labels: data.locations.labels,
                    datasets: [{
                        data: data.locations.data,
                        backgroundColor: palette,
                        borderWidth: 2
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
            });

            if (data.departments) {
                makeChart('chartJurusan', {
                    type: 'doughnut',
                    data: {
                        labels: data.departments.labels,
                        datasets: [{
                            data: data.departments.data,
                            backgroundColor: palette,
                            borderWidth: 2
                        }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
                });
            }

            makeChart('chartTren', {
                type: 'line',
                data: {
                    labels: data.trend.labels,
                    datasets: [
                        {
                            label: 'Barang Masuk',
                            data: data.trend.incoming,
                            borderColor: '#1cc88a',
                            backgroundColor: 'rgba(28, 200, 138, 0.15)',
                            fill: true,
                            tension: 0.3
                        },
                        {
                            label: 'Barang Keluar',
                            data: data.trend.outgoing,
                            borderColor: '#e74a3b',
                            backgroundColor: 'rgba(231, 74, 59, 0.15)',
                            fill: true,
                            tension: 0.3
                        }
                    ]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
            });
        });
    </script>
@endpush
@endsection
