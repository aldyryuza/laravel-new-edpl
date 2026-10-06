/**
 * Management user.
 *
 * Tabel: Yajra server-side (GET api/users/datatable), form tambah/edit di modal.
 */
'use strict';

$(function () {
    const $page = $('#users-page');
    const can = { update: $page.data('can-update') === 1, delete: $page.data('can-delete') === 1 };

    const $form = $('#user-form');
    const $submit = $form.find('[type="submit"]');
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('user-modal'));
    let editingId = null;

    const formatDate = value => value
        ? new Date(value).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' })
        : '<span class="text-body-secondary">-</span>';

    const table = $('#users-table').DataTable({
        serverSide: true,
        processing: true,
        searchDelay: 400,
        order: [[1, 'asc']],
        ajax: App.dataTableSource('api/users/datatable'),
        columns: [
            { data: 'nik', name: 'nik', render: App.escape },
            { data: 'name', name: 'name', render: App.escape },
            { data: 'email', name: 'email', render: App.escape },
            {
                data: 'roles',
                orderable: false,
                searchable: false,
                render: roles => roles.length
                    ? roles.map(role => `<span class="badge bg-label-primary me-1">${App.escape(role.name)}</span>`).join('')
                    : '<span class="text-body-secondary">-</span>'
            },
            {
                data: 'is_active',
                name: 'is_active',
                searchable: false,
                render: active => active
                    ? '<span class="badge bg-label-success">Aktif</span>'
                    : '<span class="badge bg-label-secondary">Nonaktif</span>'
            },
            { data: 'last_login_at', name: 'last_login_at', searchable: false, className: 'text-nowrap', render: formatDate },
            { data: null, orderable: false, searchable: false, className: 'text-end text-nowrap', render: renderActions }
        ],
        language: { emptyTable: 'Belum ada user', zeroRecords: 'User tidak ditemukan', search: 'Cari:' }
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

    function openForm(user = null) {
        editingId = user ? user.id : null;

        $form[0].reset();
        App.clearErrors($form);

        $('#user-modal-title').text(user ? 'Edit User' : 'Tambah User');
        $('#user-password-required').toggleClass('d-none', !!user);
        $('#user-password-hint').toggleClass('d-none', !user);

        if (user) {
            $form.find('[name="nik"]').val(user.nik);
            $form.find('[name="name"]').val(user.name);
            $form.find('[name="email"]').val(user.email);
            $('#user-active').prop('checked', user.is_active);
            user.roles.forEach(role => $(`#user-role-${role.id}`).prop('checked', true));
        }

        modal.show();
    }

    $('#btn-add-user').on('click', () => openForm());

    // Ekspor mengikuti kata kunci pencarian yang sedang dipakai di tabel.
    $('#btn-export-users').on('click', function (event) {
        event.preventDefault();

        const search = table.search();
        const url = $(this).data('url') + (search ? `?search=${encodeURIComponent(search)}` : '');

        window.location.href = url;
    });

    $('#users-table').on('click', '.btn-edit', function () {
        openForm(table.rows().data().toArray().find(user => user.id === $(this).data('id')));
    });

    $('#users-table').on('click', '.btn-delete', function () {
        const user = table.rows().data().toArray().find(item => item.id === $(this).data('id'));

        App.confirm(`Hapus user ${user.name} (${user.nik})?`, 'Ya, hapus').then(confirmed => {
            if (!confirmed) {
                return;
            }

            $.ajax({ url: App.url(`api/users/${user.id}`), type: 'DELETE' })
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
            url: App.url(editingId ? `api/users/${editingId}` : 'api/users'),
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
