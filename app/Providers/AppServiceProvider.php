<?php

namespace App\Providers;

use App\Models\RoleMenuAccess;
use App\Models\User;
use App\Support\Theme;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Pulse\Facades\Pulse;

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
        $this->configurePulse();
        $this->registerDomainMigrationPaths();
    }

    /**
     * Migration E-DPL dikelompokkan per domain di subfolder database/migrations/{domain}.
     * Urutan eksekusi tetap global berdasarkan timestamp nama file, bukan per folder.
     */
    private function registerDomainMigrationPaths(): void
    {
        $this->loadMigrationsFrom(glob(database_path('migrations/*'), GLOB_ONLYDIR) ?: []);
    }

    /**
     * Dashboard Pulse (/pulse) mengikuti hak akses menu `pulse.read`.
     * Avatar tidak diambil dari Gravatar (layanan eksternal) demi privasi data user.
     */
    private function configurePulse(): void
    {
        Gate::define('viewPulse', fn (User $user) => $user->can('pulse.read'));

        Pulse::user(fn (User $user) => [
            'name' => $user->name,
            'extra' => $user->nik,
            'avatar' => Theme::asset('img/avatars/1.png'),
        ]);
    }

    /**
     * Rate limit dipakai di routes:
     * - login    : 5x/menit per NIK + IP, dan 20x/menit per IP (mencegah tebak banyak NIK)
     * - api      : 60x/menit per user
     * - password : 5x/menit per user (ganti password di profil)
     * - export   : 10x/menit per user (ekspor Excel)
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

        // Ekspor membuat file di server: dibatasi agar tidak dipakai membebani server.
        RateLimiter::for('export', fn (Request $request) => Limit::perMinute(10)
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
