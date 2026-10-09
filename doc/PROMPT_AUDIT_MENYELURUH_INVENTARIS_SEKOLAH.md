# MASTER PROMPT ANTIGRAVITY AGENT
## Audit Menyeluruh dan Perbaikan UI/UX, Alur Kerja, serta Fungsionalitas Sistem Inventaris dan Logistik Sekolah

Repositori: https://github.com/Moefkiim/Sistem-Informasi-Inventaris-dan-Logistik-Sekolah-Berbasis-Web.git

## 1. Peran dan tujuan

Bertindak sebagai Senior Laravel Engineer, UI/UX Designer, QA Engineer, dan analis proses bisnis sistem inventaris sekolah.

Lakukan audit menyeluruh terhadap kode yang benar-benar tersedia di workspace/repository. Setelah audit, langsung implementasikan perbaikan yang terbukti diperlukan. Fokus pada kemudahan pengoperasian oleh pengguna sekolah, konsistensi alur kerja, keamanan, akurasi data inventaris, dan kestabilan fitur.

Jangan berhenti pada daftar temuan atau rekomendasi. Perbaiki masalah yang dapat dibuktikan dari kode dan dapat ditangani dengan aman. Jangan mengarang temuan. Setiap temuan harus menyebutkan file/komponen terkait, dampaknya bagi pengguna, dan solusi yang diterapkan.

## 2. Aturan kerja yang wajib dipatuhi

1. Periksa struktur proyek, README, composer.json, package.json, routes, middleware, controller, model, migration, policy/gate, request validation, Blade, JavaScript, CSS, seeder, dan tests sebelum mengubah kode.
2. Identifikasi versi Laravel, pola arsitektur, template UI, komponen bersama, sistem role, dan konvensi penamaan yang benar-benar digunakan.
3. Periksa perubahan lokal yang belum di-commit. Jangan menimpa atau menghapus perubahan pengguna.
4. Buat ringkasan kondisi awal dan daftar prioritas sebelum implementasi.
5. Kerjakan secara bertahap. Pertahankan fitur dan tampilan yang sudah benar. Hindari rewrite besar-besaran.
6. Jangan membuat controller, layout, komponen, route, atau sistem status duplikat jika sudah ada implementasi yang sesuai. Reuse pola proyek yang ada.
7. Jangan mengubah nama route, kolom database, relasi, status, atau struktur data secara luas tanpa memeriksa semua pemakainya.
8. Jangan menjalankan perintah destruktif seperti `migrate:fresh`, `db:wipe`, penghapusan data, atau reset database.
9. Jangan menampilkan, menyalin, atau mengubah secret dari `.env`. Jangan memasukkan kredensial ke kode atau dokumentasi.
10. Jangan menambahkan dependency baru jika fitur bisa dibuat menggunakan stack yang sudah terpasang. Jika dependency benar-benar diperlukan, jelaskan alasan dan dampaknya.
11. Jangan membuat data contoh palsu di lingkungan produksi. Seeder hanya untuk kebutuhan pengujian yang sudah sesuai pola proyek.
12. Jangan menandai masalah sebagai selesai jika belum diuji. Bedakan antara diperbaiki, diuji, dan belum dapat diverifikasi.
13. Gunakan bahasa Indonesia yang konsisten untuk teks antarmuka, label, validasi, notifikasi, tombol, dan status.
14. Jangan hanya mempercantik dashboard. Prioritaskan alur tugas yang benar-benar digunakan petugas sekolah.
15. Jika akses ke repository, database, browser, atau layanan tertentu tidak tersedia, jelaskan keterbatasannya dan lanjutkan dengan pemeriksaan yang memang dapat dilakukan.

## 3. Pahami proses bisnis sekolah

Gunakan aturan berikut sebagai konteks kebutuhan. Cocokkan dengan implementasi yang ada dan tandai bagian yang belum terwakili.

- Sistem mengelola kategori inventaris/KIB. Informasi awal menyebut KIB A untuk tanah, KIB B untuk peralatan dan mesin/aset terkait, KIB C untuk gedung dan bangunan, serta KIB E untuk aset tetap lainnya seperti buku. Verifikasi struktur kategori aktual dan jangan mengarang klasifikasi KIB yang belum dikonfirmasi. Jika aplikasi mengklaim mengelola lima KIB, audit apakah KIB D dan KIB F perlu didukung berdasarkan kebutuhan resmi sekolah.
- Barang dapat berasal dari pembelian maupun bantuan.
- Barang disimpan di gudang dan di lokasi masing-masing jurusan.
- Jurusan yang terlibat meliputi Teknik Permesinan, Otomotif, Teknik Ketenagalistrikan, Komputer/RPL, dan Akuntansi. Periksa apakah jurusan dikelola sebagai data master, bukan hard-coded di banyak tempat.
- Alur permintaan pembelian yang diharapkan: jurusan mengajukan kebutuhan melalui Ketua Jurusan (Kajur), Sarpras meninjau dan memverifikasi, Kepala Sekolah menyetujui atau menolak, lalu sekolah melakukan pembelian setelah disetujui.
- Perencanaan pembelian mengikuti RAKS dan tahun anggaran. Permintaan perlu memiliki konteks tahun anggaran yang jelas. Jangan mengasumsikan semua pengajuan pasti dibeli.
- Barang habis pakai perlu dicatat dengan perlakuan stok yang sesuai, terpisah dari aset individual yang memiliki identitas/unit.
- Peminjaman dan pengembalian harus memperbarui status transaksi dan barang secara konsisten.
- Laporan inventaris dan realisasi dibutuhkan secara berkala, termasuk laporan tiga bulanan jika data yang tersedia mendukungnya.
- Jangan mengubah kebijakan bisnis yang belum pasti secara sepihak. Jika ada aturan yang tidak jelas atau implementasi yang bertentangan, dokumentasikan sebagai keputusan yang perlu dikonfirmasi dan pilih solusi paling aman yang konsisten dengan kode saat ini.

## 4. Audit UI/UX secara menyeluruh

Periksa semua halaman penting untuk setiap role, bukan hanya halaman dashboard.

### A. Navigasi dan struktur halaman
- Menu sesuai hak akses dan tugas masing-masing role.
- Nama menu mudah dipahami petugas sekolah.
- Menu aktif menunjukkan posisi pengguna.
- Breadcrumb tersedia pada halaman bertingkat jika membantu orientasi.
- Tidak ada tautan mati, tombol tanpa aksi, route yang salah, halaman kosong, atau menu yang mengarah ke halaman yang tidak sesuai.
- Pengguna dapat kembali ke daftar tanpa kehilangan konteks pencarian/filter jika pola aplikasi memungkinkan.
- Hindari menu ganda yang mengarah ke fungsi sama.
- Pastikan layout sidebar, topbar, konten, modal, dan footer konsisten.

### B. Dashboard
- Tampilkan ringkasan yang relevan untuk role yang sedang login.
- Angka statistik harus berasal dari query yang benar dan definisi status yang jelas.
- Kartu statistik dapat menuju daftar terfilter jika sesuai.
- Utamakan tugas yang perlu ditindaklanjuti, seperti permintaan menunggu persetujuan, peminjaman terlambat, stok rendah, atau barang yang perlu diverifikasi, hanya jika fitur dan datanya tersedia.
- Jangan menampilkan angka dekoratif, data hard-coded, atau grafik tanpa sumber data yang benar.
- Pastikan dashboard tidak memuat seluruh data tanpa pagination atau agregasi yang diperlukan.

### C. Tabel dan daftar data
- Judul halaman, deskripsi singkat, tombol aksi utama, pencarian, filter, dan tombol reset filter jelas.
- Tampilkan empty state yang menjelaskan kondisi dan langkah berikutnya.
- Bedakan kondisi data memang kosong dari hasil filter yang tidak menemukan data.
- Pertahankan filter saat pagination bila memungkinkan.
- Gunakan pagination server-side untuk daftar besar.
- Kolom tabel memiliki label jelas, alignment konsisten, dan informasi penting tidak terpotong tanpa cara melihat detail.
- Status menggunakan badge yang konsisten dan dapat dipahami.
- Tombol aksi per baris tidak terlalu padat dan tidak membingungkan.
- Aksi berisiko seperti hapus harus meminta konfirmasi yang kontekstual.
- Jangan gunakan warna sebagai satu-satunya penanda status.

### D. Form input dan validasi
- Label setiap input jelas. Jangan hanya mengandalkan placeholder.
- Tandai field wajib dan opsional.
- Tampilkan pesan validasi di dekat field dan pertahankan input lama saat validasi gagal.
- Format tanggal, angka, mata uang rupiah, kuantitas, dan satuan konsisten.
- Dropdown data master memiliki opsi yang benar dan tidak menampilkan data yang tidak relevan.
- Untuk form panjang, kelompokkan field menjadi bagian yang masuk akal.
- Hindari input yang dapat menyebabkan salah pilih, duplikasi, atau perubahan data tanpa sengaja.
- Berikan ringkasan dan konfirmasi sebelum tindakan penting.
- Pastikan modal dapat ditutup dengan aman, fokus keyboard tidak terperangkap, dan pesan kesalahan tidak hilang.

### E. Responsif dan aksesibilitas
- Periksa tampilan desktop, tablet, dan ponsel.
- Tabel lebar memiliki strategi responsif yang sesuai, bukan membuat seluruh halaman melebar tanpa kendali.
- Tombol dan input nyaman digunakan dengan sentuhan.
- Kontras teks cukup, fokus keyboard terlihat, label form terhubung dengan input, dan ikon penting memiliki nama/label aksesibel.
- Pastikan menu mobile dapat dibuka dan ditutup.
- Hormati preferensi reduce-motion jika animasi digunakan.
- Jangan menambahkan animasi yang menghambat tugas utama.

### F. Feedback dan kondisi sistem
- Setiap operasi simpan, ubah, hapus, setujui, tolak, pinjam, dan kembalikan memberi feedback yang jelas.
- Tampilkan loading state ketika aksi memang memerlukan proses.
- Cegah pengiriman form ganda.
- Gunakan pesan sukses dan gagal yang spesifik, tidak menampilkan stack trace atau detail sensitif.
- Pertahankan input saat terjadi kesalahan validasi.
- Jika ada operasi gagal, jangan tampilkan pesan sukses palsu.

## 5. Audit alur kerja dan aturan bisnis

Telusuri setiap alur dari UI sampai route, controller, model, database, dan kembali ke tampilan.

### A. Data master dan inventaris
- Periksa CRUD barang, kategori, KIB, sumber perolehan, lokasi, gudang, jurusan, satuan, kondisi, pengguna, dan data master lain yang benar-benar tersedia.
- Cegah kode inventaris atau identitas barang duplikat sesuai aturan yang ada.
- Validasi relasi kategori, lokasi, jurusan, sumber perolehan, dan tahun perolehan.
- Bedakan aset individual dari barang habis pakai jika domain model mendukung keduanya.
- Pastikan perpindahan lokasi tercatat secara benar dan tidak membuat histori hilang jika histori memang dibutuhkan.
- Hapus data master yang masih dipakai harus ditolak atau menggunakan mekanisme aman sesuai relasi. Jangan menghapus histori transaksi.
- Audit import/export jika tersedia: validasi format, baris gagal, duplikasi, otorisasi, dan ringkasan hasil import.
- Periksa konsistensi stok dan kondisi barang setelah setiap transaksi.

### B. Peminjaman dan pengembalian
- Telusuri status dari pengajuan, persetujuan, peminjaman aktif, keterlambatan, pengembalian, pembatalan, penolakan, dan kondisi barang saat kembali sesuai status yang didukung aplikasi.
- Jangan menambahkan status baru tanpa meninjau semua query, filter, badge, laporan, dan transisi.
- Pastikan hanya role yang berwenang dapat menyetujui, menolak, meminjamkan, atau memproses pengembalian.
- Pastikan barang yang tidak tersedia, rusak, dihapus, atau sedang dipinjam tidak dapat dipinjam kembali secara tidak sah.
- Validasi jumlah pinjaman dan jumlah unit tersedia. Barang habis pakai tidak boleh diperlakukan sama dengan aset individual tanpa aturan eksplisit.
- Validasi tanggal pinjam dan jatuh tempo.
- Periksa logika keterlambatan terhadap tanggal lokal dan definisi jatuh tempo. Hindari mengubah status secara diam-diam tanpa pola aplikasi yang jelas.
- Proses pengembalian harus memperbarui transaksi dan status unit secara atomik dalam database transaction bila melibatkan beberapa perubahan.
- Cegah pengembalian ganda, persetujuan ganda, dan race condition sejauh memungkinkan.
- Pastikan histori dan timeline menunjukkan kejadian yang benar, urutan waktu yang tepat, dan pelaku aksi jika datanya tersedia.
- Jangan menganggap keterlambatan otomatis berarti barang telah dikembalikan atau transaksi dapat ditutup.

### C. Pengajuan pembelian dan persetujuan
Jika modul ini tersedia, audit alur dari awal hingga akhir:
1. Jurusan membuat permintaan kebutuhan dengan barang, kuantitas, alasan, prioritas, tahun anggaran, dan informasi RAKS jika memang disediakan.
2. Kajur memeriksa atau meneruskan permintaan sesuai hak akses yang ada.
3. Sarpras meninjau kebutuhan, ketersediaan stok, spesifikasi, dan kelengkapan.
4. Kepala Sekolah menyetujui atau menolak sesuai wewenang.
5. Pembelian dan penerimaan barang dicatat setelah persetujuan sesuai proses aktual.
6. Barang yang diterima menambah inventaris/stok secara tepat dan memiliki sumber perolehan yang benar.
7. Status, catatan, waktu, dan pelaku setiap keputusan dapat ditelusuri jika skema data mendukungnya.

Pastikan:
- Pengajuan yang ditolak tidak masuk ke stok.
- Permintaan yang masih menunggu tidak dianggap sebagai pembelian selesai.
- Pengajuan tidak dapat melompati tahap persetujuan dengan memanggil URL langsung.
- Perubahan status hanya melalui transisi yang valid.
- Jumlah yang diterima dapat dibandingkan dengan jumlah yang disetujui jika proses penerimaan mendukungnya.
- Permintaan yang sama tidak diproses dua kali.
- Riwayat alasan penolakan/perbaikan terlihat oleh pihak yang relevan.
- Pengguna memahami langkah selanjutnya dan siapa yang perlu bertindak.

Jika modul belum ada, jangan membuat modul lengkap baru secara otomatis dalam audit UI. Catat gap dan implementasikan hanya bagian yang dapat dibangun aman tanpa mengasumsikan skema atau kebijakan yang belum tersedia.

### D. Laporan
- Periksa laporan inventaris, peminjaman, pengembalian, keterlambatan, perolehan, lokasi, kategori/KIB, barang habis pakai, dan pengajuan pembelian jika tersedia.
- Cocokkan angka laporan dengan sumber data dan definisi status.
- Pastikan filter tanggal, jurusan, lokasi, kategori, sumber perolehan, dan tahun anggaran bekerja bila tersedia.
- Periksa periode laporan triwulanan. Definisikan rentang tanggal dengan benar dan jangan menggabungkan tahun berbeda secara keliru.
- Pastikan laporan hanya menampilkan data yang boleh diakses role terkait.
- Periksa hasil cetak, PDF, atau Excel jika fitur tersedia, termasuk header, tanggal cetak, filter yang dipakai, total, dan penanganan dataset kosong.
- Jangan menampilkan kolom sensitif yang tidak diperlukan.

## 6. Audit keamanan dan integritas data

- Periksa autentikasi dan otorisasi pada setiap route dan setiap aksi sensitif.
- Jangan mengandalkan penyembunyian tombol di UI sebagai pengamanan.
- Periksa middleware, policy, gate, role, dan pemeriksaan kepemilikan data.
- Cegah IDOR: pengguna tidak boleh mengakses atau mengubah record milik unit/role lain hanya dengan mengganti ID URL.
- Periksa validasi server-side untuk semua input, termasuk query string dan data dari JavaScript.
- Periksa mass assignment, penggunaan `$fillable`/`$guarded`, binding query, output escaping Blade, dan unggahan file.
- Jika upload tersedia, validasi MIME/jenis file, ukuran, nama penyimpanan, dan akses file.
- Jangan menerima status akhir atau identitas pelaku dari input pengguna biasa jika seharusnya ditentukan server.
- Periksa CSRF, rate limiting untuk aksi sensitif bila sesuai, dan logout/session.
- Gunakan database transaction untuk proses multi-tabel yang harus konsisten.
- Hindari N+1 query, query tanpa batas, dan agregasi dashboard yang tidak efisien.
- Jangan membocorkan exception, path server, secret, atau data pribadi melalui UI/log yang dapat diakses pengguna.
- Jangan membuat perubahan keamanan yang memutus akses role sah. Uji role yang diizinkan dan ditolak.

## 7. Audit konsistensi kode dan kualitas teknis

- Temukan route tidak terpakai, route duplikat, nama route yang tidak konsisten, dan link Blade yang rusak.
- Temukan controller/model/view yang tumpang tindih atau logika bisnis yang diduplikasi.
- Gunakan Form Request jika sesuai dengan pola proyek, tetapi jangan melakukan refactor luas hanya demi gaya.
- Periksa query berulang, eager loading, pagination, N+1, dan validasi.
- Pastikan JavaScript tidak bergantung pada elemen yang mungkin tidak ada dan tidak menghasilkan error console.
- Pastikan komponen Blade reusable digunakan konsisten.
- Periksa dependency dan asset build sesuai toolchain yang ada.
- Jalankan formatter, static checks, dan test yang sudah tersedia jika environment mendukung.
- Jangan mengubah versi framework atau melakukan upgrade dependency besar dalam tugas ini kecuali ada masalah kritis yang terbukti dan perubahannya diminta secara terpisah.

## 8. Urutan pelaksanaan

### Tahap 1: Pemetaan
1. Periksa branch, status Git, dan perubahan lokal.
2. Petakan role dan permission.
3. Petakan semua route, modul, controller, model, migration, view, dan laporan.
4. Buat matriks alur pengguna dari login hingga tugas selesai.
5. Catat temuan dengan prioritas:
   - P0: keamanan, kehilangan/korupsi data, transaksi inventaris salah.
   - P1: alur utama rusak, otorisasi salah, status/stok tidak konsisten.
   - P2: UI/UX yang menyebabkan salah operasi atau menghambat tugas.
   - P3: konsistensi visual, aksesibilitas, performa sekunder, dan perapian.
6. Setiap temuan harus berisi bukti file/route/fungsi dan dampak nyata.

### Tahap 2: Perbaikan inti
Perbaiki P0 dan P1 terlebih dahulu. Prioritaskan:
- hak akses server-side,
- validasi,
- transisi status,
- integritas stok dan transaksi,
- pengajuan/persetujuan,
- link dan route utama yang rusak,
- error yang menghalangi operasi.

### Tahap 3: Perbaikan UI/UX
Setelah alur inti aman:
- konsistenkan layout, judul halaman, tombol utama, badge status, form, tabel, filter, empty state, alert, modal, dan feedback;
- sederhanakan navigasi tanpa menghilangkan fitur;
- buat dashboard relevan untuk role;
- tingkatkan responsivitas dan aksesibilitas;
- pertahankan bahasa Indonesia yang jelas dan istilah sekolah yang konsisten.

Jangan melakukan redesign total jika sistem desain yang ada sudah layak. Utamakan perbaikan terukur yang konsisten dengan proyek.

### Tahap 4: Pengujian
- Jalankan test suite yang tersedia.
- Tambahkan atau perbarui test untuk bug yang diperbaiki.
- Periksa route list dan route names bila environment memungkinkan.
- Jalankan lint/format/build yang sesuai dengan konfigurasi proyek.
- Uji alur positif dan negatif untuk setiap aksi penting.
- Jika browser automation tersedia, uji UI pada desktop dan viewport mobile.
- Jika database atau layanan eksternal tidak tersedia, jangan mengarang hasil. Laporkan pengujian yang dilewati dan alasan spesifiknya.

## 9. Skenario pengujian minimum

Sesuaikan nama role dan status dengan implementasi aktual. Jangan membuat akun produksi atau mengubah data nyata.

1. Pengguna belum login tidak dapat mengakses halaman privat.
2. Role tanpa izin tidak dapat menjalankan aksi sensitif melalui URL langsung.
3. Pengguna dengan izin dapat menjalankan tugas yang sesuai.
4. Form invalid menampilkan pesan yang jelas dan tidak menyimpan data salah.
5. Pencarian, filter, reset filter, dan pagination tidak menghasilkan data yang keliru.
6. CRUD tidak membuat data duplikat atau menghapus histori transaksi.
7. Stok tidak menjadi negatif akibat transaksi yang tidak valid.
8. Dua aksi bersamaan tidak menyebabkan unit yang sama dipinjam atau diproses dua kali, sejauh dapat diuji.
9. Pengembalian memperbarui transaksi dan unit secara konsisten.
10. Status terlambat sesuai tanggal jatuh tempo dan zona waktu aplikasi.
11. Permintaan yang menunggu/ditolak tidak menambah stok.
12. Hanya role berwenang yang dapat menyetujui atau menolak permintaan.
13. Data yang diterima masuk ke inventaris dengan sumber, lokasi, kategori, dan jumlah yang benar.
14. Laporan sesuai dengan filter dan data sumber.
15. Halaman dengan dataset kosong, pencarian tanpa hasil, error validasi, dan error server memiliki tampilan yang dapat dipahami.
16. Navigasi, modal, form, tabel, dan tombol utama tetap dapat digunakan pada layar kecil.

## 10. Kriteria penerimaan

Pekerjaan dianggap selesai hanya jika:
- Temuan penting yang terbukti sudah diperbaiki atau diberi alasan jelas mengapa belum dapat diperbaiki.
- Tidak ada fitur lama yang dihapus tanpa alasan dan persetujuan.
- Tidak ada route, view, atau controller duplikat yang dibuat hanya untuk menutupi masalah.
- Hak akses diuji pada server-side.
- Perubahan status dan stok konsisten dengan aturan bisnis.
- UI memberi petunjuk, validasi, dan feedback yang jelas.
- Form, tabel, filter, dan navigasi utama nyaman digunakan.
- Test/build yang tersedia telah dijalankan atau keterbatasannya dilaporkan.
- Tidak ada secret atau data pribadi yang ditambahkan ke repository.
- Dokumentasi hasil audit dan pengujian dibuat.

## 11. Dokumentasi hasil

Buat file dokumentasi baru tanpa menimpa dokumentasi pengguna yang sudah ada, misalnya:
`docs/audit-perbaikan-menyeluruh.md`

Isi dokumentasi:
1. Ringkasan kondisi awal.
2. Modul dan role yang diperiksa.
3. Temuan berdasarkan prioritas P0-P3.
4. Bukti berupa path file, route, method, atau komponen.
5. Dampak masalah kepada pengguna.
6. Perubahan yang diterapkan.
7. File yang diubah dan alasan perubahan.
8. Skenario test, hasil aktual, dan perintah yang dijalankan.
9. Masalah yang belum selesai beserta alasannya.
10. Keputusan bisnis yang perlu dikonfirmasi pihak sekolah.
11. Rekomendasi lanjutan berdasarkan risiko.

Jangan menulis klaim “semua test lulus” kecuali perintah pengujian benar-benar dijalankan dan hasilnya mendukung klaim tersebut.

## 12. Laporan akhir kepada pengguna

Setelah implementasi, berikan ringkasan dalam bahasa Indonesia:
- Temuan paling penting.
- Perbaikan yang benar-benar dilakukan.
- Daftar file utama yang berubah.
- Test yang dijalankan beserta hasil aktual.
- Test yang belum dapat dijalankan dan alasannya.
- Risiko atau keputusan bisnis yang masih memerlukan konfirmasi.
- Cara pengguna memverifikasi perubahan secara manual.

Mulai dengan memeriksa repository dan kondisi kerja saat ini. Lanjutkan audit, implementasi, pengujian, dan dokumentasi. Jangan berhenti setelah membuat rencana.
