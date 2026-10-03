<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8" />
    <title>Login - Sistem Informasi Inventaris & Logistik Sekolah</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="shortcut icon" href="{{ asset('assets/media/logos/favicon.ico') }}" />
    <!-- Fonts -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700" />
    <!-- Metronic Global Stylesheets -->
    <link href="{{ asset('assets/plugins/global/plugins.bundle.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('assets/css/style.bundle.css') }}" rel="stylesheet" type="text/css" />
</head>
<body id="kt_body" class="bg-body">
    <div class="d-flex flex-column flex-root">
        <div class="d-flex flex-column flex-lg-row flex-column-fluid">
            <!-- Aside kiri / Banner Metronic -->
            <div class="d-flex flex-column flex-lg-row-auto w-xl-500px position-xl-relative" style="background-color: #1e1e2d">
                <div class="d-flex flex-column position-xl-fixed top-0 bottom-0 w-xl-500px scroll-y p-10 justify-content-between">
                    <div class="text-center pt-lg-15">
                        <div class="mb-5">
                            <i class="bi bi-box-seam text-primary" style="font-size: 3.5rem;"></i>
                        </div>
                        <h1 class="fw-bolder fs-2qx text-white pb-3">Inventaris & Logistik</h1>
                        <p class="fw-normal fs-4 text-gray-400">
                            Sistem Informasi Terpadu Pengelolaan Sarana Prasarana dan Logistik Sekolah
                        </p>
                    </div>

                    <div class="d-flex flex-row-auto bgi-no-repeat bgi-position-x-center bgi-size-contain bgi-position-y-bottom min-h-150px min-h-lg-250px"
                         style="background-image: url('{{ asset('assets/media/illustrations/sketchy-1/13.png') }}')">
                    </div>

                    <div class="text-center text-gray-500 fs-7 pb-4">
                        &copy; {{ date('Y') }} Sistem Informasi Inventaris & Logistik Sekolah
                    </div>
                </div>
            </div>

            <!-- Form Login Kanan -->
            <div class="d-flex flex-column flex-lg-row-fluid py-10">
                <div class="d-flex flex-center flex-column flex-column-fluid">
                    <div class="w-lg-450px p-10 p-lg-15 mx-auto">
                        <form class="form w-100" method="POST" action="{{ route('login') }}">
                            @csrf
                            <div class="text-center mb-10">
                                <h1 class="text-dark mb-3 fw-bolder">Selamat Datang</h1>
                                <div class="text-gray-400 fw-bold fs-6">Masuk menggunakan Username atau Email Anda</div>
                            </div>

                            @if ($errors->any())
                                <div class="alert alert-dismissible bg-light-danger d-flex flex-column flex-sm-row p-5 mb-8 border border-danger">
                                    <span class="svg-icon svg-icon-2hx svg-icon-danger me-4 mb-5 mb-sm-0">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                            <rect opacity="0.3" x="2" y="2" width="20" height="20" rx="10" fill="currentColor"/>
                                            <rect x="11" y="14" width="2" height="2" rx="1" fill="currentColor"/>
                                            <rect x="11" y="6" width="2" height="6" rx="1" fill="currentColor"/>
                                        </svg>
                                    </span>
                                    <div class="d-flex flex-column pe-0 pe-sm-10">
                                        <h5 class="mb-1 text-danger">Gagal Masuk</h5>
                                        @foreach ($errors->all() as $error)
                                            <span class="text-danger fs-7">{{ $error }}</span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            <div class="fv-row mb-7">
                                <label class="form-label fs-6 fw-bolder text-dark" for="login">Username atau Email</label>
                                <input
                                    class="form-control form-control-lg form-control-solid"
                                    type="text"
                                    name="login"
                                    id="login"
                                    value="{{ old('login') }}"
                                    placeholder="Contoh: kajur_rpl atau sarpras@sekolah.sch.id"
                                    required
                                    autofocus
                                    autocomplete="username"
                                />
                            </div>

                            <div class="fv-row mb-7">
                                <div class="d-flex flex-stack mb-2">
                                    <label class="form-label fw-bolder text-dark fs-6 mb-0" for="password">Password</label>
                                </div>
                                <input
                                    class="form-control form-control-lg form-control-solid"
                                    type="password"
                                    name="password"
                                    id="password"
                                    placeholder="Masukkan kata sandi"
                                    required
                                    autocomplete="current-password"
                                />
                            </div>

                            <div class="fv-row mb-8">
                                <label class="form-check form-check-custom form-check-solid form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="remember" value="1" />
                                    <span class="form-check-label fw-bold text-gray-700 fs-6">Ingat Saya</span>
                                </label>
                            </div>

                            <div class="text-center">
                                <button type="submit" class="btn btn-lg btn-primary w-100 mb-5">
                                    <span class="indicator-label">Masuk ke Sistem</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts Bundle Metronic -->
    <script src="{{ asset('assets/plugins/global/plugins.bundle.js') }}"></script>
    <script src="{{ asset('assets/js/scripts.bundle.js') }}"></script>
</body>
</html>
