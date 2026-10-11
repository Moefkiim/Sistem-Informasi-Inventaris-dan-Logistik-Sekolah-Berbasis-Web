<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8" />
    <title>@yield('title', 'Terjadi Kesalahan') - SIILS Sekolah</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="shortcut icon" href="{{ asset('assets/media/logos/favicon.ico') }}" />
    <!-- Fonts -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700" />
    <!-- Metronic Global Stylesheets -->
    <link href="{{ asset('assets/plugins/global/plugins.bundle.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('assets/css/style.bundle.css') }}" rel="stylesheet" type="text/css" />
    <style>
        body {
            background-color: #f5f8fa;
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 2rem 1rem;
        }
        .error-card {
            background: #ffffff;
            border-radius: 1rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            max-width: 620px;
            width: 100%;
            padding: 3rem 2.5rem;
            text-align: center;
        }
        .error-code {
            font-size: 5.5rem;
            font-weight: 800;
            line-height: 1;
            letter-spacing: -2px;
        }
        .error-title {
            font-size: 1.6rem;
            font-weight: 700;
            color: #181c32;
            margin-top: 1rem;
            margin-bottom: 0.75rem;
        }
        .error-desc {
            font-size: 1rem;
            color: #5e6278;
            line-height: 1.6;
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
    <div class="error-card">
        <div class="mb-3">
            <span class="error-code @yield('code-color', 'text-primary')">@yield('code', '500')</span>
        </div>
        <h1 class="error-title">@yield('heading', 'Terjadi Kesalahan')</h1>
        <p class="error-desc">@yield('message', 'Terjadi kendala pada sistem. Silakan coba kembali.')</p>

        <div class="d-flex justify-content-center flex-wrap gap-3">
            @yield('actions')

            @auth
                <a href="{{ route('home') }}" class="btn btn-primary px-5">
                    <i class="bi bi-speedometer2 me-1"></i> Kembali ke Dashboard
                </a>
            @else
                <a href="{{ route('login') }}" class="btn btn-primary px-5">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Masuk ke Sistem
                </a>
            @endauth
        </div>

        <div class="text-muted fs-8 mt-5 pt-3 border-top">
            &copy; {{ date('Y') }} Sistem Informasi Inventaris dan Logistik Sekolah
        </div>
    </div>
</body>
</html>
