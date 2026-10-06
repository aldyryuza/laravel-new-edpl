/**
 * Klasifikasi outlet: kategori (GRUP1) dan jenis kepemilikan (GRUP2).
 *
 * Dua tabel Yajra (api/outlet-categories/datatable, api/ownership-types/datatable) memakai satu modal form.
 */
'use strict';

$(function () {
    const $page = $('#classifications-page');
    const can = { update: $page.data('can-update') === 1, delete: $page.data('can-delete') === 1 };

    const kinds = {
        categories: { label: 'Kategori Outlet', endpoint: 'api/outlet-categories' },
        ownership: { label: 'Jenis Kepemilikan', endpoint: 'api/ownership-types' }
    };

    const $form = $('#classification-form');
    const $submit = $form.find('[type="submit"]');
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('classification-modal'));
    let editing = { kind: null, id: null };

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

    Object.entries(kinds).forEach(([kind, config]) => {
        config.table = $(`#${kind}-table`).DataTable({
            serverSide: true,
            processing: true,
            searchDelay: 400,
            order: [[0, 'asc']],
            ajax: App.dataTableSource(`${config.endpoint}/datatable`),
            columns: [
                { data: 'code', name: 'code', render: App.escape },
                { data: 'name', name: 'name', render: App.escape },
                { data: 'outlets_count', orderable: false, searchable: false },
                { data: 'is_active', name: 'is_active', searchable: false, render: renderStatus },
                { data: null, orderable: false, searchable: false, className: 'text-end text-nowrap', render: renderActions }
            ],
            language: { emptyTable: 'Belum ada data', zeroRecords: 'Data tidak ditemukan', search: 'Cari:' }
        });

        const findRow = id => config.table.rows().data().toArray().find(row => row.id === id);

        $(`#${kind}-table`).on('click', '.btn-edit', function () {
            openForm(kind, findRow($(this).data('id')));
        });

        $(`#${kind}-table`).on('click', '.btn-delete', function () {
            const row = findRow($(this).data('id'));

            App.confirm(`Hapus ${config.label.toLowerCase()} ${row.name} (${row.code})?`, 'Ya, hapus').then(confirmed => {
                if (!confirmed) {
                    return;
                }

                $.ajax({ url: App.url(`${config.endpoint}/${row.id}`), type: 'DELETE' })
                    .done(res => {
                        App.notify('success', res.message);
                        config.table.ajax.reload(null, false);
                    })
                    .fail(xhr => App.handleError(xhr));
            });
        });
    });

    function openForm(kind, row = null) {
        editing = { kind, id: row ? row.id : null };

        $form[0].reset();
        App.clearErrors($form);
        $('#classification-modal-title').text(`${row ? 'Edit' : 'Tambah'} ${kinds[kind].label}`);

        if (row) {
            $form.find('[name="code"]').val(row.code);
            $form.find('[name="name"]').val(row.name);
            $('#classification-active').prop('checked', row.is_active);
        }

        modal.show();
    }

    $page.on('click', '.btn-add', function () {
        openForm($(this).data('kind'));
    });

    $form.on('submit', function (event) {
        event.preventDefault();
        $submit.prop('disabled', true);

        const config = kinds[editing.kind];

        $.ajax({
            url: App.url(editing.id ? `${config.endpoint}/${editing.id}` : config.endpoint),
            type: editing.id ? 'PUT' : 'POST',
            data: $form.serialize()
        })
            .done(res => {
                modal.hide();
                App.notify('success', res.message);
                config.table.ajax.reload(null, false);
            })
            .fail(xhr => App.handleError(xhr, $form))
            .always(() => $submit.prop('disabled', false));
    });
});
