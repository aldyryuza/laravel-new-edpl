/**
 * Region harga DPL.
 *
 * Tabel: Yajra server-side (GET api/dpl/price-regions/datatable), form tambah/edit di modal.
 * Import Excel lewat js/excel-import.js.
 */
'use strict';

$(function () {
    const $page = $('#price-regions-page');
    const can = { update: $page.data('can-update') === 1, delete: $page.data('can-delete') === 1 };

    const $form = $('#price-region-form');
    const $submit = $form.find('[type="submit"]');
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('price-region-modal'));
    let editingId = null;

    const muted = text => `<span class="text-body-secondary">${text}</span>`;

    const table = $('#price-regions-table').DataTable({
        serverSide: true,
        processing: true,
        searchDelay: 400,
        order: [[0, 'asc']],
        ajax: App.dataTableSource('api/dpl/price-regions/datatable'),
        columns: [
            { data: 'code', name: 'code', render: App.escape },
            { data: 'name', name: 'name', render: App.escape },
            { data: 'description', orderable: false, searchable: false, render: value => value ? App.escape(value) : muted('-') },
            { data: 'outlets_count', orderable: false, searchable: false },
            { data: 'distributors_count', orderable: false, searchable: false },
            { data: 'product_prices_count', orderable: false, searchable: false },
            { data: 'is_active', name: 'is_active', searchable: false, render: renderStatus },
            { data: null, orderable: false, searchable: false, className: 'text-end text-nowrap', render: renderActions }
        ],
        language: { emptyTable: 'Belum ada region harga', zeroRecords: 'Region harga tidak ditemukan', search: 'Cari:' }
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

    const findRow = id => table.rows().data().toArray().find(region => region.id === id);

    function openForm(region = null) {
        editingId = region ? region.id : null;

        $form[0].reset();
        App.clearErrors($form);
        $('#price-region-modal-title').text(region ? 'Edit Region Harga' : 'Tambah Region Harga');

        if (region) {
            $form.find('[name="code"]').val(region.code);
            $form.find('[name="name"]').val(region.name);
            $form.find('[name="description"]').val(region.description);
            $('#price-region-active').prop('checked', region.is_active);
        }

        modal.show();
    }

    $('#btn-add-price-region').on('click', () => openForm());

    $('#price-regions-table').on('click', '.btn-edit', function () {
        openForm(findRow($(this).data('id')));
    });

    $('#price-regions-table').on('click', '.btn-delete', function () {
        const region = findRow($(this).data('id'));

        App.confirm(`Hapus region harga ${region.name} (${region.code})?`, 'Ya, hapus').then(confirmed => {
            if (!confirmed) {
                return;
            }

            $.ajax({ url: App.url(`api/dpl/price-regions/${region.id}`), type: 'DELETE' })
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
            url: App.url(editingId ? `api/dpl/price-regions/${editingId}` : 'api/dpl/price-regions'),
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
