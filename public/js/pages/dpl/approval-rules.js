/**
 * Aturan jalur approval item DPL (rentang Total Calculator + provinsi outlet -> step wajib).
 *
 * Tabel: Yajra server-side (GET api/dpl/approval-rules/datatable), default urut `sort_order`.
 * Form: modal; step wajib & provinsi memakai select2 multi (provinsi kosong = semua provinsi).
 */
'use strict';

$(function () {
    const $page = $('#rules-page');
    const can = { update: $page.data('can-update') === 1, delete: $page.data('can-delete') === 1 };

    const $form = $('#rule-form');
    const $submit = $form.find('[type="submit"]');
    const $steps = $('#rule-steps');
    const $provinces = $('#rule-provinces');
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('rule-modal'));
    let editingId = null;

    const muted = text => `<span class="text-body-secondary">${text}</span>`;
    const isBlank = value => value === null || value === undefined || value === '';
    const percent = value => App.escape(String(Number(value)));

    [[$steps, 'Pilih step'], [$provinces, 'Semua provinsi']].forEach(([$select, placeholder]) => {
        $select.wrap('<div class="position-relative"></div>').select2({
            dropdownParent: $select.parent(),
            placeholder,
            width: '100%'
        });
    });

    const table = $('#rules-table').DataTable({
        serverSide: true,
        processing: true,
        searchDelay: 400,
        order: [[0, 'asc']],
        ajax: App.dataTableSource('api/dpl/approval-rules/datatable'),
        columns: [
            { data: 'sort_order', name: 'sort_order', searchable: false },
            {
                data: 'name',
                name: 'name',
                render: (name, type, row) => App.escape(name) + (row.note ? `<div class="small text-body-secondary">${App.escape(row.note)}</div>` : '')
            },
            { data: null, orderable: false, searchable: false, render: renderRange },
            {
                data: 'steps',
                orderable: false,
                searchable: false,
                render: steps => steps.length
                    ? steps.map(step => `<span class="badge bg-label-primary me-1">${App.escape(step.role ? step.role.name : `Step ${step.sequence}`)}</span>`).join('')
                    : muted('-')
            },
            {
                data: 'provinces',
                orderable: false,
                searchable: false,
                render: provinces => provinces.length
                    ? provinces.map(province => `<span class="badge bg-label-secondary me-1">${App.escape(province.name)}</span>`).join('')
                    : muted('Semua provinsi')
            },
            { data: 'is_active', name: 'is_active', searchable: false, render: renderStatus },
            { data: null, orderable: false, searchable: false, className: 'text-end text-nowrap', render: renderActions }
        ],
        language: { emptyTable: 'Belum ada aturan approval', zeroRecords: 'Aturan approval tidak ditemukan', search: 'Cari:' }
    });

    /** Bawah eksklusif ("> x"), atas inklusif ("s/d y"); keduanya kosong = semua. */
    function renderRange(row) {
        const parts = [];

        if (!isBlank(row.discount_lower)) {
            parts.push(`&gt; ${percent(row.discount_lower)}`);
        }

        if (!isBlank(row.discount_upper)) {
            parts.push(`s/d ${percent(row.discount_upper)}`);
        }

        return parts.length ? parts.join(' ') : muted('semua');
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

    const findRow = id => table.rows().data().toArray().find(rule => rule.id === id);

    function openForm(rule = null) {
        editingId = rule ? rule.id : null;

        $form[0].reset();
        App.clearErrors($form);
        $('#rule-modal-title').text(rule ? 'Edit Aturan' : 'Tambah Aturan');

        if (rule) {
            $form.find('[name="name"]').val(rule.name);
            $form.find('[name="sort_order"]').val(rule.sort_order);
            $form.find('[name="discount_lower"]').val(isBlank(rule.discount_lower) ? '' : Number(rule.discount_lower));
            $form.find('[name="discount_upper"]').val(isBlank(rule.discount_upper) ? '' : Number(rule.discount_upper));
            $form.find('[name="note"]').val(rule.note || '');
            $('#rule-active').prop('checked', rule.is_active);
        }

        $steps.val(rule ? rule.steps.map(step => String(step.id)) : []).trigger('change');
        $provinces.val(rule ? rule.provinces.map(province => String(province.id)) : []).trigger('change');
        modal.show();
    }

    $('#btn-add-rule').on('click', () => openForm());

    $('#rules-table').on('click', '.btn-edit', function () {
        openForm(findRow($(this).data('id')));
    });

    $('#rules-table').on('click', '.btn-delete', function () {
        const rule = findRow($(this).data('id'));

        App.confirm(`Hapus aturan approval ${rule.name}? DPL yang sudah diajukan tidak terpengaruh.`, 'Ya, hapus').then(confirmed => {
            if (!confirmed) {
                return;
            }

            $.ajax({ url: App.url(`api/dpl/approval-rules/${rule.id}`), type: 'DELETE' })
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
            url: App.url(editingId ? `api/dpl/approval-rules/${editingId}` : 'api/dpl/approval-rules'),
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
