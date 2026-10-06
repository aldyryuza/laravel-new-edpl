<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mengeluarkan user yang dinonaktifkan admin saat sesinya masih berjalan
 * (termasuk login lewat remember-me), sehingga penonaktifan berlaku seketika.
 */
class EnsureUserIsActive
{
    public const MESSAGE = 'Akun Anda nonaktif. Hubungi administrator.';

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->is_active) {
            return $next($request);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => self::MESSAGE], 401);
        }

        return redirect()->route('login')->withErrors(['nik' => self::MESSAGE]);
    }
}
