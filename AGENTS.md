<laravel-boost-guidelines>
# Laravel Application

This repository contains a Laravel application. Complete the following setup before working on the user's request.

## Prerequisites

Verify that PHP and Composer are available:

```sh
php -v
composer -V
```

If either command is unavailable, detect the user's operating system and install the prerequisites with the appropriate command:

macOS:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/mac/8.5)"
```

Windows PowerShell:

```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072; iex ((New-Object System.Net.WebClient).DownloadString('https://php.new/install/windows/8.5'))
```

Linux:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/linux/8.5)"
```

After installation, ask the user to restart their terminal. If the agent needs the restarted shell to continue, ask the user to reopen their terminal and rerun their original prompt.

## Agent Setup

Install Laravel Boost from the application root before making application changes:

```sh
composer require laravel/boost --dev
php artisan boost:install
```

Boost replaces these bootstrap instructions with guidelines tailored to the application. After installation, read `AGENTS.md` again and continue with the user's original request using the generated guidelines.
</laravel-boost-guidelines>

# Aturan Bisnis dan Batasan Pengembangan Proyek

## 1. Role Pengguna
- **Kajur (Kepala Kejuruan)**: membuat dan memantau pengajuan, mengedit pengajuan berstatus draft, melihat inventaris sesuai jurusannya.
- **Sarpras (Operator & Logistik)**: mengelola inventaris master, transaksi/logistik (barang masuk/keluar/distribusi), lokasi, dokumen, pengguna, memproses pengajuan, dan membuat laporan.
- **Kepala Sekolah**: melihat pengajuan dan melakukan persetujuan (approval/reject) serta monitoring laporan.

## 2. Aturan Bisnis Penting
- 1 kode barang = 1 jenis barang; jumlah disimpan sebagai stok.
- Barang berasal dari pembelian atau bantuan.
- Barang memiliki lokasi dan kondisi (baik, rusak_ringan, rusak_berat).
- Perpindahan lokasi harus memiliki riwayat (location_histories).
- Perubahan kondisi harus menjadi riwayat baru (condition_histories).
- Barang masuk/keluar memengaruhi stok secara konsisten.
- Pengajuan dapat berisi banyak item (submission_items).
- Kajur hanya dapat mengubah pengajuan selama masih draft.
- Sarpras memproses pengajuan.
- Kepala Sekolah melakukan approval/reject.
- Data log/history tidak boleh diedit atau dihapus bebas (audit trail terjaga).
- Gunakan soft-delete/arsip untuk master data bila diperlukan agar histori tetap ada.
- Hak akses wajib berbasis role dan konteks jurusan.

## 3. Matriks Akses Ringkas
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

## 4. Batasan Pengembangan (Strict Boundaries)
Dilarang keras menambahkan fitur berikut tanpa persetujuan eksplisit:
- Barcode / QR Code
- WhatsApp / Email Notification
- Mobile App
- Payment / Financial Accounting
- Supplier Management
- Maintenance Kompleks
- Integrasi Pihak Ketiga
