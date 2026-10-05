<?php

namespace App\Support;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Konvensi audit log project (spatie/laravel-activitylog).
 *
 * Kategori (log_name):
 * - auth     : login, login gagal, logout, ganti role, ganti password
 * - data     : create/update/delete data master
 * - security : rate limit terlampaui
 */
class AuditLog
{
    public const AUTH = 'auth';

    public const DATA = 'data';

    public const SECURITY = 'security';

    /** @var array<string, string> */
    public const CATEGORIES = [
        self::AUTH => 'Autentikasi',
        self::DATA => 'Perubahan data',
        self::SECURITY => 'Keamanan',
    ];

    /** @var array<string, string> */
    private const EVENT_LABELS = [
        'created' => 'dibuat',
        'updated' => 'diubah',
        'deleted' => 'dihapus',
    ];

    /**
     * Opsi log untuk model data master: hanya atribut yang disebut, hanya yang berubah.
     *
     * @param  array<int, string>  $attributes  jangan masukkan atribut rahasia (password, token)
     * @param  Closure(): string|string  $subject  label subjek, contoh: "User budi (123)"
     */
    public static function dataOptions(array $attributes, Closure|string $subject): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName(self::DATA)
            ->logOnly($attributes)
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(function (string $event) use ($subject): string {
                $label = $subject instanceof Closure ? $subject() : $subject;

                return trim($label.' '.(self::EVENT_LABELS[$event] ?? $event));
            });
    }

    /**
     * Catat request yang kena rate limit, cukup sekali per IP + route + user
     * selama window limit masih berlaku, supaya serangan tidak membanjiri log.
     */
    public static function rateLimited(Request $request, int $retryAfterSeconds): void
    {
        $route = $request->route()?->getName() ?? $request->path();
        $key = 'audit-log:rate-limited:'.sha1($request->ip().'|'.$route.'|'.$request->user()?->getAuthIdentifier());

        if (! Cache::add($key, true, max($retryAfterSeconds, 1))) {
            return;
        }

        $logger = activity(self::SECURITY)
            ->event('rate_limited')
            ->withProperties([
                ...self::requestContext(),
                'route' => $route,
                'method' => $request->method(),
                'nik' => $request->routeIs('login.store') ? $request->input('nik') : null,
                'retry_after' => $retryAfterSeconds,
            ]);

        $request->user() ? $logger->causedBy($request->user()) : $logger->causedByAnonymous();

        $logger->log('Rate limit terlampaui');
    }

    /**
     * Konteks request yang ikut disimpan di properties.
     *
     * @return array{ip: string|null, user_agent: string|null}
     */
    public static function requestContext(): array
    {
        return [
            'ip' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 255) ?: null,
        ];
    }
}
