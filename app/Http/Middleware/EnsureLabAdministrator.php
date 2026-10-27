<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * The application has exactly one operator: the laboratory administrator.
 * Any other authenticated account (for example legacy users that still exist
 * in the database) is signed out immediately.
 */
class EnsureLabAdministrator
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_lab_admin) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'This account is not permitted to access AccuCare Pathology Lab.',
            ]);
        }

        return $next($request);
    }
}
