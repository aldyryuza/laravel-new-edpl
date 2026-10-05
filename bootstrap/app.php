<?php

use App\Http\Middleware\ResolveActiveRole;
use App\Support\AuditLog;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function (): void {
            // API dipanggil lewat AJAX dari halaman Blade, jadi memakai session
            // + CSRF dari grup `web` (bukan token), dan wajib login.
            Route::middleware(['web', 'auth', 'throttle:api'])
                ->prefix('api')
                ->name('api.')
                ->group(base_path('routes/api.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Role aktif harus sudah ditentukan sebelum middleware `can:` berjalan.
        $middleware->appendToGroup('web', ResolveActiveRole::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Catat request yang kena rate limit (sekali per window), lalu render 429 seperti biasa.
        $exceptions->render(function (ThrottleRequestsException $exception, Request $request) {
            AuditLog::rateLimited($request, (int) ($exception->getHeaders()['Retry-After'] ?? 60));

            return null;
        });
    })->create();
