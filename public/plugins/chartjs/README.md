Chart.js v4.4.7 (UMD build)
==========================

File `chart.umd.js` diambil dari jsdelivr CDN
(`https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.js`) dan di-vendor
ke sini mengikuti pola `public/plugins/daterangepicker` (library pihak ketiga
disimpan statis, tidak melalui npm/Vite). Lisensi: MIT (header lisensi
dipertahankan di bagian atas file).

Grafik dashboard (`resources/views/home.blade.php`) memuat file ini hanya pada
halaman Home via `{{ asset('plugins/chartjs/chart.umd.js') }}`.