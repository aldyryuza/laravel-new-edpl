/**
 * Override margin per product (hanya item non-Direct). Grup distributor / team kosong = semua.
 *
 * Tabel: Yajra server-side (GET api/dpl/product-margin-overrides/datatable), form tambah/edit di modal
 * (product memakai select2, grup distributor input bebas dengan saran datalist).
 */
'use strict';

$(function () {
    const $page = $('#overrides-page');
    const can = { update: $page.data('can-update') === 1, delete: $page.data('can-delete') === 1 };

    const $form = $('#override-form');
    const $submit = $form.find('[type="submit"]');
    const $product = $('#override-product');
    const $team = $('#override-team');
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('override-modal'));
    let editingId = null;

    const muted = text => `<span class="text-body-secondary">${text}</span>`;
    // Nilai NUMERIC dikirim server sebagai string; hanya diformat untuk tampilan.
    const formatPercent = value => value === null ? muted('-') : Number(value).toLocaleString('id-ID', { maximumFractionDigits: 4 });

    $product.add($team).each(function () {
        const $select = $(this);

        $select.wrap('<div class="position-relative"></div>').select2({
            dropdownParent: $select.parent(),
            placeholder: $select.find('option[value=""]').text(),
            allowClear: !$select.prop('required'),
            width: '100%'
        });
    });

    const table = $('#overrides-table').DataTable({
        serverSide: true,
        processing: true,
        searchDelay: 400,
        order: [[1, 'asc']],
        ajax: App.dataTableSource('api/dpl/product-margin-overrides/datatable'),
        columns: [
            {
                data: 'product',
                orderable: false,
                searchable: false,
                render: product => product
                    ? `${App.escape(product.name)}<br><small class="text-body-secondary">${App.escape(product.code)}</small>`
                    : '-'
            },
            { data: 'distributor_group_code', name: 'distributor_group_code', render: value => value ? App.escape(value) : muted('Semua grup') },
            { data: 'team_code', name: 'team_code', render: value => value ? App.escape(value) : muted('Semua team') },
            { data: 'margin', name: 'margin', searchable: false, className: 'text-end text-nowrap', render: formatPercent },
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
        language: { emptyTable: 'Belum ada override margin', zeroRecords: 'Override margin tidak ditemukan', search: 'Cari product/grup/team:' }
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

    const findRow = id => table.rows().data().toArray().find(override => override.id === id);

    // Product yang sudah nonaktif tidak ada di pilihan; tambahkan agar data lama tetap bisa diedit.
    function ensureProductOption(product) {
        if (product && $product.find(`option[value="${product.id}"]`).length === 0) {
            $product.append(new Option(`${product.name} (${product.code}) - nonaktif`, product.id));
        }
    }

    function openForm(override = null) {
        editingId = override ? override.id : null;

        $form[0].reset();
        App.clearErrors($form);
        $('#override-modal-title').text(override ? 'Edit Override' : 'Tambah Override');

        if (override) {
            ['distributor_group_code', 'margin', 'note'].forEach(field => $form.find(`[name="${field}"]`).val(override[field] ?? ''));
            $('#override-active').prop('checked', override.is_active);
            ensureProductOption(override.product);
        }

        $product.val(override ? String(override.product_id) : '').trigger('change');
        $team.val(override && override.team_code ? override.team_code : '').trigger('change');
        modal.show();
    }

    $('#btn-add-override').on('click', () => openForm());

    $('#overrides-table').on('click', '.btn-edit', function () {
        openForm(findRow($(this).data('id')));
    });

    $('#overrides-table').on('click', '.btn-delete', function () {
        const override = findRow($(this).data('id'));
        const label = `${override.product ? override.product.code : ''} (${override.distributor_group_code || 'Semua grup'}, ${override.team_code || 'Semua team'})`;

        App.confirm(`Hapus override margin ${label}?`, 'Ya, hapus').then(confirmed => {
            if (!confirmed) {
                return;
            }

            $.ajax({ url: App.url(`api/dpl/product-margin-overrides/${override.id}`), type: 'DELETE' })
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
            url: App.url(editingId ? `api/dpl/product-margin-overrides/${editingId}` : 'api/dpl/product-margin-overrides'),
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
