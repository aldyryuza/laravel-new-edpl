/**
 * Master product. Volume (ml) menjadi dasar grouping 500/250/100/25 ml.
 *
 * Tabel: Yajra server-side (GET api/products/datatable) dengan filter `[data-filter]`.
 * Import Excel lewat js/excel-import.js.
 */
'use strict';

$(function () {
    const $page = $('#products-page');
    const can = { update: $page.data('can-update') === 1, delete: $page.data('can-delete') === 1 };

    const $form = $('#product-form');
    const $submit = $form.find('[type="submit"]');
    const $filters = $page.find('[data-filter]');
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('product-modal'));
    let editingId = null;

    const muted = text => `<span class="text-body-secondary">${text}</span>`;
    // "500.0000" -> "500"
    const formatVolume = value => value === null ? muted('-') : `${Number(value).toLocaleString('id-ID', { maximumFractionDigits: 4 })} ml`;
    const filterParams = () => Object.fromEntries($filters.toArray().map(el => [$(el).data('filter'), $(el).val()]));

    const table = $('#products-table').DataTable({
        serverSide: true,
        processing: true,
        searchDelay: 400,
        order: [[1, 'asc']],
        ajax: App.dataTableSource('api/products/datatable', filterParams),
        columns: [
            { data: 'code', name: 'code', render: App.escape },
            { data: 'name', name: 'name', render: App.escape },
            { data: 'volume', name: 'volume', searchable: false, className: 'text-nowrap', render: formatVolume },
            {
                data: 'product_group',
                orderable: false,
                searchable: false,
                render: (value, type, row) => (value ? App.escape(value) : muted('-'))
                    + (row.is_team_b_product ? ' <span class="badge bg-label-warning">Tim B</span>' : '')
                    + (row.calculator_limit !== null ? ` <span class="badge bg-label-info">batas ${App.escape(String(Number(row.calculator_limit)))}%</span>` : '')
            },
            {
                data: 'is_branded',
                name: 'is_branded',
                searchable: false,
                render: branded => branded ? '<span class="badge bg-label-primary">Branded</span>' : muted('-')
            },
            { data: 'netsuite_id', orderable: false, searchable: false, render: value => value ? App.escape(value) : muted('-') },
            { data: 'is_active', name: 'is_active', searchable: false, render: renderStatus },
            { data: null, orderable: false, searchable: false, className: 'text-end text-nowrap', render: renderActions }
        ],
        language: { emptyTable: 'Belum ada product', zeroRecords: 'Product tidak ditemukan', search: 'Cari:' }
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

    const findRow = id => table.rows().data().toArray().find(product => product.id === id);

    function openForm(product = null) {
        editingId = product ? product.id : null;

        $form[0].reset();
        App.clearErrors($form);
        $('#product-modal-title').text(product ? 'Edit Product' : 'Tambah Product');

        if (product) {
            $form.find('[name="code"]').val(product.code);
            $form.find('[name="name"]').val(product.name);
            $form.find('[name="volume"]').val(product.volume === null ? '' : Number(product.volume));
            $form.find('[name="product_group"]').val(product.product_group || '');
            $form.find('[name="calculator_limit"]').val(product.calculator_limit === null ? '' : Number(product.calculator_limit));
            $('#product-team-b').prop('checked', product.is_team_b_product);
            $form.find('[name="netsuite_id"]').val(product.netsuite_id);
            $('#product-branded').prop('checked', product.is_branded);
            $('#product-active').prop('checked', product.is_active);
        }

        modal.show();
    }

    $('#btn-add-product').on('click', () => openForm());

    $('#products-table').on('click', '.btn-edit', function () {
        openForm(findRow($(this).data('id')));
    });

    $('#products-table').on('click', '.btn-delete', function () {
        const product = findRow($(this).data('id'));

        App.confirm(`Hapus product ${product.name} (${product.code})?`, 'Ya, hapus').then(confirmed => {
            if (!confirmed) {
                return;
            }

            $.ajax({ url: App.url(`api/products/${product.id}`), type: 'DELETE' })
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
            url: App.url(editingId ? `api/products/${editingId}` : 'api/products'),
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
