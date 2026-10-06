/**
 * Group RS / group outlet (legacy GRUP3).
 *
 * Tabel: Yajra server-side (GET api/outlet-groups/datatable). Jumlah anggota aktif bisa diklik
 * untuk membuka halaman Outlet dengan filter group tersebut.
 */
'use strict';

$(function () {
    const $page = $('#groups-page');
    const can = {
        update: $page.data('can-update') === 1,
        delete: $page.data('can-delete') === 1,
        viewOutlets: $page.data('can-view-outlets') === 1
    };

    const $form = $('#group-form');
    const $submit = $form.find('[type="submit"]');
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('group-modal'));
    let editingId = null;

    const table = $('#groups-table').DataTable({
        serverSide: true,
        processing: true,
        searchDelay: 400,
        order: [[1, 'asc']],
        ajax: App.dataTableSource('api/outlet-groups/datatable'),
        columns: [
            { data: 'code', name: 'code', render: code => code ? App.escape(code) : '<span class="text-body-secondary">-</span>' },
            { data: 'name', name: 'name', render: App.escape },
            {
                data: 'description',
                orderable: false,
                searchable: false,
                render: value => value ? App.escape(value) : '<span class="text-body-secondary">-</span>'
            },
            { data: 'active_members_count', orderable: false, searchable: false, render: renderMembers },
            { data: 'is_active', name: 'is_active', searchable: false, render: renderStatus },
            { data: null, orderable: false, searchable: false, className: 'text-end text-nowrap', render: renderActions }
        ],
        language: { emptyTable: 'Belum ada group', zeroRecords: 'Group tidak ditemukan', search: 'Cari:' }
    });

    function renderMembers(count, type, row) {
        if (!can.viewOutlets || count === 0) {
            return count;
        }

        return `<a href="${$page.data('outlets-url')}?outlet_group_id=${row.id}">${count} outlet</a>`;
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

    const findRow = id => table.rows().data().toArray().find(group => group.id === id);

    function openForm(group = null) {
        editingId = group ? group.id : null;

        $form[0].reset();
        App.clearErrors($form);
        $('#group-modal-title').text(group ? 'Edit Group' : 'Tambah Group');

        if (group) {
            $form.find('[name="code"]').val(group.code);
            $form.find('[name="name"]').val(group.name);
            $form.find('[name="description"]').val(group.description);
            $('#group-active').prop('checked', group.is_active);
        }

        modal.show();
    }

    $('#btn-add-group').on('click', () => openForm());

    $('#groups-table').on('click', '.btn-edit', function () {
        openForm(findRow($(this).data('id')));
    });

    $('#groups-table').on('click', '.btn-delete', function () {
        const group = findRow($(this).data('id'));

        App.confirm(`Hapus group ${group.name}?`, 'Ya, hapus').then(confirmed => {
            if (!confirmed) {
                return;
            }

            $.ajax({ url: App.url(`api/outlet-groups/${group.id}`), type: 'DELETE' })
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
            url: App.url(editingId ? `api/outlet-groups/${editingId}` : 'api/outlet-groups'),
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
