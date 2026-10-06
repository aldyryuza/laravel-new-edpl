/**
 * Master zona penjualan.
 *
 * Tabel: Yajra server-side (GET api/sales-zones/datatable), form tambah/edit di modal.
 */
'use strict';

$(function () {
    const $page = $('#zones-page');
    const can = { update: $page.data('can-update') === 1, delete: $page.data('can-delete') === 1 };

    const $form = $('#zone-form');
    const $submit = $form.find('[type="submit"]');
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('zone-modal'));
    let editingId = null;

    const table = $('#zones-table').DataTable({
        serverSide: true,
        processing: true,
        searchDelay: 400,
        order: [[1, 'asc']],
        ajax: App.dataTableSource('api/sales-zones/datatable'),
        columns: [
            { data: 'code', name: 'code', render: App.escape },
            { data: 'name', name: 'name', render: App.escape },
            { data: 'regions_count', orderable: false, searchable: false },
            { data: 'is_active', name: 'is_active', searchable: false, render: renderStatus },
            { data: null, orderable: false, searchable: false, className: 'text-end text-nowrap', render: renderActions }
        ],
        language: { emptyTable: 'Belum ada zona', zeroRecords: 'Zona tidak ditemukan', search: 'Cari:' }
    });

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

    const findRow = id => table.rows().data().toArray().find(zone => zone.id === id);

    function openForm(zone = null) {
        editingId = zone ? zone.id : null;

        $form[0].reset();
        App.clearErrors($form);
        $('#zone-modal-title').text(zone ? 'Edit Zona' : 'Tambah Zona');

        if (zone) {
            $form.find('[name="code"]').val(zone.code);
            $form.find('[name="name"]').val(zone.name);
            $('#zone-active').prop('checked', zone.is_active);
        }

        modal.show();
    }

    $('#btn-add-zone').on('click', () => openForm());

    $('#zones-table').on('click', '.btn-edit', function () {
        openForm(findRow($(this).data('id')));
    });

    $('#zones-table').on('click', '.btn-delete', function () {
        const zone = findRow($(this).data('id'));

        App.confirm(`Hapus zona ${zone.name} (${zone.code})?`, 'Ya, hapus').then(confirmed => {
            if (!confirmed) {
                return;
            }

            $.ajax({ url: App.url(`api/sales-zones/${zone.id}`), type: 'DELETE' })
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
            url: App.url(editingId ? `api/sales-zones/${editingId}` : 'api/sales-zones'),
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
