/**
 * Master region penjualan (zona -> region).
 *
 * Tabel: Yajra server-side (GET api/sales-regions/datatable, filter `zone_id`), form tambah/edit di modal.
 */
'use strict';

$(function () {
    const $page = $('#regions-page');
    const can = { update: $page.data('can-update') === 1, delete: $page.data('can-delete') === 1 };

    const $form = $('#region-form');
    const $submit = $form.find('[type="submit"]');
    const $filterZone = $('#filter-zone');
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('region-modal'));
    let editingId = null;

    const table = $('#regions-table').DataTable({
        serverSide: true,
        processing: true,
        searchDelay: 400,
        order: [[1, 'asc']],
        ajax: App.dataTableSource('api/sales-regions/datatable', () => ({ zone_id: $filterZone.val() })),
        columns: [
            { data: 'code', name: 'code', render: App.escape },
            { data: 'name', name: 'name', render: App.escape },
            { data: 'zone', orderable: false, searchable: false, render: zone => zone ? App.escape(zone.name) : '-' },
            { data: 'areas_count', orderable: false, searchable: false },
            { data: 'is_active', name: 'is_active', searchable: false, render: renderStatus },
            { data: null, orderable: false, searchable: false, className: 'text-end text-nowrap', render: renderActions }
        ],
        language: { emptyTable: 'Belum ada region', zeroRecords: 'Region tidak ditemukan', search: 'Cari:' }
    });

    $filterZone.on('change', () => table.ajax.reload());

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

    const findRow = id => table.rows().data().toArray().find(region => region.id === id);

    function openForm(region = null) {
        editingId = region ? region.id : null;

        $form[0].reset();
        App.clearErrors($form);
        $('#region-modal-title').text(region ? 'Edit Region' : 'Tambah Region');

        if (region) {
            $form.find('[name="code"]').val(region.code);
            $form.find('[name="name"]').val(region.name);
            $form.find('[name="zone_id"]').val(region.zone_id);
            $('#region-active').prop('checked', region.is_active);
        } else if ($filterZone.val()) {
            $form.find('[name="zone_id"]').val($filterZone.val());
        }

        modal.show();
    }

    $('#btn-add-region').on('click', () => openForm());

    $('#regions-table').on('click', '.btn-edit', function () {
        openForm(findRow($(this).data('id')));
    });

    $('#regions-table').on('click', '.btn-delete', function () {
        const region = findRow($(this).data('id'));

        App.confirm(`Hapus region ${region.name} (${region.code})?`, 'Ya, hapus').then(confirmed => {
            if (!confirmed) {
                return;
            }

            $.ajax({ url: App.url(`api/sales-regions/${region.id}`), type: 'DELETE' })
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
            url: App.url(editingId ? `api/sales-regions/${editingId}` : 'api/sales-regions'),
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
