/**
 * Tarif logistic fee (%) khusus grup distributor + kota.
 *
 * Tabel: Yajra server-side (GET api/dpl/distributor-logistic-rates/datatable), form tambah/edit di modal
 * (kota memakai select2, grup distributor input bebas dengan saran datalist).
 */
'use strict';

$(function () {
    const $page = $('#rates-page');
    const can = { update: $page.data('can-update') === 1, delete: $page.data('can-delete') === 1 };

    const $form = $('#rate-form');
    const $submit = $form.find('[type="submit"]');
    const $city = $('#rate-city');
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('rate-modal'));
    let editingId = null;

    const muted = text => `<span class="text-body-secondary">${text}</span>`;
    // Nilai NUMERIC dikirim server sebagai string; hanya diformat untuk tampilan.
    const formatPercent = value => value === null ? muted('-') : Number(value).toLocaleString('id-ID', { maximumFractionDigits: 4 });

    $city.wrap('<div class="position-relative"></div>').select2({
        dropdownParent: $city.parent(),
        placeholder: $city.find('option[value=""]').text(),
        width: '100%'
    });

    const table = $('#rates-table').DataTable({
        serverSide: true,
        processing: true,
        searchDelay: 400,
        order: [[0, 'asc']],
        ajax: App.dataTableSource('api/dpl/distributor-logistic-rates/datatable'),
        columns: [
            { data: 'distributor_group_code', name: 'distributor_group_code', render: App.escape },
            {
                data: 'city',
                orderable: false,
                searchable: false,
                render: city => city
                    ? `${App.escape(city.name)}<br><small class="text-body-secondary">${App.escape(city.code)}</small>`
                    : '-'
            },
            { data: 'logistic_fee', name: 'logistic_fee', searchable: false, className: 'text-end text-nowrap', render: formatPercent },
            { data: 'note', orderable: false, searchable: false, render: note => note ? App.escape(note) : muted('-') },
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
        language: { emptyTable: 'Belum ada tarif logistic', zeroRecords: 'Tarif logistic tidak ditemukan', search: 'Cari grup/kota:' }
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

    const findRow = id => table.rows().data().toArray().find(rate => rate.id === id);

    function openForm(rate = null) {
        editingId = rate ? rate.id : null;

        $form[0].reset();
        App.clearErrors($form);
        $('#rate-modal-title').text(rate ? 'Edit Tarif' : 'Tambah Tarif');

        if (rate) {
            ['distributor_group_code', 'logistic_fee', 'note'].forEach(field => $form.find(`[name="${field}"]`).val(rate[field] ?? ''));
            $('#rate-active').prop('checked', rate.is_active);
        }

        $city.val(rate ? String(rate.city_id) : '').trigger('change');
        modal.show();
    }

    $('#btn-add-rate').on('click', () => openForm());

    $('#rates-table').on('click', '.btn-edit', function () {
        openForm(findRow($(this).data('id')));
    });

    $('#rates-table').on('click', '.btn-delete', function () {
        const rate = findRow($(this).data('id'));
        const label = `${rate.distributor_group_code} - ${rate.city ? rate.city.name : ''}`;

        App.confirm(`Hapus tarif logistic ${label}?`, 'Ya, hapus').then(confirmed => {
            if (!confirmed) {
                return;
            }

            $.ajax({ url: App.url(`api/dpl/distributor-logistic-rates/${rate.id}`), type: 'DELETE' })
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
            url: App.url(editingId ? `api/dpl/distributor-logistic-rates/${editingId}` : 'api/dpl/distributor-logistic-rates'),
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
