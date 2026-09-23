<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
<meta name="csrf-token" content="{{ csrf_token() }}" />
<meta name="base-url" content="{{ url('/') }}" />

<title>@yield('title', config('app.name')) &middot; {{ config('app.name') }}</title>

<link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}" />

{{-- Font --}}
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet" />

{{-- Template customizer butuh Pickr (color picker) --}}
@if (config('sneat.customizer'))
    <link rel="stylesheet" href="{{ theme_asset('vendor/libs/pickr/pickr-themes.css') }}" />
@endif

{{-- Core CSS --}}
@foreach (config('sneat.core.css', []) as $file)
    <link rel="stylesheet" href="{{ theme_asset($file) }}" />
@endforeach

{{-- Vendor CSS: hanya yang dideklarasikan halaman --}}
@foreach (\App\Support\Theme::vendorStyles($vendors ?? []) as $url)
    <link rel="stylesheet" href="{{ $url }}" />
@endforeach

{{-- CSS aplikasi: dimuat terakhir supaya bisa menimpa core.css --}}
<link rel="stylesheet" href="{{ asset_v('css/app.css') }}" />

{{-- CSS khusus halaman --}}
@stack('styles')

{{-- Helpers & config template wajib dimuat setelah core CSS --}}
<script src="{{ theme_asset('vendor/js/helpers.js') }}"></script>
@if (config('sneat.customizer'))
    <script src="{{ theme_asset('vendor/js/template-customizer.js') }}"></script>
@endif
<script src="{{ theme_asset('js/config.js') }}"></script>

{{-- Terapkan theme tersimpan sebelum render supaya tidak ada kedip (FOUC). --}}
<script>
    (function () {
        try {
            var stored = localStorage.getItem('templateCustomizer-{{ config('sneat.layout.template') }}--Theme');

            if (!stored) return;

            document.documentElement.setAttribute(
                'data-bs-theme',
                stored === 'system'
                    ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
                    : stored
            );
        } catch (e) {}
    })();
</script>

@stack('head')
