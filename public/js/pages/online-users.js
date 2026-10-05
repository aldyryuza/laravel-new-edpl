/**
 * Daftar user online dari presence channel (public/js/realtime.js).
 */
'use strict';

$(function () {
    const $tbody = $('#online-users-table tbody');
    const $status = $('#realtime-status');
    const $unavailable = $('#realtime-unavailable');

    const STATUS = {
        connected: ['bg-label-success', 'Terhubung'],
        connecting: ['bg-label-secondary', 'Menghubungkan...'],
        unavailable: ['bg-label-danger', 'Tidak tersambung'],
        failed: ['bg-label-danger', 'Tidak tersambung'],
        disconnected: ['bg-label-warning', 'Terputus']
    };

    if (!window.Realtime) {
        $status.attr('class', 'badge bg-label-danger align-self-center').text('Tidak aktif');
        $unavailable.removeClass('d-none');
        $tbody.html('<tr><td colspan="4" class="text-center text-body-secondary py-6">Fitur real-time tidak aktif.</td></tr>');
        return;
    }

    Realtime.onStatus(state => {
        const [cls, label] = STATUS[state] || ['bg-label-secondary', state];
        $status.attr('class', `badge ${cls} align-self-center`).text(label);
        $unavailable.toggleClass('d-none', !['unavailable', 'failed'].includes(state));
    });

    Realtime.onMembers(members => {
        if (members.length === 0) {
            $tbody.html('<tr><td colspan="4" class="text-center text-body-secondary py-6">Belum ada data</td></tr>');
            return;
        }

        $tbody.html(members.map(member => `
            <tr>
                <td>${App.escape(member.nik)}</td>
                <td>${App.escape(member.name)}</td>
                <td>${member.role ? `<span class="badge bg-label-primary">${App.escape(member.role)}</span>` : '<span class="text-body-secondary">-</span>'}</td>
                <td>
                    ${member.page
                        ? `${App.escape(member.title || member.page)}<div class="small text-body-secondary">${App.escape(member.page)}</div>`
                        : '<span class="text-body-secondary">-</span>'}
                </td>
            </tr>`).join(''));
    });
});
