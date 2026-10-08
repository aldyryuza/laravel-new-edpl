/**
 * Margin khusus outlet per grup distributor. Berlaku untuk cabang grup itu yang ditandai is_special_case_margin.
 *
 * Tabel: Yajra server-side (GET api/dpl/distributor-margin-overrides/datatable), form tambah/edit di modal
 * (grup distributor input bebas dengan saran datalist, outlet select2 AJAX ke api/dpl/distributor-margin-overrides/outlet-options).
 * Import Excel lewat js/excel-import.js.
 */
'use strict';

$(function () {
    const $page = $('#overrides-page');
    const can = { update: $page.data('can-update') === 1, delete: $page.data('can-delete') === 1 };

    const $form = $('#override-form');
    const $submit = $form.find('[type="submit"]');
    const $outlet = $('#override-outlet');
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('override-modal'));
    let editingId = null;

    const muted = text => `<span class="text-body-secondary">${text}</span>`;
    // Nilai NUMERIC dikirim server sebagai string; hanya diformat untuk tampilan.
    const formatPercent = value => value === null ? muted('-') : Number(value).toLocaleString('id-ID', { maximumFractionDigits: 4 });
    const codeName = item => item
        ? `${App.escape(item.name)}<br><small class="text-body-secondary">${App.escape(item.code)}</small>`
        : '-';

    $outlet.wrap('<div class="position-relative"></div>').select2({
        dropdownParent: $outlet.parent(),
        placeholder: 'Cari outlet',
        width: '100%',
        ajax: {
            url: App.url('api/dpl/distributor-margin-overrides/outlet-options'),
            delay: 300,
            data: params => ({ q: params.term || '' }),
            processResults: res => ({ results: res.data }),
            error: (xhr, status) => (status === 'abort' ? null : App.handleError(xhr))
        }
    });

    const table = $('#overrides-table').DataTable({
        serverSide: true,
        processing: true,
        searchDelay: 400,
        order: [[0, 'asc']],
        ajax: App.dataTableSource('api/dpl/distributor-margin-overrides/datatable'),
        columns: [
            {
                data: 'distributor_group_code',
                name: 'distributor_group_code',
                render: (groupCode, type, row) => {
                    // Margin hanya berlaku untuk cabang grup yang ditandai margin khusus outlet.
                    const warning = row.has_special_case_distributor
                        ? ''
                        : '<br><span class="badge bg-label-warning mt-1">belum ada distributor grup ini yang ditandai margin khusus outlet</span>';

                    return App.escape(groupCode) + warning;
                }
            },
            { data: 'outlet', orderable: false, searchable: false, render: codeName },
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
        language: { emptyTable: 'Belum ada margin khusus outlet', zeroRecords: 'Margin khusus outlet tidak ditemukan', search: 'Cari grup/outlet:' }
    });

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

    const findRow = id => table.rows().data().toArray().find(override => override.id === id);

    function openForm(override = null) {
        editingId = override ? override.id : null;

        $form[0].reset();
        App.clearErrors($form);
        $('#override-modal-title').text(override ? 'Edit Margin' : 'Tambah Margin');

        // Outlet dimuat lewat AJAX; saat edit, isi opsi outlet tersimpan agar langsung terpilih.
        $outlet.empty();

        if (override) {
            ['distributor_group_code', 'margin', 'note'].forEach(field => $form.find(`[name="${field}"]`).val(override[field] ?? ''));
            $('#override-active').prop('checked', override.is_active);

            if (override.outlet) {
                $outlet.append(new Option(`${override.outlet.name} (${override.outlet.code})`, override.outlet.id, true, true));
            }
        }

        $outlet.trigger('change');
        modal.show();
    }

    $('#btn-add-override').on('click', () => openForm());

    $('#overrides-table').on('click', '.btn-edit', function () {
        openForm(findRow($(this).data('id')));
    });

    $('#overrides-table').on('click', '.btn-delete', function () {
        const override = findRow($(this).data('id'));
        const label = `${override.distributor_group_code} - ${override.outlet ? override.outlet.code : ''}`;

        App.confirm(`Hapus margin khusus outlet ${label}?`, 'Ya, hapus').then(confirmed => {
            if (!confirmed) {
                return;
            }

            $.ajax({ url: App.url(`api/dpl/distributor-margin-overrides/${override.id}`), type: 'DELETE' })
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
            url: App.url(editingId ? `api/dpl/distributor-margin-overrides/${editingId}` : 'api/dpl/distributor-margin-overrides'),
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
