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
            <form action="{{ route('sarpras.users.store') }}" method="POST" data-disable-on-submit>
                @csrf
                <div class="card-body">

                    <div class="mb-4">
                        <label class="form-label fw-bold required">Nama Lengkap</label>
                        <input type="text" name="name" class="form-control form-control-solid @error('name') is-invalid @enderror" value="{{ old('name') }}" placeholder="Nama pengguna" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold required">Username</label>
                        <input type="text" name="username" class="form-control form-control-solid @error('username') is-invalid @enderror" value="{{ old('username') }}" placeholder="username_unik" required>
                        @error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold required">Email</label>
                        <input type="email" name="email" class="form-control form-control-solid @error('email') is-invalid @enderror" value="{{ old('email') }}" placeholder="user@sekolah.sch.id" required>
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold required">Password Awal</label>
                        <input type="password" name="password" class="form-control form-control-solid @error('password') is-invalid @enderror" placeholder="Minimal 8 karakter (huruf & angka)" required>
                        <div class="form-text text-muted fs-8">Minimal 8 karakter dan harus mengandung kombinasi huruf serta angka. Pengguna wajib menggantinya saat login pertama.</div>
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold required">Peran (Role)</label>
                        <select name="role" class="form-select form-select-solid @error('role') is-invalid @enderror" required>
                            <option value="kajur" {{ old('role') == 'kajur' ? 'selected' : '' }}>Kajur (Kepala Kejuruan)</option>
                            <option value="sarpras" {{ old('role') == 'sarpras' ? 'selected' : '' }}>Sarpras (Operator & Logistik)</option>
                        </select>
                        @error('role')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Jurusan (Wajib jika role Kajur)</label>
                        <select name="department" class="form-select form-select-solid @error('department') is-invalid @enderror">
                            <option value="">— Pilih Jurusan —</option>
                            @foreach(config('departments') as $code => $name)
                                <option value="{{ $code }}" {{ old('department') == $code ? 'selected' : '' }}>{{ $name }} ({{ $code }})</option>
                            @endforeach
                        </select>
                        @error('department')<div class="invalid-feedback">{{ $message }}</div>@enderror
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
                                @php($protected = in_array($u->role, ['kepala_sekolah', 'sarpras'], true))
                                <tr>
                                    <td>
                                        <span class="text-dark fw-bolder">{{ $u->name }}</span>
                                        @if($u->must_change_password)
                                            <span class="badge badge-light-warning ms-1" title="Wajib ganti kata sandi">Wajib ganti sandi</span>
                                        @endif
                                    </td>
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
                                        @if($u->id !== auth()->id() && ! $protected)
                                            <button type="button" class="btn btn-sm btn-light-primary mb-1"
                                                    data-bs-toggle="modal" data-bs-target="#editUserModal"
                                                    data-user-id="{{ $u->id }}"
                                                    data-user-name="{{ $u->name }}"
                                                    data-user-role="{{ $u->role }}"
                                                    data-user-department="{{ $u->department }}">
                                                Edit
                                            </button>
                                            <form action="{{ route('sarpras.users.reset_password', $u) }}" method="POST" class="d-inline" onsubmit="return confirm('Reset kata sandi akun {{ $u->name }}? Kata sandi sementara akan ditampilkan satu kali dan wajib diganti saat login.')">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-light-info mb-1">Reset Sandi</button>
                                            </form>
                                            <form action="{{ route('sarpras.users.toggle', $u) }}" method="POST" class="d-inline" onsubmit="return confirm('Ubah status aktif pengguna ini?')">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-light-{{ $u->is_active ? 'warning' : 'success' }} mb-1">
                                                    {{ $u->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                                </button>
                                            </form>
                                        @else
                                            <span class="text-muted fs-8">Terkunci</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $users->withQueryString()->links() }}</div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Edit Pengguna -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="editUserForm" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title fw-bolder" id="editUserModalLabel">Edit Pengguna</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-4">
                        <label class="form-label fw-bold required">Nama Lengkap</label>
                        <input type="text" name="name" id="editUserName" class="form-control form-control-solid" required>
                    </div>
                    <div class="mb-2" id="editUserDepartmentWrapper">
                        <label class="form-label fw-bold required">Jurusan</label>
                        <select name="department" id="editUserDepartment" class="form-select form-select-solid">
                            <option value="">— Pilih Jurusan —</option>
                            @foreach(config('departments') as $code => $name)
                                <option value="{{ $code }}">{{ $name }} ({{ $code }})</option>
                            @endforeach
                        </select>
                        <div class="form-text text-muted fs-8">Wajib untuk akun Kajur.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

@if(!empty($temporaryPassword))
    @php($temp = $temporaryPassword)
    <div class="modal fade" id="tempPasswordModal" tabindex="-1" aria-labelledby="tempPasswordModalLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bolder" id="tempPasswordModalLabel">Kata Sandi Sementara</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning mb-4">
                        Kata sandi ini <strong>hanya ditampilkan sekali</strong>. Salin dan sampaikan ke pengguna. Pengguna wajib menggantinya saat login berikutnya.
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Akun</label>
                        <div class="form-control form-control-solid bg-light">{{ $temp['name'] }} ({{ $temp['username'] }})</div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-bold">Kata Sandi Sementara</label>
                        <div class="input-group">
                            <input type="text" id="tempPasswordValue" class="form-control form-control-solid font-monospace" value="{{ $temp['password'] }}" readonly>
                            <button type="button" class="btn btn-light-primary" id="tempPasswordCopyButton">Salin</button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Selesai</button>
                </div>
            </div>
        </div>
    </div>
@endif
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var editModal = document.getElementById('editUserModal');
        if (editModal) {
            editModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                if (!button) {
                    return;
                }

                var id = button.getAttribute('data-user-id');
                var role = button.getAttribute('data-user-role');
                var department = button.getAttribute('data-user-department') || '';

                var form = document.getElementById('editUserForm');
                form.setAttribute('action', '{{ url('sarpras/users') }}/' + id);

                document.getElementById('editUserName').value = button.getAttribute('data-user-name') || '';

                var wrapper = document.getElementById('editUserDepartmentWrapper');
                var select = document.getElementById('editUserDepartment');
                if (role === 'kajur') {
                    wrapper.style.display = '';
                    select.value = department;
                } else {
                    wrapper.style.display = 'none';
                    select.value = '';
                }
            });
        }

        var copyButton = document.getElementById('tempPasswordCopyButton');
        if (copyButton) {
            copyButton.addEventListener('click', function () {
                var input = document.getElementById('tempPasswordValue');
                var button = this;
                if (!input) {
                    return;
                }
                navigator.clipboard.writeText(input.value).then(function () {
                    button.textContent = 'Tersalin!';
                    setTimeout(function () { button.textContent = 'Salin'; }, 2000);
                }).catch(function () {
                    input.select();
                    document.execCommand('copy');
                    button.textContent = 'Tersalin!';
                    setTimeout(function () { button.textContent = 'Salin'; }, 2000);
                });
            });
        }

        var tempModal = document.getElementById('tempPasswordModal');
        if (tempModal) {
            bootstrap.Modal.getOrCreateInstance(tempModal).show();
        }
    });
</script>
@endpush
