<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RedirectToRouteController extends Controller
{
    /**
     * Redirect ke nama route yang ditentukan pada defaults route ('to').
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $route = $request->route();
        $targetRoute = $route?->defaults['to']
            ?? $route?->parameter('to')
            ?? $route?->getAction('to');

        if (! $targetRoute) {
            throw new NotFoundHttpException('Target route not specified.');
        }

        return redirect()->route($targetRoute);
    }
}
