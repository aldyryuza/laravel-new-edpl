<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveActiveRole;
use App\Support\AuditLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Pilih role setelah login (user dengan >1 role) dan ganti role tanpa login ulang.
 */
class ActiveRoleController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user->roles->count() <= 1) {
            return redirect()->route('dashboard');
        }

        return view('pages.auth.select-role', [
            'roles' => $user->roles,
            'activeRoleId' => $user->activeRole()?->id,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'role_id' => ['required', 'integer', Rule::in($user->roles->modelKeys())],
        ], [
            'role_id.in' => 'Role tidak tersedia untuk akun Anda.',
        ], [
            'role_id' => 'role',
        ]);

        $previous = $user->activeRole();
        $request->session()->put(ResolveActiveRole::SESSION_KEY, (int) $validated['role_id']);

        $role = $user->roles->firstWhere('id', (int) $validated['role_id']);

        if (! $role->is($previous)) {
            activity(AuditLog::AUTH)
                ->event('role_switched')
                ->performedOn($user)
                ->withProperties([...AuditLog::requestContext(), 'from' => $previous?->name, 'to' => $role->name])
                ->log($previous ? "Ganti role dari {$previous->name} ke {$role->name}" : "Memilih role {$role->name}");
        }

        // Saat login: kembali ke halaman yang dituju. Saat ganti role: ke dashboard,
        // karena halaman sebelumnya belum tentu boleh diakses role baru.
        return redirect()->intended(route('dashboard'))
            ->with('success', "Sekarang menggunakan role {$role->name}.");
    }
}
