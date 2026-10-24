<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RootRedirectController extends Controller
{
    /**
     * Redirect root ke home jika login, atau ke login jika guest.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        return auth()->check() ? redirect()->route('home') : redirect()->route('login');
    }
}
