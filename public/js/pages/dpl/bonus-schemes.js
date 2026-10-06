/**
 * Skema bonus (mengikuti legacy dpl_bonus: bonus, diskon invoice/bonus/pembulatan, total diskon).
 *
 * Tabel: Yajra server-side (GET api/dpl/bonus-schemes/datatable), form tambah/edit di modal.
 */
'use strict';

$(function () {
    const $page = $('#bonus-page');
    const can = { update: $page.data('can-update') === 1, delete: $page.data('can-delete') === 1 };

    const $form = $('#bonus-form');
    const $submit = $form.find('[type="submit"]');
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('bonus-modal'));
    let editingId = null;

    const numberFields = ['bonus', 'discount_invoice', 'discount_bonus', 'discount_rounded', 'total_discount'];
    const muted = text => `<span class="text-body-secondary">${text}</span>`;
    // Nilai NUMERIC dikirim server sebagai string; hanya diformat untuk tampilan.
    const formatNumber = value => value === null ? muted('-') : Number(value).toLocaleString('id-ID', { maximumFractionDigits: 4 });

    const table = $('#bonus-table').DataTable({
        serverSide: true,
        processing: true,
        searchDelay: 400,
        order: [[1, 'asc']],
        ajax: App.dataTableSource('api/dpl/bonus-schemes/datatable'),
        columns: [
            { data: 'code', name: 'code', render: value => value ? App.escape(value) : muted('-') },
            { data: 'name', name: 'name', render: App.escape },
            ...numberFields.map(field => ({
                data: field,
                name: field,
                orderable: ['bonus', 'total_discount'].includes(field),
                searchable: false,
                className: 'text-end',
                render: formatNumber
            })),
            { data: 'dpl_items_count', orderable: false, searchable: false },
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
        language: { emptyTable: 'Belum ada skema bonus', zeroRecords: 'Skema bonus tidak ditemukan', search: 'Cari:' }
    });

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

    const findRow = id => table.rows().data().toArray().find(scheme => scheme.id === id);

    function openForm(scheme = null) {
        editingId = scheme ? scheme.id : null;

        $form[0].reset();
        App.clearErrors($form);
        $('#bonus-modal-title').text(scheme ? 'Edit Skema Bonus' : 'Tambah Skema Bonus');

        if (scheme) {
            $form.find('[name="code"]').val(scheme.code);
            $form.find('[name="name"]').val(scheme.name);
            numberFields.forEach(field => $form.find(`[name="${field}"]`).val(scheme[field] === null ? '' : Number(scheme[field])));
            $('#bonus-active').prop('checked', scheme.is_active);
        }

        modal.show();
    }

    $('#btn-add-bonus').on('click', () => openForm());

    $('#bonus-table').on('click', '.btn-edit', function () {
        openForm(findRow($(this).data('id')));
    });

    $('#bonus-table').on('click', '.btn-delete', function () {
        const scheme = findRow($(this).data('id'));

        App.confirm(`Hapus skema bonus ${scheme.name}?`, 'Ya, hapus').then(confirmed => {
            if (!confirmed) {
                return;
            }

            $.ajax({ url: App.url(`api/dpl/bonus-schemes/${scheme.id}`), type: 'DELETE' })
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
            url: App.url(editingId ? `api/dpl/bonus-schemes/${editingId}` : 'api/dpl/bonus-schemes'),
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
