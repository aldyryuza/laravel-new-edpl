{{--
    Layout tanpa menu/navbar: login, error page, halaman cetak.

        @extends('layouts.blank', ['vendors' => ['form-validation']])
--}}
@php($layout = config('sneat.layout'))
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ $layout['html_class'] }}" dir="ltr"
    data-skin="{{ $layout['skin'] }}" data-assets-path="{{ rtrim(theme_asset(''), '/') }}/"
    data-template="{{ $layout['template'] }}" data-bs-theme="{{ $layout['theme'] }}">

<head>
    @include('layouts.partials.head')
</head>

<body>
    <div class="container-xxl">
        <div class="authentication-wrapper authentication-basic container-p-y">
            <div class="authentication-inner">
                @yield('content')
            </div>
        </div>
    </div>

    @stack('modals')

    @include('layouts.partials.scripts')
</body>

</html>
