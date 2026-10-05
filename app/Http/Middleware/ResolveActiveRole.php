<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menentukan role aktif user dari session, sebelum pengecekan hak akses (`can:`).
 *
 * - Role di session tidak lagi dimiliki user (dicabut admin) -> dibuang.
 * - Belum ada role aktif dan user hanya punya 1 role -> dipakai otomatis.
 * - Belum ada role aktif dan user punya >1 role -> diarahkan ke halaman pilih role.
 * - User tanpa role -> tetap bisa masuk, tanpa hak akses menu.
 */
class ResolveActiveRole
{
    public const SESSION_KEY = 'active_role_id';

    /**
     * Route yang tetap bisa dibuka sebelum role dipilih.
     *
     * @var array<int, string>
     */
    private const ALLOWED_WITHOUT_ROLE = ['active-role.create', 'active-role.store', 'logout'];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $roles = $user->roles()->orderBy('name')->get();
        $role = $roles->firstWhere('id', $request->session()->get(self::SESSION_KEY));

        if ($role === null && $roles->count() === 1) {
            $role = $roles->first();
        }

        if ($role === null) {
            $request->session()->forget(self::SESSION_KEY);
        } else {
            $request->session()->put(self::SESSION_KEY, $role->id);
        }

        $user->setActiveRole($role);
        $user->setRelation('roles', $roles);

        if ($role === null && $roles->count() > 1 && ! $request->routeIs(...self::ALLOWED_WITHOUT_ROLE)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pilih role terlebih dahulu.',
                ], 409);
            }

            return redirect()->guest(route('active-role.create'));
        }

        return $next($request);
    }
}
