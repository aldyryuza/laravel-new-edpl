<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveActiveRole;
use App\Http\Requests\LoginRequest;
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

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'nik' => 'NIK atau password salah.',
            ]);
        }

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
