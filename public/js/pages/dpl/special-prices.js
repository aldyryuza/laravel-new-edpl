/**
 * Harga khusus (JKN/PADMA, dll.), opsional per group RS.
 *
 * Tabel: Yajra server-side (GET api/dpl/special-prices/datatable) dengan filter `[data-filter]` dan
 * "berlaku hari ini". Form: modal (product & group memakai select2). Import Excel lewat js/excel-import.js.
 */
'use strict';

$(function () {
    const $page = $('#special-page');
    const can = { update: $page.data('can-update') === 1, delete: $page.data('can-delete') === 1 };

    const $form = $('#special-form');
    const $submit = $form.find('[type="submit"]');
    const $filters = $page.find('[data-filter]');
    const $effective = $('#filter-effective');
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('special-modal'));
    let editingId = null;

    const muted = text => `<span class="text-body-secondary">${text}</span>`;
    // Nilai NUMERIC dikirim server sebagai string; hanya diformat untuk tampilan.
    const formatAmount = value => Number(value).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 4 });

    $('#special-product, #special-group').each(function () {
        const $select = $(this);

        $select.wrap('<div class="position-relative"></div>').select2({
            dropdownParent: $select.parent(),
            placeholder: $select.find('option[value=""]').text(),
            allowClear: !$select.prop('required'),
            width: '100%'
        });
    });

    const filterParams = () => ({
        ...Object.fromEntries($filters.toArray().map(el => [$(el).data('filter'), $(el).val()])),
        effective_today: $effective.is(':checked') ? 1 : ''
    });

    const table = $('#special-table').DataTable({
        serverSide: true,
        processing: true,
        searchDelay: 400,
        order: [[0, 'asc']],
        ajax: App.dataTableSource('api/dpl/special-prices/datatable', filterParams),
        columns: [
            { data: 'price_type', name: 'price_type', render: value => `<span class="badge bg-label-primary">${App.escape(value)}</span>` },
            {
                data: 'product',
                orderable: false,
                searchable: false,
                render: product => product
                    ? `${App.escape(product.name)}<br><small class="text-body-secondary">${App.escape(product.code)}</small>`
                    : '-'
            },
            { data: 'outlet_group', orderable: false, searchable: false, render: group => group ? App.escape(group.name) : muted('semua') },
            { data: 'price', name: 'price', searchable: false, className: 'text-end text-nowrap', render: formatAmount },
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
        language: { emptyTable: 'Belum ada harga khusus', zeroRecords: 'Harga khusus tidak ditemukan', search: 'Cari product:' }
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

    const findRow = id => table.rows().data().toArray().find(item => item.id === id);

    function openForm(item = null) {
        editingId = item ? item.id : null;

        $form[0].reset();
        App.clearErrors($form);
        $('#special-modal-title').text(item ? 'Edit Harga Khusus' : 'Tambah Harga Khusus');

        if (item) {
            ['price_type', 'price', 'valid_from', 'valid_until', 'note'].forEach(field => $form.find(`[name="${field}"]`).val(item[field] ?? ''));
            $('#special-active').prop('checked', item.is_active);
        }

        $('#special-product').val(item ? String(item.product_id) : '').trigger('change');
        $('#special-group').val(item && item.outlet_group_id ? String(item.outlet_group_id) : '').trigger('change');
        modal.show();
    }

    $('#btn-add-special').on('click', () => openForm());

    $('#special-table').on('click', '.btn-edit', function () {
        openForm(findRow($(this).data('id')));
    });

    $('#special-table').on('click', '.btn-delete', function () {
        const item = findRow($(this).data('id'));
        const label = `${item.price_type} ${item.product ? item.product.code : ''}`;

        App.confirm(`Hapus harga khusus ${label}? Untuk menjaga histori, sebaiknya nonaktifkan atau isi "Berlaku Sampai".`, 'Ya, hapus')
            .then(confirmed => {
                if (!confirmed) {
                    return;
                }

                $.ajax({ url: App.url(`api/dpl/special-prices/${item.id}`), type: 'DELETE' })
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
            url: App.url(editingId ? `api/dpl/special-prices/${editingId}` : 'api/dpl/special-prices'),
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
