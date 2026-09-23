<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/**
 * Helper layout/asset untuk template Sneat.
 *
 * Fokusnya dua hal:
 * 1. Membangun URL asset (dengan cache busting) tanpa stat file berulang.
 * 2. Menerjemahkan daftar nama vendor menjadi daftar file CSS/JS, agar setiap
 *    halaman hanya memuat asset yang dibutuhkan.
 */
class Theme
{
    /** @var array<string, string> cache URL asset per request */
    private static array $urlCache = [];

    /**
     * URL file di dalam public/assets (folder template Sneat).
     */
    public static function asset(string $path): string
    {
        return self::publicAsset(trim(config('sneat.assets_path', 'assets'), '/').'/'.ltrim($path, '/'));
    }

    /**
     * URL file mana pun di dalam public/, dengan cache busting.
     */
    public static function publicAsset(string $path): string
    {
        $path = ltrim($path, '/');

        if (isset(self::$urlCache[$path])) {
            return self::$urlCache[$path];
        }

        $url = asset(implode('/', array_map('rawurlencode', explode('/', $path))));

        if (config('sneat.cache_bust', true)) {
            $file = public_path($path);

            if (is_file($file)) {
                $url .= '?v='.filemtime($file);
            }
        }

        return self::$urlCache[$path] = $url;
    }

    /**
     * Daftar URL CSS untuk vendor yang diminta halaman.
     *
     * @param  array<int, string>  $vendors
     * @return array<int, string>
     */
    public static function vendorStyles(array $vendors): array
    {
        return self::resolve($vendors, 'css');
    }

    /**
     * Daftar URL JS untuk vendor yang diminta halaman.
     *
     * @param  array<int, string>  $vendors
     * @return array<int, string>
     */
    public static function vendorScripts(array $vendors): array
    {
        return self::resolve($vendors, 'js');
    }

    /**
     * @param  array<int, string>  $vendors
     * @return array<int, string>
     */
    public static function resolve(array $vendors, string $type): array
    {
        $registry = config('sneat.vendors', []);
        $files = [];

        foreach (self::expand($vendors, $registry) as $name) {
            foreach ($registry[$name][$type] ?? [] as $path) {
                $files[$path] = self::asset($path);
            }
        }

        return array_values($files);
    }

    /**
     * Menyelesaikan dependency antar vendor dan menghapus duplikat.
     *
     * @param  array<int, string>  $vendors
     * @param  array<string, array>  $registry
     * @return array<int, string>
     */
    private static function expand(array $vendors, array $registry, array $seen = []): array
    {
        $resolved = [];

        foreach ($vendors as $name) {
            if (! isset($registry[$name]) || isset($seen[$name])) {
                continue;
            }

            $seen[$name] = true;

            foreach (self::expand($registry[$name]['requires'] ?? [], $registry, $seen) as $dependency) {
                $resolved[$dependency] = true;
                $seen[$dependency] = true;
            }

            $resolved[$name] = true;
        }

        return array_keys($resolved);
    }

    /**
     * URL tujuan sebuah item menu.
     *
     * @param  array<string, mixed>  $item
     */
    public static function menuUrl(array $item): string
    {
        if (! empty($item['route']) && Route::has($item['route'])) {
            return route($item['route'], $item['params'] ?? []);
        }

        return $item['url'] ?? 'javascript:void(0);';
    }

    /**
     * Item aktif jika route/URL-nya cocok, atau salah satu turunannya aktif.
     *
     * @param  array<string, mixed>  $item
     */
    public static function menuIsActive(array $item): bool
    {
        foreach ($item['children'] ?? [] as $child) {
            if (self::menuIsActive($child)) {
                return true;
            }
        }

        $patterns = (array) ($item['active'] ?? $item['route'] ?? []);

        if ($patterns !== [] && request()->routeIs(...$patterns)) {
            return true;
        }

        $url = $item['url'] ?? null;

        if (! $url || str_starts_with($url, 'javascript:')) {
            return false;
        }

        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');

        return request()->is($path === '' ? '/' : $path);
    }

    /**
     * Item disembunyikan jika user tidak punya permission.
     * Authorization tetap wajib dilakukan di server.
     *
     * @param  array<string, mixed>  $item
     */
    public static function menuIsVisible(array $item): bool
    {
        if (empty($item['can'])) {
            return true;
        }

        return (bool) Auth::user()?->can($item['can']);
    }
}
