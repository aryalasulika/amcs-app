<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects authenticated-but-deactivated accounts (FR-01).
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->active) {
            Auth::guard('web')->logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Akun Anda dinonaktifkan. Hubungi administrator.',
                ], 403);
            }

            return redirect()->route('login')->withErrors([
                'email' => 'Akun Anda dinonaktifkan. Hubungi administrator.',
            ]);
        }

        return $next($request);
    }
}
