/**
 * Wilayah user (Nasional / zona / region / area + team + periode).
 *
 * Tabel: Yajra server-side (GET api/user-assignments/datatable, filter `team_id`).
 * Form: modal; field zona/region/area tampil sesuai wilayah yang dipilih. User/region/area memakai select2.
 */
'use strict';

$(function () {
    const $page = $('#assignments-page');
    const can = { update: $page.data('can-update') === 1, delete: $page.data('can-delete') === 1 };

    const $form = $('#assignment-form');
    const $submit = $form.find('[type="submit"]');
    const $filterTeam = $('#filter-team');
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('assignment-modal'));
    let editingId = null;

    const scopeLabels = { national: 'Nasional', zone: 'Zona', region: 'Region', area: 'Area' };
    const muted = text => `<span class="text-body-secondary">${text}</span>`;
    const $selects = $('#assignment-user, #assignment-region, #assignment-area');

    $selects.each(function () {
        const $select = $(this);

        $select.wrap('<div class="position-relative"></div>').select2({
            dropdownParent: $select.parent(),
            placeholder: $select.find('option[value=""]').text(),
            width: '100%'
        });
    });

    const table = $('#assignments-table').DataTable({
        serverSide: true,
        processing: true,
        searchDelay: 400,
        order: [[3, 'desc']],
        ajax: App.dataTableSource('api/user-assignments/datatable', () => ({ team_id: $filterTeam.val() })),
        columns: [
            {
                data: 'user',
                orderable: false,
                searchable: false,
                render: user => user
                    ? `${App.escape(user.name)} <span class="text-body-secondary">(${App.escape(user.nik)})</span>`
                        + (user.is_active ? '' : ' <span class="badge bg-label-danger">user nonaktif</span>')
                    : '-'
            },
            { data: 'scope_type', orderable: false, searchable: false, render: renderScope },
            { data: 'team', orderable: false, searchable: false, render: team => team ? App.escape(team.name) : muted('-') },
            { data: 'valid_from', name: 'valid_from', searchable: false, render: value => value ? App.escape(value) : muted('-') },
            { data: 'valid_until', name: 'valid_until', searchable: false, render: value => value ? App.escape(value) : muted('tanpa batas') },
            { data: 'is_active', name: 'is_active', searchable: false, render: renderStatus },
            { data: null, orderable: false, searchable: false, className: 'text-end text-nowrap', render: renderActions }
        ],
        language: { emptyTable: 'Belum ada wilayah user', zeroRecords: 'Wilayah user tidak ditemukan', search: 'Cari:' }
    });

    $filterTeam.on('change', () => table.ajax.reload());

    function renderScope(scope, type, row) {
        const org = row[scope];

        if (scope === 'national' || !org) {
            return `<span class="badge bg-label-info">${scopeLabels.national}</span>`;
        }

        return `${scopeLabels[scope]}: ${App.escape(org.name)}`;
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

    /** Tampilkan field zona/region/area sesuai wilayah. */
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
        $('#assignment-modal-title').text(assignment ? 'Edit Wilayah' : 'Tambah Wilayah');

        if (assignment) {
            $form.find(`[name="scope_type"][value="${assignment.scope_type}"]`).prop('checked', true);
            $form.find('[name="zone_id"]').val(assignment.zone_id || '');
            $form.find('[name="team_id"]').val(assignment.team_id || '');
            $form.find('[name="valid_from"]').val(assignment.valid_from || '');
            $form.find('[name="valid_until"]').val(assignment.valid_until || '');
            $('#assignment-active').prop('checked', assignment.is_active);
        } else if ($filterTeam.val()) {
            $form.find('[name="team_id"]').val($filterTeam.val());
        }

        $('#assignment-user').val(assignment ? String(assignment.user_id) : '').trigger('change');
        $('#assignment-region').val(assignment && assignment.region_id ? String(assignment.region_id) : '').trigger('change');
        $('#assignment-area').val(assignment && assignment.area_id ? String(assignment.area_id) : '').trigger('change');
        toggleScopeFields();
        modal.show();
    }

    $('#btn-add-assignment').on('click', () => openForm());

    $('#assignments-table').on('click', '.btn-edit', function () {
        openForm(findRow($(this).data('id')));
    });

    $('#assignments-table').on('click', '.btn-delete', function () {
        const assignment = findRow($(this).data('id'));
        const label = assignment.user ? assignment.user.name : '';

        App.confirm(`Hapus wilayah user ${label}? Untuk menjaga histori, sebaiknya nonaktifkan atau isi "Berlaku Sampai".`, 'Ya, hapus')
            .then(confirmed => {
                if (!confirmed) {
                    return;
                }

                $.ajax({ url: App.url(`api/user-assignments/${assignment.id}`), type: 'DELETE' })
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
            url: App.url(editingId ? `api/user-assignments/${editingId}` : 'api/user-assignments'),
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
