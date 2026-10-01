<?php

namespace App\Http\Controllers\Sarpras;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $users = User::latest()->paginate(15);
        return view('sarpras.users.index', compact('users'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:50', 'unique:users,username'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['required', 'in:kajur,sarpras'],
            'department' => [
                'required_if:role,kajur',
                'nullable',
                'string',
                'max:100',
                function ($attribute, $value, $fail) use ($request) {
                    if ($request->input('role') === 'kajur' && empty(trim((string) $value))) {
                        $fail('Jurusan wajib diisi jika peran pengguna adalah Kajur.');
                    }
                },
            ],
        ]);

        // role & is_active dikecualikan dari $fillable — set secara eksplisit
        $user = new User();
        $user->name       = $validated['name'];
        $user->username   = $validated['username'];
        $user->email      = $validated['email'];
        $user->password   = $validated['password'];
        $user->role       = $validated['role'];
        $user->department = $validated['department'] ?? null;
        $user->is_active  = true;
        $user->save();

        return back()->with('success', 'Pengguna baru berhasil ditambahkan.');
    }

    public function toggleStatus(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->withErrors(['msg' => 'Anda tidak dapat menonaktifkan akun sendiri.']);
        }

        if (in_array($user->role, ['kepala_sekolah', 'sarpras'])) {
            return back()->withErrors(['msg' => 'Anda tidak memiliki wewenang untuk mengubah status akun Kepala Sekolah atau Sarpras.']);
        }

        // is_active dikecualikan dari $fillable — set secara eksplisit
        $user->is_active = ! $user->is_active;
        $user->save();

        $statusText = $user->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return back()->with('success', "Status akun pengguna {$user->name} berhasil {$statusText}.");
    }
}
