<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Tampilkan informasi akun dan form ganti kata sandi.
     */
    public function show(Request $request): View
    {
        return view('profile.show', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Perbarui kata sandi akun pengguna.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password' => [
                'required',
                'string',
                Password::min(8)->letters()->numbers(),
                'confirmed',
                'different:current_password',
            ],
        ], [
            'current_password.required' => 'Kata sandi saat ini wajib diisi.',
            'current_password.current_password' => 'Kata sandi saat ini yang Anda masukkan salah.',
            'password.required' => 'Kata sandi baru wajib diisi.',
            'password.min' => 'Kata sandi baru minimal 8 karakter.',
            'password.letters' => 'Kata sandi baru harus mengandung kombinasi huruf.',
            'password.numbers' => 'Kata sandi baru harus mengandung kombinasi angka.',
            'password.confirmed' => 'Konfirmasi kata sandi baru tidak cocok.',
            'password.different' => 'Kata sandi baru tidak boleh sama dengan kata sandi lama.',
        ]);

        $user = $request->user();
        $user->password = Hash::make($validated['password']);
        $user->must_change_password = false;
        $user->save();

        ActivityLog::log(
            action: 'user_password_changed',
            description: "Pengguna {$user->name} memperbarui kata sandi akun.",
            auditable: $user,
            label: $user->username
        );

        return redirect()->route('profile.show')->with('success', 'Kata sandi akun Anda berhasil diperbarui.');
    }
}
