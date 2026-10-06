/**
 * Master approver DPL (role + scope region/zona/global + user + periode).
 *
 * Tabel: Yajra server-side (GET api/dpl/approver-assignments/datatable, filter `role_id`).
 * Form: modal; field region/zona tampil sesuai scope yang dipilih. User memakai select2.
 */
'use strict';

$(function () {
    const $page = $('#assignments-page');
    const can = { update: $page.data('can-update') === 1, delete: $page.data('can-delete') === 1 };

    const $form = $('#assignment-form');
    const $submit = $form.find('[type="submit"]');
    const $filterRole = $('#filter-role');
    const $user = $('#assignment-user');
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('assignment-modal'));
    let editingId = null;

    const scopeLabels = { region: 'Region', zone: 'Zona', global: 'Global' };
    const muted = text => `<span class="text-body-secondary">${text}</span>`;

    $user.wrap('<div class="position-relative"></div>').select2({
        dropdownParent: $user.parent(),
        placeholder: 'Pilih user',
        width: '100%'
    });

    const table = $('#assignments-table').DataTable({
        serverSide: true,
        processing: true,
        searchDelay: 400,
        order: [[3, 'desc']],
        ajax: App.dataTableSource('api/dpl/approver-assignments/datatable', () => ({ role_id: $filterRole.val() })),
        columns: [
            { data: 'role', orderable: false, searchable: false, render: role => role ? App.escape(role.name) : '-' },
            { data: 'scope_type', orderable: false, searchable: false, render: renderScope },
            {
                data: 'user',
                orderable: false,
                searchable: false,
                render: user => user
                    ? `${App.escape(user.name)} <span class="text-body-secondary">(${App.escape(user.nik)})</span>`
                        + (user.is_active ? '' : ' <span class="badge bg-label-danger">user nonaktif</span>')
                    : '-'
            },
            { data: 'valid_from', name: 'valid_from', searchable: false, render: value => value ? App.escape(value) : muted('-') },
            { data: 'valid_until', name: 'valid_until', searchable: false, render: value => value ? App.escape(value) : muted('tanpa batas') },
            { data: 'is_active', name: 'is_active', searchable: false, render: renderStatus },
            { data: null, orderable: false, searchable: false, className: 'text-end text-nowrap', render: renderActions }
        ],
        language: { emptyTable: 'Belum ada approver', zeroRecords: 'Approver tidak ditemukan', search: 'Cari:' }
    });

    $filterRole.on('change', () => table.ajax.reload());

    function renderScope(scope, type, row) {
        if (scope === 'region' && row.region) {
            return `${scopeLabels.region}: ${App.escape(row.region.name)}`;
        }

        if (scope === 'zone' && row.zone) {
            return `${scopeLabels.zone}: ${App.escape(row.zone.name)}`;
        }

        return `<span class="badge bg-label-info">${scopeLabels.global}</span>`;
    }

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

    /** Tampilkan field region/zona sesuai scope. */
    function toggleScopeFields() {
        const scope = $form.find('[name="scope_type"]:checked').val();

        $form.find('[data-scope]').each(function () {
            $(this).toggleClass('d-none', $(this).data('scope') !== scope);
        });
    }

    $form.on('change', '[name="scope_type"]', toggleScopeFields);

    const findRow = id => table.rows().data().toArray().find(assignment => assignment.id === id);

    function openForm(assignment = null) {
        editingId = assignment ? assignment.id : null;

        $form[0].reset();
        App.clearErrors($form);
        $('#assignment-modal-title').text(assignment ? 'Edit Approver' : 'Tambah Approver');

        if (assignment) {
            $form.find('[name="role_id"]').val(assignment.role_id);
            $form.find(`[name="scope_type"][value="${assignment.scope_type}"]`).prop('checked', true);
            $form.find('[name="region_id"]').val(assignment.region_id || '');
            $form.find('[name="zone_id"]').val(assignment.zone_id || '');
            $form.find('[name="valid_from"]').val(assignment.valid_from || '');
            $form.find('[name="valid_until"]').val(assignment.valid_until || '');
            $('#assignment-active').prop('checked', assignment.is_active);
        } else if ($filterRole.val()) {
            $form.find('[name="role_id"]').val($filterRole.val());
        }

        $user.val(assignment ? String(assignment.user_id) : '').trigger('change');
        toggleScopeFields();
        modal.show();
    }

    $('#btn-add-assignment').on('click', () => openForm());

    $('#assignments-table').on('click', '.btn-edit', function () {
        openForm(findRow($(this).data('id')));
    });

    $('#assignments-table').on('click', '.btn-delete', function () {
        const assignment = findRow($(this).data('id'));
        const label = `${assignment.role ? assignment.role.name : ''} - ${assignment.user ? assignment.user.name : ''}`;

        App.confirm(`Hapus approver ${label}? Untuk menjaga histori, sebaiknya nonaktifkan atau isi "Berlaku Sampai".`, 'Ya, hapus')
            .then(confirmed => {
                if (!confirmed) {
                    return;
                }

                $.ajax({ url: App.url(`api/dpl/approver-assignments/${assignment.id}`), type: 'DELETE' })
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
            url: App.url(editingId ? `api/dpl/approver-assignments/${editingId}` : 'api/dpl/approver-assignments'),
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
