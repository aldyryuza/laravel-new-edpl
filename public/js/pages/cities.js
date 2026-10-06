/**
 * Master kota/kabupaten (kode wilayah BPS), di bawah provinsi.
 *
 * Tabel: Yajra server-side (GET api/cities/datatable, filter `province_id`), form tambah/edit di modal.
 */
'use strict';

$(function () {
    const $page = $('#cities-page');
    const can = { update: $page.data('can-update') === 1, delete: $page.data('can-delete') === 1 };

    const $form = $('#city-form');
    const $submit = $form.find('[type="submit"]');
    const $filterProvince = $('#filter-province');
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('city-modal'));
    let editingId = null;

    const table = $('#cities-table').DataTable({
        serverSide: true,
        processing: true,
        searchDelay: 400,
        order: [[1, 'asc']],
        ajax: App.dataTableSource('api/cities/datatable', () => ({ province_id: $filterProvince.val() })),
        columns: [
            { data: 'code', name: 'code', render: App.escape },
            { data: 'name', name: 'name', render: App.escape },
            { data: 'province', orderable: false, searchable: false, render: province => province ? App.escape(province.name) : '-' },
            { data: 'is_active', name: 'is_active', searchable: false, render: renderStatus },
            { data: null, orderable: false, searchable: false, className: 'text-end text-nowrap', render: renderActions }
        ],
        language: { emptyTable: 'Belum ada city', zeroRecords: 'Kota tidak ditemukan', search: 'Cari:' }
    });

    // Muat ulang tabel setelah import Excel berhasil (lihat js/excel-import.js).
    $(document).on('excel-import:done', () => table.ajax.reload());

    $filterProvince.on('change', () => table.ajax.reload());

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

    const findRow = id => table.rows().data().toArray().find(city => city.id === id);

    function openForm(city = null) {
        editingId = city ? city.id : null;

        $form[0].reset();
        App.clearErrors($form);
        $('#city-modal-title').text(city ? 'Edit Kota' : 'Tambah Kota');

        if (city) {
            $form.find('[name="code"]').val(city.code);
            $form.find('[name="name"]').val(city.name);
            $form.find('[name="province_id"]').val(city.province_id);
            $('#city-active').prop('checked', city.is_active);
        } else if ($filterProvince.val()) {
            $form.find('[name="province_id"]').val($filterProvince.val());
        }

        modal.show();
    }

    $('#btn-add-city').on('click', () => openForm());

    $('#cities-table').on('click', '.btn-edit', function () {
        openForm(findRow($(this).data('id')));
    });

    $('#cities-table').on('click', '.btn-delete', function () {
        const city = findRow($(this).data('id'));

        App.confirm(`Hapus city ${city.name} (${city.code})?`, 'Ya, hapus').then(confirmed => {
            if (!confirmed) {
                return;
            }

            $.ajax({ url: App.url(`api/cities/${city.id}`), type: 'DELETE' })
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
            url: App.url(editingId ? `api/cities/${editingId}` : 'api/cities'),
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
