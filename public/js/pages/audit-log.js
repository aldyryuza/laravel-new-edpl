/**
 * Audit log (read-only). Yajra server-side: GET api/activity-logs/datatable + filter form.
 */
'use strict';

$(function () {
    const $filter = $('#audit-filter');
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('audit-modal'));

    const BADGE = { auth: 'bg-label-info', data: 'bg-label-primary', security: 'bg-label-danger' };

    const formatDate = value => value
        ? new Date(value).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'medium' })
        : '-';

    const filters = () => Object.fromEntries(
        $filter.serializeArray().filter(field => field.value !== '').map(field => [field.name, field.value])
    );

    const table = $('#audit-table').DataTable({
        serverSide: true,
        processing: true,
        // Pencarian lewat form filter di atas (parameter `keyword`), bukan kotak search DataTables.
        searching: false,
        order: [[0, 'desc']],
        ajax: App.dataTableSource('api/activity-logs/datatable', filters),
        columns: [
            { data: 'created_at', name: 'created_at', className: 'text-nowrap', render: formatDate },
            {
                data: 'category_label',
                name: 'log_name',
                render: (label, type, row) => `<span class="badge ${BADGE[row.category] || 'bg-label-secondary'}">${App.escape(label)}</span>`
            },
            { data: 'description', name: 'description', render: App.escape },
            {
                data: 'causer',
                orderable: false,
                render: causer => causer
                    ? `${App.escape(causer.name)}<div class="small text-body-secondary">${App.escape(causer.nik)}</div>`
                    : '<span class="text-body-secondary">Sistem / tamu</span>'
            },
            { data: 'properties', orderable: false, className: 'text-nowrap', render: props => App.escape(props?.ip || '-') },
            {
                data: null,
                orderable: false,
                className: 'text-end',
                render: row => `<button type="button" class="btn btn-sm btn-icon btn-text-secondary btn-detail" data-id="${row.id}" title="Detail">
                                    <i class="icon-base bx bx-show"></i></button>`
            }
        ],
        language: { emptyTable: 'Belum ada aktivitas', zeroRecords: 'Aktivitas tidak ditemukan' }
    });

    const display = value => {
        if (value === null || value === undefined || value === '') {
            return '<span class="text-body-secondary">-</span>';
        }

        if (typeof value === 'boolean') {
            return value ? 'Ya' : 'Tidak';
        }

        return App.escape(Array.isArray(value) ? value.join(', ') : (typeof value === 'object' ? JSON.stringify(value) : value));
    };

    function renderChanges(changes) {
        const after = changes?.attributes || {};
        const before = changes?.old || {};
        const keys = Array.from(new Set([...Object.keys(before), ...Object.keys(after)]));

        if (keys.length === 0) {
            return '';
        }

        return `
            <h6 class="mt-4">Perubahan data</h6>
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0">
                    <thead><tr><th>Field</th><th>Sebelum</th><th>Sesudah</th></tr></thead>
                    <tbody>${keys.map(key => `
                        <tr><td><code>${App.escape(key)}</code></td><td>${display(before[key])}</td><td>${display(after[key])}</td></tr>`).join('')}
                    </tbody>
                </table>
            </div>`;
    }

    function renderProperties(properties) {
        const entries = Object.entries(properties || {});

        if (entries.length === 0) {
            return '';
        }

        return `
            <h6 class="mt-4">Informasi tambahan</h6>
            <dl class="row mb-0">${entries.map(([key, value]) => `
                <dt class="col-sm-3 text-break">${App.escape(key)}</dt><dd class="col-sm-9 text-break">${display(value)}</dd>`).join('')}
            </dl>`;
    }

    $('#audit-table').on('click', '.btn-detail', function () {
        const log = table.rows().data().toArray().find(item => item.id === $(this).data('id'));

        $('#audit-modal-body').html(`
            <dl class="row mb-0">
                <dt class="col-sm-3">Waktu</dt><dd class="col-sm-9">${formatDate(log.created_at)}</dd>
                <dt class="col-sm-3">Kategori</dt><dd class="col-sm-9">${App.escape(log.category_label)}</dd>
                <dt class="col-sm-3">Aktivitas</dt><dd class="col-sm-9">${App.escape(log.description)}</dd>
                <dt class="col-sm-3">Event</dt><dd class="col-sm-9"><code>${App.escape(log.event || '-')}</code></dd>
                <dt class="col-sm-3">Pelaku</dt><dd class="col-sm-9">${log.causer ? `${App.escape(log.causer.name)} (${App.escape(log.causer.nik)})` : 'Sistem / tamu'}</dd>
                <dt class="col-sm-3">Objek</dt><dd class="col-sm-9">${display(log.subject)}</dd>
            </dl>
            ${renderChanges(log.changes)}
            ${renderProperties(log.properties)}`);

        modal.show();
    });

    $filter.on('submit', function (event) {
        event.preventDefault();
        table.ajax.reload();
    });

    $filter.on('reset', () => setTimeout(() => table.ajax.reload()));
});
