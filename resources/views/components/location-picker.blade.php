{{--
    Peta Leaflet untuk memilih titik lokasi (butuh vendor `leaflet` + public/js/location-picker.js).

        <x-location-picker prefix="outlet" />

    Membuat elemen #{prefix}-map, #{prefix}-my-location, #{prefix}-clear-location.
    Input latitude/longitude (dan radius) tetap milik form pemanggil; hubungkan lewat LocationPicker.create().
--}}
@props(['prefix', 'label' => 'Posisi', 'height' => 300])

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
    <span class="form-label mb-0">{{ $label }}</span>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-sm btn-label-primary" id="{{ $prefix }}-my-location">
            <i class="icon-base bx bx-current-location me-1"></i> Lokasi saya
        </button>
        <button type="button" class="btn btn-sm btn-label-secondary" id="{{ $prefix }}-clear-location">
            <i class="icon-base bx bx-x me-1"></i> Hapus titik
        </button>
    </div>
</div>
<div id="{{ $prefix }}-map" class="rounded border" style="height: {{ (int) $height }}px;"
    data-tile-url="{{ config('services.map.tile_url') }}"
    data-attribution="{{ config('services.map.attribution') }}"
    data-center="{{ json_encode(config('services.map.center')) }}"
    data-zoom="{{ config('services.map.zoom') }}"
    data-marker-icon="{{ theme_asset('vendor/libs/leaflet/images/marker-icon.png') }}"></div>
<div class="form-text">Klik peta atau geser marker untuk mengisi latitude/longitude. Mengetik angka juga memindahkan marker.</div>
