/**
 * Daftar pengajuan DPL.
 *
 * Tabel: Yajra server-side (GET api/dpl/proposals/datatable, filter proposal_status/team_code/mine/awaiting_me).
 * Badge "Giliran Anda" bila user punya item yang bisa di-approve sekarang; status program tampil bila bukan INACTIVE.
 * Detail/ubah dibuka di halaman form (dpl/proposals/{id}); draft milik user bisa dihapus dari sini.
 */
'use strict';

$(function () {
    const $page = $('#proposals-page');
    const canDelete = $page.data('can-delete') === 1;
    const showUrl = $page.data('show-url');
    const $filters = $page.find('[data-filter]');

    const muted = text => `<span class="text-body-secondary">${text}</span>`;
    const filterParams = () => Object.fromEntries($filters.toArray().map(el => [$(el).data('filter'), $(el).val()]));
    const statusColors = {
        DRAFT: 'secondary', SUBMITTED: 'info', IN_APPROVAL: 'info', PARTIALLY_APPROVED: 'warning',
        APPROVED: 'success', REJECTED: 'danger', CANCELLED: 'dark'
    };
    const programBadges = {
        ACTIVE: ['success', 'Aktif'], EXPIRED: ['secondary', 'Expired'], SUPERSEDED: ['dark', 'Superseded']
    };

    const table = $('#proposals-table').DataTable({
        serverSide: true,
        processing: true,
        searchDelay: 400,
        order: [[0, 'desc']],
        ajax: App.dataTableSource('api/dpl/proposals/datatable', filterParams),
        columns: [
            {
                data: 'dpl_number',
                name: 'dpl_number',
                render: (number, type, row) => `<a href="${showUrl}/${row.id}">${App.escape(number)}</a>`
                    + (row.proposal_type !== 'NORMAL' ? ` <span class="badge bg-label-warning">${App.escape(row.proposal_type)}</span>` : '')
                    + (row.awaiting_me ? ' <span class="badge bg-label-primary">Giliran Anda</span>' : '')
            },
            {
                data: 'outlet',
                orderable: false,
                searchable: false,
                render: outlet => outlet ? `${App.escape(outlet.name)} ${muted('(' + App.escape(outlet.code) + ')')}` : '-'
            },
            { data: 'team_code', orderable: false, searchable: false, render: App.escape },
            {
                data: 'date_start',
                name: 'date_start',
                searchable: false,
                render: (start, type, row) => `${App.escape(start)} s/d ${App.escape(row.date_end)}`
            },
            { data: 'items_count', orderable: false, searchable: false },
            {
                data: 'proposal_status',
                name: 'proposal_status',
                searchable: false,
                render: (status, type, row) => `<span class="badge bg-label-${statusColors[status] || 'secondary'}">${App.escape(status)}</span>`
                    + (programBadges[row.program_status]
                        ? ` <span class="badge bg-${programBadges[row.program_status][0]}">${programBadges[row.program_status][1]}</span>`
                        : '')
            },
            { data: 'creator', orderable: false, searchable: false, render: user => user ? App.escape(user.name) : '-' },
            { data: null, orderable: false, searchable: false, className: 'text-end text-nowrap', render: renderActions }
        ],
        language: { emptyTable: 'Belum ada DPL', zeroRecords: 'DPL tidak ditemukan', search: 'Cari:' }
    });

    $filters.on('change', () => table.ajax.reload());

    function renderActions(row) {
        let html = `<a href="${showUrl}/${row.id}" class="btn btn-sm btn-icon btn-text-secondary" title="${row.can_edit ? 'Edit' : 'Lihat'}">
                        <i class="icon-base bx ${row.can_edit ? 'bx-edit' : 'bx-show'}"></i></a>`;

        if (canDelete && row.can_edit) {
            html += `<button type="button" class="btn btn-sm btn-icon btn-text-danger btn-delete" data-id="${row.id}" title="Hapus draft">
                        <i class="icon-base bx bx-trash"></i></button>`;
        }

        return html;
    }

    $('#proposals-table').on('click', '.btn-delete', function () {
        const proposal = table.rows().data().toArray().find(row => row.id === $(this).data('id'));

        App.confirm(`Hapus draft DPL ${proposal.dpl_number}? Nomor DPL ini tidak akan dipakai ulang.`, 'Ya, hapus').then(confirmed => {
            if (!confirmed) {
                return;
            }

            $.ajax({ url: App.url(`api/dpl/proposals/${proposal.id}`), type: 'DELETE' })
                .done(res => {
                    App.notify('success', res.message);
                    table.ajax.reload(null, false);
                })
                .fail(xhr => App.handleError(xhr));
        });
    });
});
