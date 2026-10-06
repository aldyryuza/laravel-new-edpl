/**
 * Master area penjualan (zona -> region -> area).
 *
 * Tabel: Yajra server-side (GET api/sales-areas/datatable, filter `region_id`), form tambah/edit di modal.
 */
'use strict';

$(function () {
    const $page = $('#areas-page');
    const can = { update: $page.data('can-update') === 1, delete: $page.data('can-delete') === 1 };

    const $form = $('#area-form');
    const $submit = $form.find('[type="submit"]');
    const $filterRegion = $('#filter-region');
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('area-modal'));
    let editingId = null;

    const table = $('#areas-table').DataTable({
        serverSide: true,
        processing: true,
        searchDelay: 400,
        order: [[1, 'asc']],
        ajax: App.dataTableSource('api/sales-areas/datatable', () => ({ region_id: $filterRegion.val() })),
        columns: [
            { data: 'code', name: 'code', render: App.escape },
            { data: 'name', name: 'name', render: App.escape },
            { data: 'region', orderable: false, searchable: false, render: region => region ? App.escape(region.name) : '-' },
            {
                data: 'region',
                orderable: false,
                searchable: false,
                render: region => region && region.zone ? App.escape(region.zone.name) : '-'
            },
            { data: 'is_active', name: 'is_active', searchable: false, render: renderStatus },
            { data: null, orderable: false, searchable: false, className: 'text-end text-nowrap', render: renderActions }
        ],
        language: { emptyTable: 'Belum ada area', zeroRecords: 'Area tidak ditemukan', search: 'Cari:' }
    });

    $filterRegion.on('change', () => table.ajax.reload());

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

    const findRow = id => table.rows().data().toArray().find(area => area.id === id);

    function openForm(area = null) {
        editingId = area ? area.id : null;

        $form[0].reset();
        App.clearErrors($form);
        $('#area-modal-title').text(area ? 'Edit Area' : 'Tambah Area');

        if (area) {
            $form.find('[name="code"]').val(area.code);
            $form.find('[name="name"]').val(area.name);
            $form.find('[name="region_id"]').val(area.region_id);
            $('#area-active').prop('checked', area.is_active);
        } else if ($filterRegion.val()) {
            $form.find('[name="region_id"]').val($filterRegion.val());
        }

        modal.show();
    }

    $('#btn-add-area').on('click', () => openForm());

    $('#areas-table').on('click', '.btn-edit', function () {
        openForm(findRow($(this).data('id')));
    });

    $('#areas-table').on('click', '.btn-delete', function () {
        const area = findRow($(this).data('id'));

        App.confirm(`Hapus area ${area.name} (${area.code})?`, 'Ya, hapus').then(confirmed => {
            if (!confirmed) {
                return;
            }

            $.ajax({ url: App.url(`api/sales-areas/${area.id}`), type: 'DELETE' })
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
            url: App.url(editingId ? `api/sales-areas/${editingId}` : 'api/sales-areas'),
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
