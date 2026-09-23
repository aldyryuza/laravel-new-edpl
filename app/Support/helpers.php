<?php

use App\Support\Theme;

if (! function_exists('theme_asset')) {
    /**
     * URL asset template Sneat (public/assets/...), dengan cache busting.
     *
     * theme_asset('img/avatars/1.png')
     */
    function theme_asset(string $path): string
    {
        return Theme::asset($path);
    }
}

if (! function_exists('asset_v')) {
    /**
     * URL asset apa pun di dalam public/, dengan cache busting.
     *
     * asset_v('js/pages/users.js')
     */
    function asset_v(string $path): string
    {
        return Theme::publicAsset($path);
    }
}
