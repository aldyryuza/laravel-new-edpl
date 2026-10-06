/**
 * Pemilih titik lokasi berbasis Leaflet untuk <x-location-picker>.
 *
 *   const picker = LocationPicker.create({
 *       prefix: 'outlet',                 // elemen #outlet-map, #outlet-my-location, #outlet-clear-location
 *       latitude: '#outlet-latitude',
 *       longitude: '#outlet-longitude',
 *       radius: '#distributor-radius',    // opsional: input radius (meter), digambar sebagai lingkaran
 *       modal: '#outlet-modal'            // opsional: peta di dalam modal Bootstrap
 *   });
 *   picker.syncFromInputs();               // panggil setelah form diisi ulang
 *
 * Klik/geser marker mengisi input; mengetik angka memindahkan marker. Tile server dan pusat default
 * diambil dari data attribute peta (config services.map).
 */
'use strict';

window.LocationPicker = (function () {
    const POINT_ZOOM = 16;

    function create(options) {
        const $map = $(`#${options.prefix}-map`);
        const $lat = $(options.latitude);
        const $lng = $(options.longitude);
        const $radius = options.radius ? $(options.radius) : $();
        const defaultView = { center: $map.data('center'), zoom: $map.data('zoom') };

        const map = L.map($map[0]).setView(defaultView.center, defaultView.zoom);
        L.tileLayer($map.data('tile-url'), { maxZoom: 19, attribution: $map.data('attribution') }).addTo(map);

        // Ikon eksplisit: bundle Sneat tidak menyertakan marker-shadow, jadi path default Leaflet tidak dipakai.
        const icon = L.icon({ iconUrl: $map.data('marker-icon'), iconSize: [25, 41], iconAnchor: [12, 41] });
        let marker = null;
        let circle = null;

        const isValid = (lat, lng) => Number.isFinite(lat) && Number.isFinite(lng)
            && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180;

        function drawRadius() {
            const radius = parseInt($radius.val(), 10);

            if (!marker || !Number.isFinite(radius) || radius <= 0) {
                if (circle) {
                    map.removeLayer(circle);
                    circle = null;
                }
                return;
            }

            if (circle) {
                circle.setLatLng(marker.getLatLng()).setRadius(radius);
            } else {
                circle = L.circle(marker.getLatLng(), { radius, weight: 1 }).addTo(map);
            }
        }

        function fillInputs(latLng) {
            $lat.val(latLng.lat.toFixed(7));
            $lng.val(latLng.lng.toFixed(7));
            $lat.add($lng).removeClass('is-invalid').siblings('.invalid-feedback').remove();
        }

        function placeMarker(latLng) {
            if (marker) {
                marker.setLatLng(latLng);
            } else {
                marker = L.marker(latLng, { icon, draggable: true }).addTo(map);
                marker.on('drag', drawRadius);
                marker.on('dragend', () => fillInputs(marker.getLatLng()));
            }

            drawRadius();
        }

        function removeMarker() {
            if (marker) {
                map.removeLayer(marker);
                marker = null;
            }

            drawRadius();
        }

        /** Posisikan marker sesuai input (tanpa mengubah input). */
        function syncFromInputs() {
            const lat = parseFloat($lat.val());
            const lng = parseFloat($lng.val());

            if (isValid(lat, lng)) {
                placeMarker(L.latLng(lat, lng));
            } else {
                removeMarker();
            }
        }

        function focusView() {
            map.invalidateSize();

            if (marker) {
                map.setView(marker.getLatLng(), POINT_ZOOM);
            } else {
                map.setView(defaultView.center, defaultView.zoom);
            }
        }

        map.on('click', event => {
            placeMarker(event.latlng);
            fillInputs(event.latlng);
        });

        $lat.add($lng).on('input', () => {
            syncFromInputs();

            if (marker) {
                map.panTo(marker.getLatLng());
            }
        });

        $radius.on('input', drawRadius);

        $(`#${options.prefix}-clear-location`).on('click', () => {
            $lat.val('');
            $lng.val('');
            removeMarker();
        });

        // Geolocation browser hanya tersedia di HTTPS atau localhost.
        $(`#${options.prefix}-my-location`).on('click', function () {
            if (!navigator.geolocation) {
                App.notify('error', 'Browser tidak mendukung pengambilan lokasi.');
                return;
            }

            const $button = $(this).prop('disabled', true);

            navigator.geolocation.getCurrentPosition(
                position => {
                    const latLng = L.latLng(position.coords.latitude, position.coords.longitude);
                    placeMarker(latLng);
                    fillInputs(latLng);
                    map.setView(latLng, POINT_ZOOM);
                    $button.prop('disabled', false);
                },
                () => {
                    App.notify('error', 'Lokasi tidak bisa diambil. Pastikan izin lokasi aktif dan aplikasi diakses lewat HTTPS.');
                    $button.prop('disabled', false);
                },
                { enableHighAccuracy: true, timeout: 10000 }
            );
        });

        // Peta di modal yang awalnya tersembunyi: ukuran dihitung ulang setelah modal tampil.
        if (options.modal) {
            $(options.modal)[0].addEventListener('shown.bs.modal', focusView);
        }

        return { syncFromInputs, focusView };
    }

    return { create };
})();
