/**
 * Region harga outlet / distributor (satu halaman untuk dua tipe, lihat data-type di #mappings-page).
 *
 * Tabel: Yajra server-side (GET api/dpl/{label}-price-regions/datatable, filter `price_region_id`).
 * Ubah per baris lewat modal (PUT), massal lewat import Excel (js/excel-import.js).
 */
'use strict';

$(function () {
    const $page = $('#mappings-page');
    const label = $page.data('label');
    const ownerTable = $page.data('table');
    const endpoint = `api/dpl/${label}-price-regions`;
    const canUpdate = $page.data('can-update') === 1;

    const $form = $('#mapping-form');
    const $submit = $form.find('[type="submit"]');
    const $filter = $('#filter-price-region');
    const $select = $('#mapping-price-region');
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('mapping-modal'));
    let editing = null;

    const muted = text => `<span class="text-body-secondary">${text}</span>`;

    $select.wrap('<div class="position-relative"></div>').select2({
        dropdownParent: $select.parent(),
        placeholder: 'Tanpa region harga',
        allowClear: true,
        width: '100%'
    });

    const table = $('#mappings-table').DataTable({
        serverSide: true,
        processing: true,
        searchDelay: 400,
        order: [[1, 'asc']],
        ajax: App.dataTableSource(`${endpoint}/datatable`, () => ({ price_region_id: $filter.val() })),
        columns: [
            { data: 'code', name: `${ownerTable}.code`, render: App.escape },
            { data: 'name', name: `${ownerTable}.name`, render: App.escape },
            {
                data: 'price_region_name',
                orderable: false,
                searchable: false,
                render: (name, type, row) => name
                    ? `${App.escape(name)} <span class="text-body-secondary">(${App.escape(row.price_region_code)})</span>`
                    : muted('belum diatur')
            },
            {
                data: 'is_active',
                orderable: false,
                searchable: false,
                render: active => active
                    ? '<span class="badge bg-label-success">Aktif</span>'
                    : '<span class="badge bg-label-secondary">Nonaktif</span>'
            },
            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'text-end text-nowrap',
                render: row => canUpdate
                    ? `<button type="button" class="btn btn-sm btn-icon btn-text-secondary btn-edit" data-id="${row.id}" title="Ubah region harga">
                           <i class="icon-base bx bx-edit"></i></button>`
                    : ''
            }
        ],
        language: { emptyTable: 'Belum ada data', zeroRecords: 'Data tidak ditemukan', search: 'Cari:' }
    });

    $filter.on('change', () => table.ajax.reload());

    // Muat ulang tabel setelah import Excel berhasil (lihat js/excel-import.js).
    $(document).on('excel-import:done', () => table.ajax.reload());

    $('#mappings-table').on('click', '.btn-edit', function () {
        editing = table.rows().data().toArray().find(row => row.id === $(this).data('id'));

        App.clearErrors($form);
        $('#mapping-owner').html(`${App.escape(editing.name)} <span class="text-body-secondary">(${App.escape(editing.code)})</span>`);
        $select.val(editing.price_region_id ? String(editing.price_region_id) : '').trigger('change');
        modal.show();
    });

    $form.on('submit', function (event) {
        event.preventDefault();
        $submit.prop('disabled', true);

        $.ajax({ url: App.url(`${endpoint}/${editing.id}`), type: 'PUT', data: $form.serialize() })
            .done(res => {
                modal.hide();
                App.notify('success', res.message);
                table.ajax.reload(null, false);
            })
            .fail(xhr => App.handleError(xhr, $form))
            .always(() => $submit.prop('disabled', false));
    });
});
