/**
 * Master team sales.
 *
 * Tabel: Yajra server-side (GET api/teams/datatable). Form: modal tambah/edit.
 */
'use strict';

$(function () {
    const $page = $('#teams-page');
    const can = { update: $page.data('can-update') === 1, delete: $page.data('can-delete') === 1 };

    const $form = $('#team-form');
    const $submit = $form.find('[type="submit"]');
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('team-modal'));
    let editingId = null;

    const table = $('#teams-table').DataTable({
        serverSide: true,
        processing: true,
        searchDelay: 400,
        order: [[1, 'asc']],
        ajax: App.dataTableSource('api/teams/datatable'),
        columns: [
            { data: 'code', name: 'code', render: App.escape },
            { data: 'name', name: 'name', render: App.escape },
            { data: 'assignments_count', orderable: false, searchable: false },
            { data: 'is_active', name: 'is_active', searchable: false, render: renderStatus },
            { data: null, orderable: false, searchable: false, className: 'text-end text-nowrap', render: renderActions }
        ],
        language: { emptyTable: 'Belum ada team', zeroRecords: 'Team tidak ditemukan', search: 'Cari:' }
    });

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

    const findRow = id => table.rows().data().toArray().find(team => team.id === id);

    function openForm(team = null) {
        editingId = team ? team.id : null;

        $form[0].reset();
        App.clearErrors($form);
        $('#team-modal-title').text(team ? 'Edit Team' : 'Tambah Team');

        if (team) {
            $form.find('[name="code"]').val(team.code);
            $form.find('[name="name"]').val(team.name);
            $('#team-active').prop('checked', team.is_active);
        }

        modal.show();
    }

    $('#btn-add-team').on('click', () => openForm());

    $('#teams-table').on('click', '.btn-edit', function () {
        openForm(findRow($(this).data('id')));
    });

    $('#teams-table').on('click', '.btn-delete', function () {
        const team = findRow($(this).data('id'));

        App.confirm(`Hapus team ${team.name}?`, 'Ya, hapus').then(confirmed => {
            if (!confirmed) {
                return;
            }

            $.ajax({ url: App.url(`api/teams/${team.id}`), type: 'DELETE' })
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
            url: App.url(editingId ? `api/teams/${editingId}` : 'api/teams'),
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
