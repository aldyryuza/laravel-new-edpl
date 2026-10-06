/**
 * Harga product (satu harga per product, tidak dibedakan per region).
 *
 * Tabel: Yajra server-side (GET api/dpl/product-prices/datatable) dengan filter `[data-filter]` dan
 * "berlaku hari ini". Form: modal (product memakai select2). Import Excel lewat js/excel-import.js.
 */
'use strict';

$(function () {
    const $page = $('#prices-page');
    const can = { update: $page.data('can-update') === 1, delete: $page.data('can-delete') === 1 };

    const $form = $('#price-form');
    const $submit = $form.find('[type="submit"]');
    const $filters = $page.find('[data-filter]');
    const $effective = $('#filter-effective');
    const $selects = $('#price-product');
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('price-modal'));
    let editingId = null;

    const muted = text => `<span class="text-body-secondary">${text}</span>`;
    // Nilai NUMERIC dikirim server sebagai string; hanya diformat untuk tampilan.
    const formatAmount = value => value === null
        ? muted('-')
        : Number(value).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 4 });

    $selects.each(function () {
        const $select = $(this);

        $select.wrap('<div class="position-relative"></div>').select2({
            dropdownParent: $select.parent(),
            placeholder: $select.find('option[value=""]').text(),
            width: '100%'
        });
    });

    const filterParams = () => ({
        ...Object.fromEntries($filters.toArray().map(el => [$(el).data('filter'), $(el).val()])),
        effective_today: $effective.is(':checked') ? 1 : ''
    });

    const table = $('#prices-table').DataTable({
        serverSide: true,
        processing: true,
        searchDelay: 400,
        order: [[3, 'desc']],
        ajax: App.dataTableSource('api/dpl/product-prices/datatable', filterParams),
        columns: [
            {
                data: 'product',
                orderable: false,
                searchable: false,
                render: product => product
                    ? `${App.escape(product.name)}<br><small class="text-body-secondary">${App.escape(product.code)}</small>`
                    : '-'
            },
            { data: 'price', name: 'price', searchable: false, className: 'text-end text-nowrap', render: formatAmount },
            { data: 'regular_price', name: 'regular_price', searchable: false, className: 'text-end text-nowrap', render: formatAmount },
            { data: 'valid_from', name: 'valid_from', searchable: false, render: value => value ? App.escape(value) : muted('-') },
            { data: 'valid_until', name: 'valid_until', searchable: false, render: value => value ? App.escape(value) : muted('tanpa batas') },
            {
                data: 'is_active',
                name: 'is_active',
                searchable: false,
                render: active => active
                    ? '<span class="badge bg-label-success">Aktif</span>'
                    : '<span class="badge bg-label-secondary">Nonaktif</span>'
            },
            { data: null, orderable: false, searchable: false, className: 'text-end text-nowrap', render: renderActions }
        ],
        language: { emptyTable: 'Belum ada harga', zeroRecords: 'Harga tidak ditemukan', search: 'Cari product:' }
    });

    $filters.add($effective).on('change', () => table.ajax.reload());

    // Muat ulang tabel setelah import Excel berhasil (lihat js/excel-import.js).
    $(document).on('excel-import:done', () => table.ajax.reload());

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

    const findRow = id => table.rows().data().toArray().find(price => price.id === id);

    function openForm(price = null) {
        editingId = price ? price.id : null;

        $form[0].reset();
        App.clearErrors($form);
        $('#price-modal-title').text(price ? 'Edit Harga' : 'Tambah Harga');

        if (price) {
            ['price', 'regular_price', 'valid_from', 'valid_until'].forEach(field => $form.find(`[name="${field}"]`).val(price[field] ?? ''));
            $('#price-active').prop('checked', price.is_active);
        }

        $('#price-product').val(price ? String(price.product_id) : '').trigger('change');
        modal.show();
    }

    $('#btn-add-price').on('click', () => openForm());

    $('#prices-table').on('click', '.btn-edit', function () {
        openForm(findRow($(this).data('id')));
    });

    $('#prices-table').on('click', '.btn-delete', function () {
        const price = findRow($(this).data('id'));
        const label = `${price.product ? price.product.code : ''} (${price.valid_from || 'tanpa tanggal'})`;

        App.confirm(`Hapus harga ${label}? Untuk menjaga histori, sebaiknya nonaktifkan atau isi "Berlaku Sampai".`, 'Ya, hapus')
            .then(confirmed => {
                if (!confirmed) {
                    return;
                }

                $.ajax({ url: App.url(`api/dpl/product-prices/${price.id}`), type: 'DELETE' })
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
            url: App.url(editingId ? `api/dpl/product-prices/${editingId}` : 'api/dpl/product-prices'),
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
