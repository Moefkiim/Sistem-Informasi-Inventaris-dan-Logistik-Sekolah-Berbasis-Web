<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([__DIR__.'/../app/Console/Commands'])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureUserRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (HttpException $e, Request $request) {
            // Sesi habis atau 419 pada form submit / TokenMismatch
            if ($e->getStatusCode() === 419) {
                if ($request->expectsJson()) {
                    return response()->json(['message' => 'Sesi Anda berakhir, silakan masuk lagi.'], 419);
                }

                // Jika pemanggilan abort(419) langsung via GET tanpa token mismatch, biarkan render view errors.419
                if ($request->isMethod('GET') && ! ($e->getPrevious() instanceof TokenMismatchException)) {
                    return null;
                }

                $intended = $request->fullUrl();
                if ($request->isMethod('POST') || $request->isMethod('PUT') || $request->isMethod('DELETE') || $request->isMethod('PATCH')) {
                    $intended = url()->previous() ?: route('home');
                }
                redirect()->setIntendedUrl($intended);

                return redirect()->route('login')
                    ->withErrors([
                        'login' => 'Sesi Anda berakhir, silakan masuk lagi.',
                    ])
                    ->with('error', 'Sesi Anda berakhir, silakan masuk lagi.');
            }

            // Percobaan login berlebih (429 pada route login)
            if ($e->getStatusCode() === 429 && ($request->is('login') || $request->routeIs('login'))) {
                $seconds = $e->getHeaders()['Retry-After'] ?? 60;

                return redirect()->route('login')
                    ->withInput($request->except('password'))
                    ->withErrors([
                        'login' => "Terlalu banyak percobaan. Coba lagi dalam {$seconds} detik.",
                    ]);
            }

            return null;
        });
    })->create();
