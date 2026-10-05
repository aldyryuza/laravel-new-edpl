<?php

namespace App\Providers;

use App\Models\RoleMenuAccess;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
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
