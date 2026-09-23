{{--
    Layout utama (vertical menu).

    Cara pakai di halaman:

        @extends('layouts.app', ['vendors' => ['datatables', 'select2']])

        @section('title', 'Daftar User')

        @section('content')
            ...
        @endsection

        @push('scripts')
            <script src="{{ asset_v('js/pages/users.js') }}"></script>
        @endpush

    Variabel opsional:
        $vendors    array nama vendor dari config/sneat.php (default: kosong)
        $container  override container content (default: config sneat.layout.container)
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
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">

            @include('layouts.partials.sidebar')

            <div class="layout-page">

                @include('layouts.partials.navbar')

                <div class="content-wrapper">
                    <div class="{{ $container ?? $layout['container'] }} flex-grow-1 container-p-y">
                        @yield('content')
                    </div>

                    @include('layouts.partials.footer')

                    <div class="content-backdrop fade"></div>
                </div>
            </div>
        </div>

        <div class="layout-overlay layout-menu-toggle"></div>
        <div class="drag-target"></div>
    </div>

    @stack('modals')

    @include('layouts.partials.scripts')
</body>

</html>
