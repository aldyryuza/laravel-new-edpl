/**
 * Daftar pengajuan DPL.
 *
 * Tabel: Yajra server-side (GET api/dpl/proposals/datatable, filter proposal_status/team_code/mine/awaiting_me).
 * Badge "Giliran Anda" bila user punya item yang bisa di-approve sekarang; status program tampil bila bukan INACTIVE.
 * Baris diberi warna sesuai status; DPL dalam approval menampilkan "Menunggu Acc ..." (level yang belum selesai).
 * Approver (RM–DMD) default memakai filter "Menunggu Approval Saya" (diatur di view).
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
    /** Label status & warna baris (keterangan ada di #proposal-status-legend). */
    const statusLabels = {
        DRAFT: 'Draft', SUBMITTED: 'Diajukan', IN_APPROVAL: 'Menunggu Approval', PARTIALLY_APPROVED: 'Approve Sebagian',
        APPROVED: 'Approved', REJECTED: 'Ditolak', CANCELLED: 'Dibatalkan'
    };
    const rowClasses = {
        DRAFT: 'table-secondary', SUBMITTED: 'table-info', IN_APPROVAL: 'table-info', PARTIALLY_APPROVED: 'table-warning',
        APPROVED: 'table-success', REJECTED: 'table-danger', CANCELLED: 'table-dark'
    };
    const programBadges = {
        ACTIVE: ['success', 'Aktif'], EXPIRED: ['secondary', 'Expired'], SUPERSEDED: ['dark', 'Superseded']
    };

    /** Penolakan terbaru: "Ditolak RM (Nama): alasan" (+ jumlah item lain yang ditolak). */
    function renderRejection(rejections) {
        const last = rejections[0];
        const who = last.auto ? 'otomatis' : `${last.role || ''}${last.rejected_by ? ` (${last.rejected_by})` : ''}`;
        const more = rejections.length > 1 ? ` (+${rejections.length - 1} item lain)` : '';

        return `<div class="small text-danger mt-1">Ditolak ${App.escape(who)}: ${App.escape(last.reason || '-')}${more}</div>`;
    }

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
                render: (status, type, row) => `<span class="badge bg-label-${statusColors[status] || 'secondary'}">${App.escape(statusLabels[status] || status)}</span>`
                    + (programBadges[row.program_status]
                        ? ` <span class="badge bg-${programBadges[row.program_status][0]}">${programBadges[row.program_status][1]}</span>`
                        : '')
                    + (row.waiting_roles && row.waiting_roles.length
                        ? `<div class="small text-body-secondary mt-1">Menunggu Acc ${App.escape(row.waiting_roles.join(', '))}</div>`
                        : '')
                    + (row.rejections && row.rejections.length ? renderRejection(row.rejections) : '')
            },
            { data: 'creator', orderable: false, searchable: false, render: user => user ? App.escape(user.name) : '-' },
            { data: null, orderable: false, searchable: false, className: 'text-end text-nowrap', render: renderActions }
        ],
        createdRow: (row, data) => $(row).addClass(rowClasses[data.proposal_status] || ''),
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
