/**
 * Master outlet.
 *
 * Tabel: Yajra server-side (GET api/outlets/datatable) dengan filter `[data-filter]`.
 * Form: modal; area & group memakai select2. Import Excel lewat js/excel-import.js.
 */
'use strict';

$(function () {
    const $page = $('#outlets-page');
    const can = { update: $page.data('can-update') === 1, delete: $page.data('can-delete') === 1 };

    const $form = $('#outlet-form');
    const $submit = $form.find('[type="submit"]');
    const $filters = $page.find('[data-filter]');
    const $selects = $('#outlet-area, #outlet-group');
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('outlet-modal'));
    let editingId = null;

    const muted = text => `<span class="text-body-secondary">${text}</span>`;

    $selects.each(function () {
        const $select = $(this);

        $select.wrap('<div class="position-relative"></div>').select2({
            dropdownParent: $select.parent(),
            placeholder: $select.find('option[value=""]').text(),
            allowClear: true,
            width: '100%'
        });
    });

    const locationPicker = createLocationPicker();

    const filterParams = () => Object.fromEntries($filters.toArray().map(el => [$(el).data('filter'), $(el).val()]));

    const table = $('#outlets-table').DataTable({
        serverSide: true,
        processing: true,
        searchDelay: 400,
        order: [[1, 'asc']],
        ajax: App.dataTableSource('api/outlets/datatable', filterParams),
        columns: [
            { data: 'code', name: 'code', render: App.escape },
            {
                data: 'name',
                name: 'name',
                render: (name, type, row) => App.escape(name) + (row.latitude !== null && row.longitude !== null
                    ? ` <i class="icon-base bx bx-map-pin text-primary" title="${App.escape(`${row.latitude}, ${row.longitude}`)}"></i>`
                    : '')
            },
            {
                data: 'area',
                orderable: false,
                searchable: false,
                render: area => area
                    ? `${App.escape(area.name)}${area.region ? `<br><small class="text-body-secondary">${App.escape(area.region.name)}</small>` : ''}`
                    : muted('-')
            },
            { data: 'category', orderable: false, searchable: false, render: item => item ? App.escape(item.name) : muted('-') },
            { data: 'ownership_type', orderable: false, searchable: false, render: item => item ? App.escape(item.name) : muted('-') },
            {
                data: 'active_group_membership',
                orderable: false,
                searchable: false,
                render: membership => membership && membership.outlet_group ? App.escape(membership.outlet_group.name) : muted('-')
            },
            { data: 'bed_count', name: 'bed_count', searchable: false, render: value => value === null ? muted('-') : value },
            { data: 'is_active', name: 'is_active', searchable: false, render: renderStatus },
            { data: null, orderable: false, searchable: false, className: 'text-end text-nowrap', render: renderActions }
        ],
        language: { emptyTable: 'Belum ada outlet', zeroRecords: 'Outlet tidak ditemukan', search: 'Cari:' }
    });

    $filters.on('change', () => table.ajax.reload());

    // Muat ulang tabel setelah import Excel berhasil (lihat js/excel-import.js).
    $(document).on('excel-import:done', () => table.ajax.reload());

    function renderStatus(active) {
        return active
            ? '<span class="badge bg-label-success">Aktif</span>'
            : '<span class="badge bg-label-secondary">Nonaktif</span>';
    }

    function renderActions(row) {
        let html = '';

        if (can.update) {
            html += `<button type="button" class="btn btn-sm btn-icon btn-text-secondary btn-edit" data-id="${row.id}" title="Edit">
                        <i class="icon-base bx bx-edit"></i></button>`;
        }

        if (can.delete) {
            html += `<button type="button" class="btn btn-sm btn-icon btn-text-danger btn-delete" data-id="${row.id}" title="Hapus">
                        <i class="icon-base bx bx-trash"></i></button>`;
        }

        return html;
    }

    const findRow = id => table.rows().data().toArray().find(outlet => outlet.id === id);

    function openForm(outlet = null) {
        editingId = outlet ? outlet.id : null;

        $form[0].reset();
        App.clearErrors($form);
        $('#outlet-modal-title').text(outlet ? 'Edit Outlet' : 'Tambah Outlet');

        if (outlet) {
            ['code', 'name', 'address', 'outlet_category_id', 'ownership_type_id', 'bed_count', 'crm_id', 'latitude', 'longitude']
                .forEach(field => $form.find(`[name="${field}"]`).val(outlet[field] ?? ''));
            $('#outlet-active').prop('checked', outlet.is_active);
        }

        const membership = outlet ? outlet.active_group_membership : null;

        $('#outlet-area').val(outlet && outlet.area_id ? String(outlet.area_id) : '').trigger('change');
        $('#outlet-group').val(membership ? String(membership.outlet_group_id) : '').trigger('change');
        locationPicker.syncFromInputs();
        modal.show();
    }

    /**
     * Peta Leaflet di form: klik/geser marker mengisi latitude & longitude, mengetik angka memindahkan marker.
     * Tile server dan pusat default diatur dari config('services.map') lewat data attribute #outlet-map.
     */
    function createLocationPicker() {
        const $map = $('#outlet-map');
        const $lat = $('#outlet-latitude');
        const $lng = $('#outlet-longitude');
        const defaultView = { center: $map.data('center'), zoom: $map.data('zoom') };
        const pointZoom = 16;

        const map = L.map($map[0]).setView(defaultView.center, defaultView.zoom);
        L.tileLayer($map.data('tile-url'), { maxZoom: 19, attribution: $map.data('attribution') }).addTo(map);

        // Ikon eksplisit: bundle Sneat tidak menyertakan marker-shadow, jadi path default Leaflet tidak dipakai.
        const icon = L.icon({ iconUrl: $map.data('marker-icon'), iconSize: [25, 41], iconAnchor: [12, 41] });
        let marker = null;

        const isValid = (lat, lng) => Number.isFinite(lat) && Number.isFinite(lng)
            && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180;

        function fillInputs(latLng) {
            $lat.val(latLng.lat.toFixed(7));
            $lng.val(latLng.lng.toFixed(7));
            App.clearErrors($lat.closest('.row'));
        }

        function placeMarker(latLng) {
            if (marker) {
                marker.setLatLng(latLng);
                return;
            }

            marker = L.marker(latLng, { icon, draggable: true }).addTo(map);
            marker.on('dragend', () => fillInputs(marker.getLatLng()));
        }

        function removeMarker() {
            if (marker) {
                map.removeLayer(marker);
                marker = null;
            }
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
                map.setView(marker.getLatLng(), pointZoom);
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

        $('#btn-clear-location').on('click', () => {
            $lat.val('');
            $lng.val('');
            removeMarker();
        });

        // Geolocation browser hanya tersedia di HTTPS atau localhost.
        $('#btn-my-location').on('click', function () {
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
                    map.setView(latLng, pointZoom);
                    $button.prop('disabled', false);
                },
                () => {
                    App.notify('error', 'Lokasi tidak bisa diambil. Pastikan izin lokasi aktif dan aplikasi diakses lewat HTTPS.');
                    $button.prop('disabled', false);
                },
                { enableHighAccuracy: true, timeout: 10000 }
            );
        });

        // Modal awalnya tersembunyi: ukuran peta dihitung ulang setelah modal tampil.
        document.getElementById('outlet-modal').addEventListener('shown.bs.modal', focusView);

        return { syncFromInputs };
    }

    $('#btn-add-outlet').on('click', () => openForm());

    $('#outlets-table').on('click', '.btn-edit', function () {
        openForm(findRow($(this).data('id')));
    });

    $('#outlets-table').on('click', '.btn-delete', function () {
        const outlet = findRow($(this).data('id'));

        App.confirm(`Hapus outlet ${outlet.name} (${outlet.code})?`, 'Ya, hapus').then(confirmed => {
            if (!confirmed) {
                return;
            }

            $.ajax({ url: App.url(`api/outlets/${outlet.id}`), type: 'DELETE' })
                .done(res => {
                    App.notify('success', res.message);
                    table.ajax.reload(null, false);
                })
                .fail(xhr => App.handleError(xhr));
        });
    });

    $form.on('submit', function (event) {
        event.preventDefault();
        $submit.prop('disabled', true);

        $.ajax({
            url: App.url(editingId ? `api/outlets/${editingId}` : 'api/outlets'),
            type: editingId ? 'PUT' : 'POST',
            data: $form.serialize()
        })
            .done(res => {
                modal.hide();
                App.notify('success', res.message);
                table.ajax.reload(null, false);
            })
            .fail(xhr => App.handleError(xhr, $form))
            .always(() => $submit.prop('disabled', false));
    });
});
