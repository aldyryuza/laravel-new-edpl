/**
 * Management user.
 *
 * Tabel memakai pagination server (GET api/users?page&per_page&search),
 * form tambah/edit di modal.
 */
'use strict';

$(function () {
    const $page = $('#users-page');
    const can = { update: $page.data('can-update') === 1, delete: $page.data('can-delete') === 1 };

    const $form = $('#user-form');
    const $submit = $form.find('[type="submit"]');
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('user-modal'));
    let editingId = null;

    const table = $('#users-table').DataTable({
        serverSide: true,
        processing: true,
        ordering: false,
        searchDelay: 400,
        ajax: function (params, callback) {
            $.get(App.url('api/users'), {
                page: Math.floor(params.start / params.length) + 1,
                per_page: params.length,
                search: params.search.value
            })
                .done(res => callback({
                    draw: params.draw,
                    data: res.data,
                    recordsTotal: res.meta.total,
                    recordsFiltered: res.meta.total
                }))
                .fail(xhr => {
                    App.handleError(xhr);
                    callback({ draw: params.draw, data: [], recordsTotal: 0, recordsFiltered: 0 });
                });
        },
        columns: [
            { data: 'nik', render: App.escape },
            { data: 'name', render: App.escape },
            { data: 'email', render: App.escape },
            {
                data: 'roles',
                render: roles => roles.length
                    ? roles.map(role => `<span class="badge bg-label-primary me-1">${App.escape(role.name)}</span>`).join('')
                    : '<span class="text-body-secondary">-</span>'
            },
            { data: null, className: 'text-end text-nowrap', render: renderActions }
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
            user.roles.forEach(role => $(`#user-role-${role.id}`).prop('checked', true));
        }

        modal.show();
    }

    $('#btn-add-user').on('click', () => openForm());

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
