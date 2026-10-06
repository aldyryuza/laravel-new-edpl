/**
 * Master distributor (induk -> cabang) beserta region.
 *
 * Tabel: Yajra server-side (GET api/distributors/datatable) dengan filter `[data-filter]`.
 * Form: modal; induk & region memakai select2. Import Excel lewat js/excel-import.js.
 */
'use strict';

$(function () {
    const $page = $('#distributors-page');
    const can = { update: $page.data('can-update') === 1, delete: $page.data('can-delete') === 1 };

    const $form = $('#distributor-form');
    const $submit = $form.find('[type="submit"]');
    const $filters = $page.find('[data-filter]');
    const $parent = $('#distributor-parent');
    const $regions = $('#distributor-regions');
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('distributor-modal'));
    let editingId = null;

    const muted = text => `<span class="text-body-secondary">${text}</span>`;
    const filterParams = () => Object.fromEntries($filters.toArray().map(el => [$(el).data('filter'), $(el).val()]));

    const locationPicker = LocationPicker.create({
        prefix: 'distributor',
        latitude: '#distributor-latitude',
        longitude: '#distributor-longitude',
        radius: '#distributor-radius',
        modal: '#distributor-modal'
    });

    const $city = $('#distributor-city');

    [$city, $regions].forEach($select => {
        $select.wrap('<div class="position-relative"></div>').select2({
            dropdownParent: $select.parent(),
            placeholder: $select.find('option[value=""]').text() || 'Pilih region',
            allowClear: true,
            width: '100%'
        });
    });

    // Induk dicari ke server saat mengetik, jadi distributor yang baru dibuat langsung bisa dipilih.
    $parent.wrap('<div class="position-relative"></div>').select2({
        dropdownParent: $parent.parent(),
        placeholder: 'Tanpa induk',
        allowClear: true,
        width: '100%',
        ajax: {
            url: App.url('api/distributors/options'),
            delay: 300,
            data: params => ({ q: params.term || '', exclude_id: editingId || '' }),
            processResults: res => ({ results: res.data }),
            error: (xhr, status) => (status === 'abort' ? null : App.handleError(xhr))
        }
    });

    const table = $('#distributors-table').DataTable({
        serverSide: true,
        processing: true,
        searchDelay: 400,
        order: [[1, 'asc']],
        ajax: App.dataTableSource('api/distributors/datatable', filterParams),
        columns: [
            { data: 'code', name: 'code', render: App.escape },
            {
                data: 'name',
                name: 'name',
                render: (name, type, row) => App.escape(name)
                    + (row.group_code ? `<br><small class="text-body-secondary">Grup: ${App.escape(row.group_code)}</small>` : '')
            },
            {
                data: 'city',
                orderable: false,
                searchable: false,
                render: (city, type, row) => (city ? App.escape(city.name) : muted('-')) + (row.latitude !== null && row.longitude !== null
                    ? ` <i class="icon-base bx bx-map-pin text-primary" title="${App.escape(`${row.latitude}, ${row.longitude}`)}"></i>`
                    : '')
            },
            {
                data: 'parent',
                orderable: false,
                searchable: false,
                render: (parent, type, row) => parent
                    ? `${App.escape(parent.name)}${row.is_branch ? ' <span class="badge bg-label-info">cabang</span>' : ''}`
                    : (row.is_branch ? '<span class="badge bg-label-info">cabang</span>' : muted('pusat'))
            },
            {
                data: 'regions',
                orderable: false,
                searchable: false,
                render: regions => regions.length
                    ? regions.map(region => `<span class="badge bg-label-primary me-1">${App.escape(region.code)}</span>`).join('')
                    : muted('-')
            },
            { data: 'branches_count', orderable: false, searchable: false },
            { data: 'is_active', name: 'is_active', searchable: false, render: renderStatus },
            { data: null, orderable: false, searchable: false, className: 'text-end text-nowrap', render: renderActions }
        ],
        language: { emptyTable: 'Belum ada distributor', zeroRecords: 'Distributor tidak ditemukan', search: 'Cari:' }
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

    const findRow = id => table.rows().data().toArray().find(distributor => distributor.id === id);

    function openForm(distributor = null) {
        editingId = distributor ? distributor.id : null;

        $form[0].reset();
        App.clearErrors($form);
        $('#distributor-modal-title').text(distributor ? 'Edit Distributor' : 'Tambah Distributor');

        if (distributor) {
            [
                'code', 'name', 'group_code', 'netsuite_id', 'address', 'phone', 'fax', 'email', 'npwp',
                'latitude', 'longitude', 'meeting_radius_meters'
            ].forEach(field => $form.find(`[name="${field}"]`).val(distributor[field] ?? ''));
            $('#distributor-branch').prop('checked', distributor.is_branch);
            $('#distributor-active').prop('checked', distributor.is_active);
        }

        // Pilihan induk dimuat via AJAX: isi ulang opsi terpilih dari data baris (dirinya sendiri dikecualikan
        // lewat exclude_id; kasus melingkar lain dicek server).
        $parent.find('option[value!=""]').remove();

        if (distributor && distributor.parent) {
            $parent.append(new Option(`${distributor.parent.name} (${distributor.parent.code})`, distributor.parent.id, true, true));
        }

        $parent.val(distributor && distributor.parent ? String(distributor.parent.id) : '').trigger('change');
        $city.val(distributor && distributor.city_id ? String(distributor.city_id) : '').trigger('change');
        $regions.val(distributor ? distributor.regions.map(region => String(region.id)) : []).trigger('change');
        locationPicker.syncFromInputs();
        modal.show();
    }

    $('#btn-add-distributor').on('click', () => openForm());

    $('#distributors-table').on('click', '.btn-edit', function () {
        openForm(findRow($(this).data('id')));
    });

    $('#distributors-table').on('click', '.btn-delete', function () {
        const distributor = findRow($(this).data('id'));

        App.confirm(`Hapus distributor ${distributor.name} (${distributor.code})?`, 'Ya, hapus').then(confirmed => {
            if (!confirmed) {
                return;
            }

            $.ajax({ url: App.url(`api/distributors/${distributor.id}`), type: 'DELETE' })
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
            url: App.url(editingId ? `api/distributors/${editingId}` : 'api/distributors'),
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
