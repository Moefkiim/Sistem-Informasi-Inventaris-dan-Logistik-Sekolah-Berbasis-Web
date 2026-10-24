<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Menambahkan header keamanan HTTP dan no-store untuk respons HTML terautentikasi.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Header keamanan dasar untuk melindungi dari clickjacking, MIME sniffing, referrer leak
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // HSTS hanya dikirim jika koneksi aman (HTTPS) dan dikonfigurasi aktif
        if ($request->isSecure() && config('siils.hsts', false)) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Halaman HTML terautentikasi tidak boleh di-cache oleh browser (komputer bersama)
        if ($request->user() !== null) {
            $contentType = (string) $response->headers->get('Content-Type', '');
            if (str_contains($contentType, 'text/html')) {
                $response->headers->set('Cache-Control', 'no-store, private');
                $response->headers->set('Pragma', 'no-cache');
            }
        }

        return $response;
    }
}
