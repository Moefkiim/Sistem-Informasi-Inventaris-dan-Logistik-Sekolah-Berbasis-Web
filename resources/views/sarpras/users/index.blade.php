@extends('layouts.app')

@section('title', 'Manajemen Pengguna')
@section('header-title', 'Manajemen Pengguna Sistem')

@section('content')
<div class="row g-5">
    <div class="col-xl-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header pt-6">
                <h4 class="fw-bolder">Tambah Pengguna Baru</h4>
            </div>
            <form action="{{ route('sarpras.users.store') }}" method="POST">
                @csrf
                <div class="card-body">
                    @if($errors->any())
                        <div class="alert alert-danger p-3 mb-4">
                            @foreach($errors->all() as $err)<div>{{ $err }}</div>@endforeach
                        </div>
                    @endif

                    <div class="mb-4">
                        <label class="form-label fw-bold required">Nama Lengkap</label>
                        <input type="text" name="name" class="form-control form-control-solid" placeholder="Nama pengguna" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold required">Username</label>
                        <input type="text" name="username" class="form-control form-control-solid" placeholder="username_unik" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold required">Email</label>
                        <input type="email" name="email" class="form-control form-control-solid" placeholder="user@sekolah.sch.id" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold required">Password Awal</label>
                        <input type="password" name="password" class="form-control form-control-solid" placeholder="Minimal 6 karakter" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold required">Peran (Role)</label>
                        <select name="role" class="form-select form-select-solid" required>
                            <option value="kajur">Kajur (Kepala Kejuruan)</option>
                            <option value="sarpras">Sarpras (Operator & Logistik)</option>
                            <option value="kepala_sekolah">Kepala Sekolah (Approval)</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Jurusan (Wajib jika role Kajur)</label>
                        <input type="text" name="department" class="form-control form-control-solid" placeholder="Contoh: Rekayasa Perangkat Lunak">
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-person-plus me-1"></i> Daftarkan Pengguna
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="col-xl-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header border-0 pt-6">
                <h3 class="fw-bolder">Daftar Akun Pengguna</h3>
            </div>
            <div class="card-body pt-0">
                @if(session('success'))
                    <div class="alert alert-success d-flex align-items-center p-3 mb-4">{{ session('success') }}</div>
                @endif

                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-7 gy-4">
                        <thead>
                            <tr class="text-start text-muted fw-bolder fs-7 text-uppercase gs-0">
                                <th>Nama</th>
                                <th>Username / Email</th>
                                <th>Role</th>
                                <th>Jurusan</th>
                                <th>Status</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-600 fw-bold">
                            @foreach($users as $u)
                                <tr>
                                    <td><span class="text-dark fw-bolder">{{ $u->name }}</span></td>
                                    <td>
                                        <div>{{ $u->username }}</div>
                                        <div class="text-muted fs-8">{{ $u->email }}</div>
                                    </td>
                                    <td><span class="badge badge-light-primary text-uppercase">{{ str_replace('_', ' ', $u->role) }}</span></td>
                                    <td>{{ $u->department ?: '-' }}</td>
                                    <td>
                                        @if($u->is_active)
                                            <span class="badge badge-light-success">Aktif</span>
                                        @else
                                            <span class="badge badge-light-danger">Nonaktif</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        @if($u->id !== auth()->id())
                                            <form action="{{ route('sarpras.users.toggle', $u) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-light-{{ $u->is_active ? 'warning' : 'success' }}" onclick="return confirm('Ubah status aktif pengguna ini?')">
                                                    {{ $u->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $users->links() }}</div>
            </div>
        </div>
    </div>
</div>
@endsection
