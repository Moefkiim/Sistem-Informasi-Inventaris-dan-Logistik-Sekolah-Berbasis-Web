<?php

namespace App\Http\Controllers\Sarpras;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\Departments;
use App\Support\SessionStore;
use App\Support\TemporaryPassword;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        // Kata sandi sementara hanya tampil sekali: nilai flash ditarik (pull)
        // begitu halaman pengguna dibaca agar tidak tersisa di payload sesi.
        $temporaryPassword = $request->session()->pull('temporary_password');

        // Bersihkan daftar flash agar kunci 'temporary_password' tidak
        // kembali di-put oleh ageFlashData pada request berikutnya.
        $request->session()->put('_flash.new', array_values(array_filter(
            $request->session()->get('_flash.new', []),
            fn ($key) => $key !== 'temporary_password',
        )));
        $request->session()->put('_flash.old', array_values(array_filter(
            $request->session()->get('_flash.old', []),
            fn ($key) => $key !== 'temporary_password',
        )));

        $users = User::latest()->paginate(15);

        return view('sarpras.users.index', compact('users', 'temporaryPassword'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:50', 'unique:users,username'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(8)->letters()->numbers()],
            'role' => ['required', 'in:kajur,sarpras'],
            'department' => ['required_if:role,kajur', 'nullable', Rule::in(Departments::codes())],
        ]);

        // role & is_active dikecualikan dari $fillable — set secara eksplisit
        $user = new User;
        $user->name = $validated['name'];
        $user->username = $validated['username'];
        $user->email = $validated['email'];
        $user->password = $validated['password'];
        $user->role = $validated['role'];
        $user->department = $validated['department'] ?? null;
        $user->is_active = true;
        $user->must_change_password = true;
        $user->save();

        ActivityLog::log(
            action: 'user_created',
            description: "Pengguna {$user->name} ({$user->username}) dibuat dengan peran {$user->role}; wajib mengganti kata sandi saat login.",
            auditable: $user,
            label: $user->username,
        );

        return back()->with('success', 'Pengguna baru berhasil ditambahkan. Kata sandi awal wajib diganti saat login pertama.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        if ($this->isProtected($user)) {
            return back()->withErrors(['msg' => 'Anda tidak memiliki wewenang untuk mengubah akun Kepala Sekolah atau Sarpras.']);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'department' => [
                $user->role === 'kajur' ? 'required' : 'nullable',
                Rule::in(Departments::codes()),
            ],
        ]);

        $oldName = $user->name;
        $oldDepartment = $user->department;

        $user->name = $validated['name'];
        $user->department = $user->role === 'kajur' ? $validated['department'] : null;
        $user->save();

        ActivityLog::log(
            action: 'user_updated',
            description: "Pengguna {$user->name} ({$user->username}) diperbarui.",
            auditable: $user,
            oldValues: ['name' => $oldName, 'department' => $oldDepartment],
            newValues: ['name' => $user->name, 'department' => $user->department],
            label: $user->username,
        );

        return back()->with('success', "Data pengguna {$user->name} berhasil diperbarui.");
    }

    public function resetPassword(User $user): RedirectResponse
    {
        if ($this->isProtected($user)) {
            return back()->withErrors(['msg' => 'Anda tidak memiliki wewenang untuk mereset kata sandi akun Kepala Sekolah atau Sarpras.']);
        }

        $temporary = TemporaryPassword::generate();

        // password di-cast 'hashed' sehingga nilai mentah otomatis di-hash saat disimpan
        $user->password = $temporary;
        $user->must_change_password = true;
        $user->save();

        $this->forgetSessions($user);

        ActivityLog::log(
            action: 'user_password_reset',
            description: "Kata sandi akun {$user->name} ({$user->username}) direset; wajib diganti saat login berikutnya.",
            auditable: $user,
            label: $user->username,
        );

        // Kata sandi sementara ditampilkan sekali lewat flash session, tidak disimpan dalam bentuk asli.
        return back()->with('temporary_password', [
            'name' => $user->name,
            'username' => $user->username,
            'password' => $temporary,
        ]);
    }

    public function toggleStatus(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->withErrors(['msg' => 'Anda tidak dapat menonaktifkan akun sendiri.']);
        }

        if ($this->isProtected($user)) {
            return back()->withErrors(['msg' => 'Anda tidak memiliki wewenang untuk mengubah status akun Kepala Sekolah atau Sarpras.']);
        }

        // is_active dikecualikan dari $fillable — set secara eksplisit
        $user->is_active = ! $user->is_active;
        $user->save();

        $statusText = $user->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return back()->with('success', "Status akun pengguna {$user->name} berhasil {$statusText}.");
    }

    private function isProtected(User $user): bool
    {
        return in_array($user->role, ['kepala_sekolah', 'sarpras'], true);
    }

    private function forgetSessions(User $user): void
    {
        SessionStore::forgetForUser($user);
    }
}
