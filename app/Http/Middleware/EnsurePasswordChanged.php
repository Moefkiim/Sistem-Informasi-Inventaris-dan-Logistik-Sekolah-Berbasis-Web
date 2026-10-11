<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->must_change_password) {
            // Pengguna dengan must_change_password hanya boleh mengakses profil (ubah password) dan logout
            if (! $request->routeIs('profile.*') && ! $request->routeIs('logout')) {
                return redirect()->route('profile.show')
                    ->with('warning', 'Demi keamanan, Anda harus memperbarui kata sandi terlebih dahulu sebelum dapat mengakses fitur lain.');
            }
        }

        return $next($request);
    }
}
