/**
 * Margin khusus product per grup distributor (kota kosong = semua kota).
 *
 * Tabel: Yajra server-side (GET api/dpl/distributor-margin-rules/datatable), form tambah/edit di modal
 * (product & kota memakai select2, grup distributor input bebas dengan saran datalist).
 */
'use strict';

$(function () {
    const $page = $('#rules-page');
    const can = { update: $page.data('can-update') === 1, delete: $page.data('can-delete') === 1 };

    const $form = $('#rule-form');
    const $submit = $form.find('[type="submit"]');
    const $product = $('#rule-product');
    const $city = $('#rule-city');
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('rule-modal'));
    let editingId = null;

    const muted = text => `<span class="text-body-secondary">${text}</span>`;
    // Nilai NUMERIC dikirim server sebagai string; hanya diformat untuk tampilan.
    const formatPercent = value => value === null ? muted('-') : Number(value).toLocaleString('id-ID', { maximumFractionDigits: 4 });
    const codeName = item => item
        ? `${App.escape(item.name)}<br><small class="text-body-secondary">${App.escape(item.code)}</small>`
        : '-';

    $product.add($city).each(function () {
        const $select = $(this);

        $select.wrap('<div class="position-relative"></div>').select2({
            dropdownParent: $select.parent(),
            placeholder: $select.find('option[value=""]').text(),
            allowClear: !$select.prop('required'),
            width: '100%'
        });
    });

    const table = $('#rules-table').DataTable({
        serverSide: true,
        processing: true,
        searchDelay: 400,
        order: [[0, 'asc']],
        ajax: App.dataTableSource('api/dpl/distributor-margin-rules/datatable'),
        columns: [
            { data: 'distributor_group_code', name: 'distributor_group_code', render: App.escape },
            { data: 'product', orderable: false, searchable: false, render: codeName },
            { data: 'city', orderable: false, searchable: false, render: city => city ? codeName(city) : muted('Semua kota') },
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
        language: { emptyTable: 'Belum ada margin khusus', zeroRecords: 'Margin khusus tidak ditemukan', search: 'Cari grup/product:' }
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

    const findRow = id => table.rows().data().toArray().find(rule => rule.id === id);

    // Product yang sudah nonaktif tidak ada di pilihan; tambahkan agar data lama tetap bisa diedit.
    function ensureOption($select, item) {
        if (item && $select.find(`option[value="${item.id}"]`).length === 0) {
            $select.append(new Option(`${item.name} (${item.code}) - nonaktif`, item.id));
        }
    }

    function openForm(rule = null) {
        editingId = rule ? rule.id : null;

        $form[0].reset();
        App.clearErrors($form);
        $('#rule-modal-title').text(rule ? 'Edit Margin' : 'Tambah Margin');

        if (rule) {
            ['distributor_group_code', 'margin', 'note'].forEach(field => $form.find(`[name="${field}"]`).val(rule[field] ?? ''));
            $('#rule-active').prop('checked', rule.is_active);
            ensureOption($product, rule.product);
        }

        $product.val(rule ? String(rule.product_id) : '').trigger('change');
        $city.val(rule && rule.city_id ? String(rule.city_id) : '').trigger('change');
        modal.show();
    }

    $('#btn-add-rule').on('click', () => openForm());

    $('#rules-table').on('click', '.btn-edit', function () {
        openForm(findRow($(this).data('id')));
    });

    $('#rules-table').on('click', '.btn-delete', function () {
        const rule = findRow($(this).data('id'));
        const label = `${rule.distributor_group_code} - ${rule.product ? rule.product.code : ''} (${rule.city ? rule.city.name : 'Semua kota'})`;

        App.confirm(`Hapus margin khusus ${label}?`, 'Ya, hapus').then(confirmed => {
            if (!confirmed) {
                return;
            }

            $.ajax({ url: App.url(`api/dpl/distributor-margin-rules/${rule.id}`), type: 'DELETE' })
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
            url: App.url(editingId ? `api/dpl/distributor-margin-rules/${editingId}` : 'api/dpl/distributor-margin-rules'),
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
