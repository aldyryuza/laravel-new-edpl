<?php

namespace App\Providers;

use App\Models\RoleMenuAccess;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerMenuAccessGate();
        $this->registerRateLimiters();
    }

    /**
     * Rate limit dipakai di routes:
     * - login    : 5x/menit per NIK + IP, dan 20x/menit per IP (mencegah tebak banyak NIK)
     * - api      : 60x/menit per user
     * - password : 5x/menit per user (ganti password di profil)
     *
     * Request yang kena limit dicatat ke audit log (lihat bootstrap/app.php).
     */
    private function registerRateLimiters(): void
    {
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by(Str::lower((string) $request->input('nik')).'|'.$request->ip()),
            Limit::perMinute(20)->by('ip:'.$request->ip()),
        ]);

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));

        RateLimiter::for('password', fn (Request $request) => Limit::perMinute(5)
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));
    }

    /**
     * Ability berformat "{kode_menu}.{action}" (contoh: users.create) dicek ke
     * tabel role_menu_accesses, sehingga bisa dipakai lewat middleware `can:`,
     * `@can`, maupun `$user->can()`. Ability lain diteruskan ke Gate/Policy biasa.
     */
    private function registerMenuAccessGate(): void
    {
        Gate::before(function (User $user, string $ability): ?bool {
            $action = Str::afterLast($ability, '.');

            if ($action === $ability || ! in_array($action, RoleMenuAccess::ACTIONS, true)) {
                return null;
            }

            return $user->hasMenuAccess(Str::beforeLast($ability, '.'), $action);
        });
    }
}
