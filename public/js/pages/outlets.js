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

    const locationPicker = LocationPicker.create({
        prefix: 'outlet',
        latitude: '#outlet-latitude',
        longitude: '#outlet-longitude',
        modal: '#outlet-modal'
    });

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
