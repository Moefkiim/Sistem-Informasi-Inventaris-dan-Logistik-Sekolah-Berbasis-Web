<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8" />
    <title>@yield('title', 'Dashboard') - SIILS Sekolah</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="description" content="Sistem Informasi Inventaris dan Logistik Sekolah">
    <link rel="shortcut icon" href="{{ asset('assets/media/logos/favicon.ico') }}" />
    <!-- Fonts -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700" />
    <!-- Metronic Global Stylesheets -->
    <link href="{{ asset('assets/plugins/global/plugins.bundle.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('assets/css/style.bundle.css') }}" rel="stylesheet" type="text/css" />
    <style>
        /* Sidebar: rasio kontras teks terhadap latar #1e1e2d (aside-dark). */
        .aside-dark .menu-section-label { color: #8a93b2 !important; font-size: 0.75rem; font-weight: 700; letter-spacing: 0.08rem; padding: 0.5rem 1.4rem; text-transform: uppercase; display: block; }
        .aside-dark .menu .menu-link { position: relative; }
        .aside-dark .menu .menu-title { color: #b5b5c3; transition: color 0.15s ease; }
        .aside-dark .menu .menu-icon i { color: #7e8299; transition: color 0.15s ease; }
        .aside-dark .menu .menu-link:hover .menu-title,
        .aside-dark .menu .menu-link:hover .menu-icon i { color: #ffffff; }
        .aside-dark .menu .menu-link.active { background-color: rgba(0, 158, 247, 0.14); }
        .aside-dark .menu .menu-link.active .menu-title,
        .aside-dark .menu .menu-link.active .menu-icon i { color: #ffffff; }
        .aside-dark .menu .menu-link.active::before { content: ''; position: absolute; left: 0; top: 0.35rem; bottom: 0.35rem; width: 3px; border-radius: 0 3px 3px 0; background-color: #009ef7; }
        .aside-dark .menu-sub .menu-link { padding-left: 3.2rem !important; }
        /* Kontras teks WCAG AA (target >= 4.5:1) */
        .text-muted { color: #5e6278 !important; }
        .text-gray-500 { color: #5e6278 !important; }
        .text-gray-600 { color: #4b5675 !important; }
        .text-primary { color: #0066cc !important; }
        .text-success { color: #0f7a43 !important; }
        .text-warning { color: #7a5f00 !important; }
        .text-danger { color: #b3123a !important; }
        .text-info { color: #5b2fbf !important; }
        .btn.btn-primary { background-color: #0066cc !important; border-color: #0066cc !important; color: #fff !important; }
        .btn.btn-primary:hover, .btn.btn-primary:focus, .btn.btn-primary:active, .btn.btn-primary.active,
        .btn-check:checked + .btn.btn-primary, .show > .btn.btn-primary.dropdown-toggle { background-color: #0052a3 !important; border-color: #0052a3 !important; color: #fff !important; }
        .btn.btn-light { color: #4b5675 !important; }
        .btn.btn-light:hover { color: #0066cc !important; }
        .btn.btn-light-primary { color: #0a58ca !important; }
        .btn.btn-light-success { color: #0f7a43 !important; }
        .btn.btn-light-warning { color: #7a5f00 !important; }
        .btn.btn-light-danger { color: #b3123a !important; }
        .btn.btn-light-info { color: #5b2fbf !important; }
        .btn.btn-light-secondary { color: #4b5675 !important; }
        .badge.badge-light-primary { color: #0a58ca !important; }
        .badge.badge-light-success { color: #0f7a43 !important; }
        .badge.badge-light-warning { color: #7a5f00 !important; }
        .badge.badge-light-danger { color: #b3123a !important; }
        .badge.badge-light-info { color: #5b2fbf !important; }
        .badge.badge-light-secondary { color: #4b5675 !important; }
        .aside-dark .text-gray-500 { color: #b5b5c3 !important; }
        .badge-role { background: linear-gradient(135deg, #009ef7, #0066cc); color: #fff; padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; }
        .user-info-card { background: linear-gradient(135deg, rgba(0,158,247,0.12), rgba(0,102,204,0.08)); border: 1px solid rgba(0,158,247,0.2); border-radius: 12px; }
    </style>
    @stack('styles')
</head>
<body id="kt_body" class="header-fixed header-tablet-and-mobile-fixed toolbar-enabled toolbar-fixed aside-enabled aside-fixed" style="--kt-toolbar-height:55px;--kt-toolbar-height-tablet-and-mobile:55px">
    <div class="d-flex flex-column flex-root">
        <div class="page d-flex flex-row flex-column-fluid">
            <!-- Sidebar / Aside -->
            <div id="kt_aside" class="aside aside-dark aside-hoverable" data-kt-drawer="true" data-kt-drawer-name="aside" data-kt-drawer-activate="{default: true, lg: false}" data-kt-drawer-overlay="true" data-kt-drawer-width="{default:'200px', '300px': '250px'}" data-kt-drawer-direction="start" data-kt-drawer-toggle="#kt_aside_mobile_toggle">
                <!-- Brand / Logo -->
                <div class="aside-logo flex-column-auto" id="kt_aside_logo">
                    <a href="{{ route('home') }}" class="d-flex align-items-center text-white text-decoration-none">
                        <i class="bi bi-box-seam text-primary fs-2x me-2"></i>
                        <span class="fs-4 fw-bolder text-white">Inventaris Aset</span>
                    </a>
                    <div id="kt_aside_toggle" class="btn btn-icon w-auto px-0 btn-active-color-primary aside-toggle" data-kt-toggle="true" data-kt-toggle-state="active" data-kt-toggle-target="body" data-kt-toggle-name="aside-minimize">
                        <span class="svg-icon svg-icon-1 rotate-180">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                <path opacity="0.5" d="M14.2657 11.4343L18.45 7.25C18.8642 6.83579 18.8642 6.16421 18.45 5.75C18.0358 5.33579 17.3642 5.33579 16.95 5.75L11.4071 11.2929C11.0166 11.6834 11.0166 12.3166 11.4071 12.7071L16.95 18.25C17.3642 18.6642 18.0358 18.6642 18.45 18.25C18.8642 17.8358 18.8642 17.1642 18.45 16.75L14.2657 12.5657C13.9533 12.2533 13.9533 11.7467 14.2657 11.4343Z" fill="black" />
                                <path d="M8.2657 11.4343L12.45 7.25C12.8642 6.83579 12.8642 6.16421 12.45 5.75C12.0358 5.33579 11.3642 5.33579 10.95 5.75L5.40712 11.2929C5.01659 11.6834 5.01659 12.3166 5.40712 12.7071L10.95 18.25C11.3642 18.6642 12.0358 18.6642 12.45 18.25C12.8642 17.8358 12.8642 17.1642 12.45 16.75L8.2657 12.5657C7.95328 12.2533 7.95328 11.7467 8.2657 11.4343Z" fill="black" />
                            </svg>
                        </span>
                    </div>
                </div>

                <!-- Aside Menu Berbasis Role -->
                <div class="aside-menu flex-column-fluid">
                    <div class="hover-scroll-overlay-y my-5 my-lg-5" id="kt_aside_menu_wrapper" data-kt-scroll="true" data-kt-scroll-activate="{default: false, lg: true}" data-kt-scroll-height="auto" data-kt-scroll-dependencies="#kt_aside_logo, #kt_aside_footer" data-kt-scroll-wrappers="#kt_aside_menu" data-kt-scroll-offset="0">
                        <div class="menu menu-column menu-title-gray-800 menu-state-title-primary menu-state-icon-primary menu-state-bullet-primary menu-arrow-gray-500" id="kt_aside_menu" data-kt-menu="true">

                            <!-- Dashboard (Semua Role) -->
                            <div class="menu-item">
                                <a class="menu-link {{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}">
                                    <span class="menu-icon"><i class="bi bi-speedometer2 fs-3"></i></span>
                                    <span class="menu-title">Dashboard</span>
                                </a>
                            </div>

                            {{-- ======================= MENU KAJUR ======================= --}}
                            @if(auth()->user()->isKajur())
                                <div class="menu-item pt-5">
                                    <div class="menu-content">
                                        <span class="menu-section-label">Menu Kajur — {{ auth()->user()->departmentLabel() }}</span>
                                    </div>
                                </div>

                                <div class="menu-item menu-accordion {{ request()->routeIs('kajur.submissions.*') ? 'open' : '' }}" data-kt-menu-trigger="click">
                                    <span class="menu-link {{ request()->routeIs('kajur.submissions.*') ? 'active' : '' }}">
                                        <span class="menu-icon"><i class="bi bi-file-earmark-text fs-3"></i></span>
                                        <span class="menu-title">Pengajuan Barang</span>
                                        <span class="menu-arrow"></span>
                                    </span>
                                    <div class="menu-sub menu-sub-accordion">
                                        <div class="menu-item">
                                            <a class="menu-link {{ request()->routeIs('kajur.submissions.index') ? 'active' : '' }}" href="{{ route('kajur.submissions.index') }}">
                                                <span class="menu-icon"><i class="bi bi-list-ul fs-5"></i></span>
                                                <span class="menu-title">Daftar Pengajuan</span>
                                            </a>
                                        </div>
                                        <div class="menu-item">
                                            <a class="menu-link {{ request()->routeIs('kajur.submissions.create') ? 'active' : '' }}" href="{{ route('kajur.submissions.create') }}">
                                                <span class="menu-icon"><i class="bi bi-plus-circle fs-5"></i></span>
                                                <span class="menu-title">Buat Pengajuan Baru</span>
                                            </a>
                                        </div>
                                    </div>
                                </div>

                                <div class="menu-item">
                                    <a class="menu-link {{ request()->routeIs('kajur.inventory.*') ? 'active' : '' }}" href="{{ route('kajur.inventory.index') }}">
                                        <span class="menu-icon"><i class="bi bi-boxes fs-3"></i></span>
                                        <span class="menu-title">Inventaris Jurusan</span>
                                    </a>
                                </div>

                                <div class="menu-item">
                                    <a class="menu-link {{ request()->routeIs('documents.*') ? 'active' : '' }}" href="{{ route('documents.index') }}">
                                        <span class="menu-icon"><i class="bi bi-file-earmark-arrow-up fs-3"></i></span>
                                        <span class="menu-title">Dokumen Terlampir</span>
                                    </a>
                                </div>

                                <div class="menu-item">
                                    <a class="menu-link {{ request()->routeIs('reports.*') ? 'active' : '' }}" href="{{ route('reports.index') }}">
                                        <span class="menu-icon"><i class="bi bi-graph-up-arrow fs-3"></i></span>
                                        <span class="menu-title">Laporan Jurusan</span>
                                    </a>
                                </div>
                            @endif

                            {{-- ======================= MENU SARPRAS ======================= --}}
                            @if(auth()->user()->isSarpras())
                                <div class="menu-item pt-5">
                                    <div class="menu-content">
                                        <span class="menu-section-label">Master Data</span>
                                    </div>
                                </div>

                                <div class="menu-item menu-accordion {{ request()->routeIs('sarpras.inventory.*') ? 'open' : '' }}" data-kt-menu-trigger="click">
                                    <span class="menu-link {{ request()->routeIs('sarpras.inventory.*') ? 'active' : '' }}">
                                        <span class="menu-icon"><i class="bi bi-archive fs-3"></i></span>
                                        <span class="menu-title">Inventaris Barang</span>
                                        <span class="menu-arrow"></span>
                                    </span>
                                    <div class="menu-sub menu-sub-accordion">
                                        <div class="menu-item">
                                            <a class="menu-link {{ request()->routeIs('sarpras.inventory.index') ? 'active' : '' }}" href="{{ route('sarpras.inventory.index') }}">
                                                <span class="menu-icon"><i class="bi bi-list-ul fs-5"></i></span>
                                                <span class="menu-title">Master Barang</span>
                                            </a>
                                        </div>
                                        <div class="menu-item">
                                            <a class="menu-link {{ request()->routeIs('sarpras.inventory.create') ? 'active' : '' }}" href="{{ route('sarpras.inventory.create') }}">
                                                <span class="menu-icon"><i class="bi bi-plus-circle fs-5"></i></span>
                                                <span class="menu-title">Registrasi Barang</span>
                                            </a>
                                        </div>
                                    </div>
                                </div>

                                <div class="menu-item">
                                    <a class="menu-link {{ request()->routeIs('sarpras.locations.*') ? 'active' : '' }}" href="{{ route('sarpras.locations.index') }}">
                                        <span class="menu-icon"><i class="bi bi-geo-alt fs-3"></i></span>
                                        <span class="menu-title">Master Lokasi</span>
                                    </a>
                                </div>

                                <div class="menu-item pt-5">
                                    <div class="menu-content">
                                        <span class="menu-section-label">Logistik</span>
                                    </div>
                                </div>

                                <div class="menu-item">
                                    <a class="menu-link {{ request()->routeIs('sarpras.logistics.incoming*') ? 'active' : '' }}" href="{{ route('sarpras.logistics.incoming') }}">
                                        <span class="menu-icon"><i class="bi bi-box-arrow-in-down fs-3"></i></span>
                                        <span class="menu-title">Barang Masuk</span>
                                    </a>
                                </div>

                                <div class="menu-item">
                                    <a class="menu-link {{ request()->routeIs('sarpras.logistics.outgoing*') ? 'active' : '' }}" href="{{ route('sarpras.logistics.outgoing') }}">
                                        <span class="menu-icon"><i class="bi bi-box-arrow-up fs-3"></i></span>
                                        <span class="menu-title">Barang Keluar</span>
                                    </a>
                                </div>

                                <div class="menu-item">
                                    <a class="menu-link {{ request()->routeIs('sarpras.logistics.distributions*') ? 'active' : '' }}" href="{{ route('sarpras.logistics.distributions') }}">
                                        <span class="menu-icon"><i class="bi bi-send fs-3"></i></span>
                                        <span class="menu-title">Distribusi / Penyaluran</span>
                                    </a>
                                </div>

                                <div class="menu-item">
                                    <a class="menu-link {{ request()->routeIs('sarpras.loans.*') ? 'active' : '' }}" href="{{ route('sarpras.loans.index') }}">
                                        <span class="menu-icon"><i class="bi bi-arrow-left-right fs-3"></i></span>
                                        <span class="menu-title">Peminjaman Barang</span>
                                    </a>
                                </div>

                                <div class="menu-item pt-5">
                                    <div class="menu-content">
                                        <span class="menu-section-label">Administrasi</span>
                                    </div>
                                </div>

                                <div class="menu-item">
                                    <a class="menu-link {{ request()->routeIs('sarpras.submissions.*') ? 'active' : '' }}" href="{{ route('sarpras.submissions.index') }}">
                                        <span class="menu-icon"><i class="bi bi-clipboard-check fs-3"></i></span>
                                        <span class="menu-title">Review Pengajuan Kajur</span>
                                    </a>
                                </div>

                                <div class="menu-item">
                                    <a class="menu-link {{ request()->routeIs('sarpras.users.*') ? 'active' : '' }}" href="{{ route('sarpras.users.index') }}">
                                        <span class="menu-icon"><i class="bi bi-people fs-3"></i></span>
                                        <span class="menu-title">Manajemen Pengguna</span>
                                    </a>
                                </div>

                                <div class="menu-item">
                                    <a class="menu-link {{ request()->routeIs('sarpras.activity_logs.*') ? 'active' : '' }}" href="{{ route('sarpras.activity_logs.index') }}">
                                        <span class="menu-icon"><i class="bi bi-clock-history fs-3"></i></span>
                                        <span class="menu-title">Riwayat Aktivitas</span>
                                    </a>
                                </div>

                                <div class="menu-item">
                                    <a class="menu-link {{ request()->routeIs('documents.*') ? 'active' : '' }}" href="{{ route('documents.index') }}">
                                        <span class="menu-icon"><i class="bi bi-folder-symlink fs-3"></i></span>
                                        <span class="menu-title">Dokumen & Berkas</span>
                                    </a>
                                </div>

                                <div class="menu-item">
                                    <a class="menu-link {{ request()->routeIs('reports.*') ? 'active' : '' }}" href="{{ route('reports.index') }}">
                                        <span class="menu-icon"><i class="bi bi-bar-chart-line fs-3"></i></span>
                                        <span class="menu-title">Laporan & Rekap</span>
                                    </a>
                                </div>
                            @endif

                            {{-- ======================= MENU KEPALA SEKOLAH ======================= --}}
                            @if(auth()->user()->isKepalaSekolah())
                                <div class="menu-item pt-5">
                                    <div class="menu-content">
                                        <span class="menu-section-label">Menu Kepala Sekolah</span>
                                    </div>
                                </div>

                                <div class="menu-item menu-accordion {{ request()->routeIs('kepala_sekolah.approval.*') ? 'open' : '' }}" data-kt-menu-trigger="click">
                                    <span class="menu-link {{ request()->routeIs('kepala_sekolah.approval.*') ? 'active' : '' }}">
                                        <span class="menu-icon"><i class="bi bi-person-check fs-3"></i></span>
                                        <span class="menu-title">Persetujuan Pengajuan</span>
                                        <span class="menu-arrow"></span>
                                    </span>
                                    <div class="menu-sub menu-sub-accordion">
                                        <div class="menu-item">
                                            <a class="menu-link {{ request()->routeIs('kepala_sekolah.approval.index') ? 'active' : '' }}" href="{{ route('kepala_sekolah.approval.index') }}">
                                                <span class="menu-icon"><i class="bi bi-list-check fs-5"></i></span>
                                                <span class="menu-title">Daftar Pengajuan</span>
                                            </a>
                                        </div>
                                    </div>
                                </div>

                                <div class="menu-item">
                                    <a class="menu-link {{ request()->routeIs('documents.*') ? 'active' : '' }}" href="{{ route('documents.index') }}">
                                        <span class="menu-icon"><i class="bi bi-folder-symlink fs-3"></i></span>
                                        <span class="menu-title">Dokumen & BAST</span>
                                    </a>
                                </div>

                                <div class="menu-item">
                                    <a class="menu-link {{ request()->routeIs('reports.*') ? 'active' : '' }}" href="{{ route('reports.index') }}">
                                        <span class="menu-icon"><i class="bi bi-graph-up fs-3"></i></span>
                                        <span class="menu-title">Laporan & Monitoring</span>
                                    </a>
                                </div>
                            @endif

                        </div>
                    </div>
                </div>

                <!-- Aside Footer (User Info) -->
                <div class="aside-footer flex-column-auto pt-5 pb-7 px-5" id="kt_aside_footer">
                    <div class="d-flex align-items-center bg-gray-800 rounded p-3">
                        <div class="symbol symbol-35px me-3">
                            <span class="symbol-label fs-6 fw-bolder text-white" style="background: linear-gradient(135deg, #009ef7, #0066cc);">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                        </div>
                        <div class="d-flex flex-column text-truncate flex-grow-1">
                            <span class="text-white fw-bolder fs-7 text-truncate" title="{{ auth()->user()->name }}">{{ auth()->user()->name }}</span>
                            <span class="text-gray-500 fs-8">{{ strtoupper(str_replace('_', ' ', auth()->user()->role)) }}</span>
                        </div>
                        <form action="{{ route('logout') }}" method="POST" class="d-inline ms-2">
                            @csrf
                            <button type="submit" class="btn btn-icon btn-sm btn-active-color-danger" title="Keluar">
                                <i class="bi bi-box-arrow-right fs-5 text-gray-400"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Wrapper Utama Konten -->
            <div class="wrapper d-flex flex-column flex-row-fluid" id="kt_wrapper">
                <!-- Header Atas -->
                <div id="kt_header" class="header align-items-stretch">
                    <div class="container-fluid d-flex align-items-stretch justify-content-between">
                        <div class="d-flex align-items-center d-lg-none ms-n3 me-1" title="Show aside menu">
                            <div class="btn btn-icon btn-active-color-white" id="kt_aside_mobile_toggle">
                                <i class="bi bi-list fs-1"></i>
                            </div>
                        </div>

                        <div class="d-flex align-items-center flex-grow-1 flex-lg-grow-0">
                            <h1 class="d-flex align-items-center text-dark fw-bolder fs-3 my-1">
                                @yield('header-title', 'Dashboard')
                            </h1>
                        </div>

                        <!-- Header Right: Role badge + Logout -->
                        <div class="d-flex align-items-stretch flex-shrink-0">
                            <div class="d-flex align-items-center gap-3">
                                <span class="badge badge-light-primary fw-bolder d-none d-md-flex align-items-center gap-1 px-3 py-2">
                                    <i class="bi bi-shield-check me-1"></i>
                                    {{ strtoupper(str_replace('_', ' ', auth()->user()->role)) }}
                                    @if(auth()->user()->department)
                                        — {{ auth()->user()->departmentLabel() }}
                                    @endif
                                </span>
                                <form action="{{ route('logout') }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-light-danger fw-bolder">
                                        <i class="bi bi-box-arrow-right me-1"></i> Keluar
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Konten Halaman -->
                <div class="content d-flex flex-column flex-column-fluid" id="kt_content">
                    <div class="post d-flex flex-column-fluid" id="kt_post">
                        <div id="kt_content_container" class="container-xxl">
                            @if(session('success'))
                                <div class="alert alert-dismissible bg-light-success border border-success d-flex align-items-center p-5 mb-6 fade show" role="alert">
                                    <i class="bi bi-check-circle-fill text-success fs-3 me-4"></i>
                                    <div class="d-flex flex-column">
                                        <span class="fw-bold text-success">{{ session('success') }}</span>
                                    </div>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            @endif
                            @if(session('error'))
                                <div class="alert alert-dismissible bg-light-danger border border-danger d-flex align-items-center p-5 mb-6 fade show" role="alert">
                                    <i class="bi bi-exclamation-circle-fill text-danger fs-3 me-4"></i>
                                    <div class="d-flex flex-column">
                                        <span class="fw-bold text-danger">{{ session('error') }}</span>
                                    </div>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            @endif
                            @yield('content')
                        </div>
                    </div>
                </div>

                <!-- Footer Bawah -->
                <div class="footer py-4 d-flex flex-lg-column" id="kt_footer">
                    <div class="container-fluid d-flex flex-column flex-md-row align-items-center justify-content-between">
                        <div class="text-dark order-2 order-md-1">
                            <span class="text-muted fw-bold me-1">&copy; {{ date('Y') }}</span>
                            <span class="text-gray-800 text-hover-primary">Sistem Informasi Inventaris dan Logistik Sekolah</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts Metronic -->
    <script src="{{ asset('assets/plugins/global/plugins.bundle.js') }}"></script>
    <script src="{{ asset('assets/js/scripts.bundle.js') }}"></script>
    <!-- DateRangePicker -->
    <script src="{{ asset('plugins/daterangepicker/moment.min.js') }}"></script>
    <script src="{{ asset('plugins/daterangepicker/daterangepicker.js') }}"></script>

    <!-- App Script (Dynamic rows, submit guard) -->
    @vite('resources/js/app.js')
    @stack('scripts')
</body>
</html>
