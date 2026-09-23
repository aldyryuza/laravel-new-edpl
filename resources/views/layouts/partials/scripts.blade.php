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

{{-- JS khusus halaman --}}
@stack('scripts')
