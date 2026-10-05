{{-- Core JS --}}
@foreach (config('sneat.core.js', []) as $file)
    <script src="{{ theme_asset($file) }}"></script>
@endforeach

@if (config('sneat.customizer'))
    <script src="{{ theme_asset('vendor/libs/pickr/pickr.js') }}"></script>
@endif

@if (config('sneat.search'))
    <script src="{{ theme_asset('vendor/libs/@algolia/autocomplete-js.js') }}"></script>
@endif

{{-- Vendor JS: hanya yang dideklarasikan halaman --}}
@foreach (\App\Support\Theme::vendorScripts($vendors ?? []) as $url)
    <script src="{{ $url }}"></script>
@endforeach

{{-- Main template JS --}}
<script src="{{ theme_asset('js/main.js') }}"></script>

{{-- Bootstrap aplikasi: CSRF, helper AJAX, notifikasi --}}
<script src="{{ asset_v('js/app.js') }}"></script>

{{--
    Real-time (Reverb + Echo): setiap user login bergabung ke presence channel `online`.
    Dilewati di halaman pilih role karena hak akses user belum ditentukan.
--}}
@auth
    @if (config('broadcasting.default') === 'reverb' && ! request()->routeIs('active-role.*'))
        @php
            $reverb = config('broadcasting.connections.reverb');
            $realtimeConfig = [
                'key' => $reverb['key'],
                'host' => $reverb['browser']['host'],
                'port' => $reverb['browser']['port'],
                'scheme' => $reverb['browser']['scheme'],
                'authEndpoint' => url('broadcasting/auth'),
                'userId' => auth()->id(),
            ];
        @endphp
        <script type="application/json" id="realtime-config">@json($realtimeConfig)</script>
        <script src="{{ theme_asset('vendor/libs/echo/pusher.min.js') }}"></script>
        <script src="{{ theme_asset('vendor/libs/echo/echo.iife.js') }}"></script>
        <script src="{{ asset_v('js/realtime.js') }}"></script>
    @endif
@endauth

{{-- JS khusus halaman --}}
@stack('scripts')
