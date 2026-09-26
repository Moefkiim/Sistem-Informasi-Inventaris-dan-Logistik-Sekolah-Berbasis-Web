# Sistem Informasi Inventaris dan Logistik Sekolah

## 1. Tujuan
Aplikasi web untuk mengelola inventaris dan logistik sekolah secara terpusat:
pengajuan, approval, barang masuk/keluar, distribusi, kondisi, lokasi, dokumen,
pengguna, dan laporan.

## 2. Role
- **Kajur**: membuat dan memantau pengajuan, melihat inventaris sesuai jurusan.
- **Sarpras**: mengelola inventaris, transaksi/log, lokasi, dokumen, pengguna,
  memproses pengajuan, dan membuat laporan.
- **Kepala Sekolah**: melihat pengajuan dan melakukan approval.

## 3. Scope Modul
1. Login & Authentication
2. Dashboard (read-only)
3. Pengajuan
4. Inventaris
5. Barang Masuk
6. Barang Keluar
7. Distribusi
8. Kondisi Barang
9. Lokasi
10. Laporan
11. Dokumen
12. Manajemen Pengguna

## 4. Aturan Bisnis Penting
- 1 kode barang = 1 jenis barang; jumlah disimpan sebagai stok.
- Barang berasal dari pembelian atau bantuan.
- Barang memiliki lokasi dan kondisi.
- Perpindahan lokasi harus memiliki riwayat.
- Perubahan kondisi harus menjadi riwayat baru.
- Barang masuk/keluar memengaruhi stok secara konsisten.
- Pengajuan dapat berisi banyak item.
- Kajur hanya dapat mengubah pengajuan selama masih draft.
- Sarpras memproses pengajuan.
- Kepala Sekolah melakukan approval/reject.
- Data log/history tidak boleh diedit atau dihapus bebas.
- Gunakan soft-delete/arsip untuk master data bila diperlukan agar histori tetap ada.
- Hak akses wajib berbasis role dan konteks jurusan.

## 5. Operasi Modul
| Modul | Pola |
|---|---|
| Login | Auth |
| Dashboard | Read-only |
| Pengajuan | Workflow + CRUD terbatas |
| Inventaris | Master CRUD terbatas |
| Barang Masuk | Log: Create + Read, koreksi terbatas |
| Barang Keluar | Log: Create + Read, koreksi terbatas |
| Distribusi | Log: Create + Read |
| Kondisi | History: Create + Read |
| Lokasi | Master CRUD |
| Laporan | Read + Filter + Export |
| Dokumen | Upload + Read/Download + Delete terbatas |
| Pengguna | CRUD terbatas + nonaktifkan |

## 6. Matriks Akses Ringkas
| Modul | Kajur | Sarpras | Kepala Sekolah |
|---|---|---|---|
| Login | ✓ | ✓ | ✓ |
| Dashboard | Read | Read | Read |
| Pengajuan | Create/Read/Edit Draft/Cancel | Review/Process | Approve/Reject |
| Inventaris | Read jurusan | CRUD terbatas | Read |
| Barang Masuk | Read sesuai akses | Create/Read/Koreksi | Read |
| Barang Keluar | Read sesuai akses | Create/Read/Koreksi | Read |
| Distribusi | Read sesuai akses | Create/Read | Read |
| Kondisi | Read | Create/Read | Read |
| Lokasi | Read | CRUD | Read |
| Laporan | Read sesuai akses | Generate/Export | Read |
| Dokumen | Upload/Read | Upload/Read/Delete terbatas | Read |
| Pengguna | - | CRUD terbatas | - |

## 7. Batasan
Jangan menambahkan barcode/QR, WhatsApp notification, mobile app, payment,
accounting, supplier management, maintenance kompleks, atau integrasi pihak ketiga
tanpa persetujuan eksplisit.

## 8. Aturan Kerja AI
- README ini adalah sumber konteks utama.
- Jangan coding sebelum ada persetujuan untuk tahap coding.
- Jangan mengubah requirement tanpa konfirmasi.
- Jangan menambah fitur di luar scope.
- Kerjakan satu tahap/task saja.
- Setelah task selesai: ringkas perubahan, file yang berubah, dan cara test.
- Jika ada ambiguity yang memengaruhi arsitektur/data, tanyakan dulu.
- Utamakan solusi sederhana dan realistis untuk solo developer.
- Pertahankan audit trail pada data transaksi/riwayat.
