@extends('layouts.app')

@section('title', 'Akun Saya')
@section('header-title', 'Profil & Pengaturan Akun')

@section('content')
<div class="row g-6">
    @if(session('warning'))
        <div class="col-12">
            <div class="alert alert-warning d-flex align-items-center p-5 border border-warning">
                <i class="bi bi-exclamation-triangle-fill text-warning fs-2 me-4"></i>
                <div class="fw-bold">{{ session('warning') }}</div>
            </div>
        </div>
    @endif

    @if($user->must_change_password)
        <div class="col-12">
            <div class="alert alert-danger d-flex align-items-center p-5 border border-danger">
                <i class="bi bi-shield-lock-fill text-danger fs-2 me-4"></i>
                <div>
                    <h5 class="text-danger fw-bolder mb-1">Perubahan Kata Sandi Diwajibkan</h5>
                    <span>Kata sandi Anda saat ini dibuat oleh administrator atau sistem. Demi keamanan akun, Anda wajib mengubah kata sandi sebelum dapat mengakses fitur lainnya.</span>
                </div>
            </div>
        </div>
    @endif

    <!-- Kolom Kiri: Informasi Profil Pengguna -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header border-0 pt-6">
                <h3 class="card-title fw-bolder">Informasi Akun</h3>
            </div>
            <div class="card-body pt-0">
                <div class="d-flex align-items-center mb-6">
                    <div class="symbol symbol-60px me-5">
                        <span class="symbol-label fs-2x fw-bolder text-white" style="background: linear-gradient(135deg, #009ef7, #0066cc);">
                            {{ strtoupper(substr($user->name, 0, 1)) }}
                        </span>
                    </div>
                    <div>
                        <div class="fw-bolder fs-5 text-gray-800">{{ $user->name }}</div>
                        <div class="text-muted fs-7">{{ $user->email ?: 'Tidak ada email' }}</div>
                        <span class="badge badge-light-primary fw-bolder mt-1">
                            {{ strtoupper(str_replace('_', ' ', $user->role)) }}
                        </span>
                    </div>
                </div>

                <div class="separator separator-dashed my-5"></div>

                <div class="d-flex flex-column gap-4 fs-6">
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Username</span>
                        <span class="fw-bold text-gray-800 font-monospace">{{ $user->username }}</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Peran / Hak Akses</span>
                        <span class="fw-bold text-gray-800">{{ strtoupper(str_replace('_', ' ', $user->role)) }}</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Jurusan</span>
                        <span class="fw-bold text-gray-800">{{ $user->departmentLabel() ?: 'Umum / Seluruh Sekolah' }}</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Status Akun</span>
                        <span class="badge badge-light-{{ $user->is_active ? 'success' : 'danger' }}">
                            {{ $user->is_active ? 'Aktif' : 'Nonaktif' }}
                        </span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Terdaftar Sejak</span>
                        <span class="fw-bold text-gray-800">{{ $user->created_at?->format('d M Y') ?? '—' }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Kolom Kanan: Form Ubah Kata Sandi -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header border-0 pt-6">
                <h3 class="card-title fw-bolder">Ubah Kata Sandi</h3>
            </div>
            <div class="card-body pt-0">
                <form action="{{ route('profile.password') }}" method="POST" data-disable-on-submit>
                    @csrf
                    @method('PUT')

                    <div class="mb-5">
                        <label class="form-label fw-bold required" for="current_password">Kata Sandi Saat Ini</label>
                        <input type="password" name="current_password" id="current_password"
                               class="form-control form-control-solid @error('current_password') is-invalid @enderror"
                               placeholder="Masukkan kata sandi saat ini" required autocomplete="current-password">
                        @error('current_password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-5">
                        <label class="form-label fw-bold required" for="password">Kata Sandi Baru</label>
                        <input type="password" name="password" id="password"
                               class="form-control form-control-solid @error('password') is-invalid @enderror"
                               placeholder="Minimal 8 karakter kombinasi huruf & angka" required autocomplete="new-password">
                        <div class="form-text text-muted fs-8">Minimal 8 karakter dan harus mengandung kombinasi huruf serta angka.</div>
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-6">
                        <label class="form-label fw-bold required" for="password_confirmation">Konfirmasi Kata Sandi Baru</label>
                        <input type="password" name="password_confirmation" id="password_confirmation"
                               class="form-control form-control-solid @error('password_confirmation') is-invalid @enderror"
                               placeholder="Ulangi kata sandi baru" required autocomplete="new-password">
                        @error('password_confirmation')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary px-6">
                            <i class="bi bi-check-lg me-1"></i> Simpan Kata Sandi
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
