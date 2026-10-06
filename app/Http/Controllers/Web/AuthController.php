<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\ResolveActiveRole;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function create(): View
    {
        return view('pages.auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->safe()->only(['nik', 'password']);
        $isInactive = false;

        // Callback hanya dijalankan setelah password terbukti benar, jadi pesan "nonaktif"
        // tidak membocorkan NIK mana yang terdaftar.
        $authenticated = Auth::attemptWhen(
            $credentials,
            function (User $user) use (&$isInactive): bool {
                $isInactive = ! $user->is_active;

                return ! $isInactive;
            },
            $request->boolean('remember'),
        );

        if (! $authenticated) {
            throw ValidationException::withMessages([
                'nik' => $isInactive ? EnsureUserIsActive::MESSAGE : 'NIK atau password salah.',
            ]);
        }

        // saveQuietly: login sudah dicatat listener auth, tidak perlu log "User diubah".
        $request->user()->forceFill(['last_login_at' => now()])->saveQuietly();

        $request->session()->regenerate();
        $request->session()->forget(ResolveActiveRole::SESSION_KEY);

        // Punya lebih dari 1 role: pilih dulu role yang akan dipakai.
        // Role tunggal dipakai otomatis oleh middleware ResolveActiveRole.
        if ($request->user()->roles()->count() > 1) {
            return redirect()->route('active-role.create');
        }

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
