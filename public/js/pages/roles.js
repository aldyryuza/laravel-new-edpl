/**
 * Management role. Hak akses per menu diatur di halaman roles/{id}/access.
 */
'use strict';

$(function () {
    const $page = $('#roles-page');
    const can = { update: $page.data('can-update') === 1, delete: $page.data('can-delete') === 1 };
    const accessUrl = id => String($page.data('access-url')).replace('__ID__', id);

    const $form = $('#role-form');
    const $submit = $form.find('[type="submit"]');
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('role-modal'));
    let editingId = null;

    const table = $('#roles-table').DataTable({
        ajax: { url: App.url('api/roles'), dataSrc: 'data', error: xhr => App.handleError(xhr) },
        order: [[0, 'asc']],
        columns: [
            { data: 'name', render: App.escape },
            { data: 'description', render: value => value ? App.escape(value) : '<span class="text-body-secondary">-</span>' },
            { data: 'users_count' },
            { data: null, orderable: false, searchable: false, className: 'text-end text-nowrap', render: renderActions }
        ],
        language: { emptyTable: 'Belum ada role', zeroRecords: 'Role tidak ditemukan', search: 'Cari:' }
    });

    function renderActions(row) {
        let html = `<a href="${accessUrl(row.id)}" class="btn btn-sm btn-icon btn-text-primary" title="Hak akses">
                        <i class="icon-base bx bx-shield-quarter"></i></a>`;

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

    const findRow = id => table.rows().data().toArray().find(role => role.id === id);

    function openForm(role = null) {
        editingId = role ? role.id : null;

        $form[0].reset();
        App.clearErrors($form);
        $('#role-modal-title').text(role ? 'Edit Role' : 'Tambah Role');

        if (role) {
            $form.find('[name="name"]').val(role.name);
            $form.find('[name="description"]').val(role.description);
        }

        modal.show();
    }

    $('#btn-add-role').on('click', () => openForm());

    $('#roles-table').on('click', '.btn-edit', function () {
        openForm(findRow($(this).data('id')));
    });

    $('#roles-table').on('click', '.btn-delete', function () {
        const role = findRow($(this).data('id'));

        App.confirm(`Hapus role ${role.name}?`, 'Ya, hapus').then(confirmed => {
            if (!confirmed) {
                return;
            }

            $.ajax({ url: App.url(`api/roles/${role.id}`), type: 'DELETE' })
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
            url: App.url(editingId ? `api/roles/${editingId}` : 'api/roles'),
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
