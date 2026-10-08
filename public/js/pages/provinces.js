/**
 * Master provinsi (kode wilayah BPS).
 *
 * Tabel: Yajra server-side (GET api/provinces/datatable), form tambah/edit di modal.
 */
'use strict';

$(function () {
    const $page = $('#provinces-page');
    const can = { update: $page.data('can-update') === 1, delete: $page.data('can-delete') === 1 };

    const $form = $('#province-form');
    const $submit = $form.find('[type="submit"]');
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('province-modal'));
    let editingId = null;

    const table = $('#provinces-table').DataTable({
        serverSide: true,
        processing: true,
        searchDelay: 400,
        order: [[1, 'asc']],
        ajax: App.dataTableSource('api/provinces/datatable'),
        columns: [
            { data: 'code', name: 'code', render: App.escape },
            { data: 'name', name: 'name', render: App.escape },
            { data: 'cities_count', orderable: false, searchable: false },
            { data: 'is_java', orderable: false, searchable: false, render: java => java ? '<span class="badge bg-label-primary">Jawa</span>' : '-' },
            { data: 'is_active', name: 'is_active', searchable: false, render: renderStatus },
            { data: null, orderable: false, searchable: false, className: 'text-end text-nowrap', render: renderActions }
        ],
        language: { emptyTable: 'Belum ada provinsi', zeroRecords: 'Provinsi tidak ditemukan', search: 'Cari:' }
    });

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

    const findRow = id => table.rows().data().toArray().find(province => province.id === id);

    function openForm(province = null) {
        editingId = province ? province.id : null;

        $form[0].reset();
        App.clearErrors($form);
        $('#province-modal-title').text(province ? 'Edit Provinsi' : 'Tambah Provinsi');

        if (province) {
            $form.find('[name="code"]').val(province.code);
            $form.find('[name="name"]').val(province.name);
            $('#province-java').prop('checked', province.is_java);
            $('#province-active').prop('checked', province.is_active);
        }

        modal.show();
    }

    $('#btn-add-province').on('click', () => openForm());

    $('#provinces-table').on('click', '.btn-edit', function () {
        openForm(findRow($(this).data('id')));
    });

    $('#provinces-table').on('click', '.btn-delete', function () {
        const province = findRow($(this).data('id'));

        App.confirm(`Hapus provinsi ${province.name} (${province.code})?`, 'Ya, hapus').then(confirmed => {
            if (!confirmed) {
                return;
            }

            $.ajax({ url: App.url(`api/provinces/${province.id}`), type: 'DELETE' })
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
            url: App.url(editingId ? `api/provinces/${editingId}` : 'api/provinces'),
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
