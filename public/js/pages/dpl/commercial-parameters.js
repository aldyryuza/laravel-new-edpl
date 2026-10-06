/**
 * Parameter komersial (nilai bertipe angka / teks / ya-tidak).
 *
 * Tabel: Yajra server-side (GET api/dpl/commercial-parameters/datatable), form tambah/edit di modal.
 * Hanya satu input nilai yang aktif (punya name="value") sesuai tipe yang dipilih.
 */
'use strict';

$(function () {
    const $page = $('#parameters-page');
    const can = { update: $page.data('can-update') === 1, delete: $page.data('can-delete') === 1 };

    const $form = $('#parameter-form');
    const $submit = $form.find('[type="submit"]');
    const $type = $('#parameter-type');
    const $valueInputs = $form.find('[data-kind]');
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('parameter-modal'));
    let editingId = null;

    const typeLabels = { number: 'Angka', text: 'Teks', boolean: 'Ya / Tidak' };
    const muted = text => `<span class="text-body-secondary">${text}</span>`;

    function formatValue(value, type) {
        if (type === 'boolean') {
            return value ? 'Ya' : 'Tidak';
        }

        if (type === 'number') {
            return Number(value).toLocaleString('id-ID', { maximumFractionDigits: 4 });
        }

        return App.escape(value);
    }

    /** Tampilkan input nilai sesuai tipe; hanya input itu yang ikut dikirim. */
    function showValueInput(type) {
        $valueInputs.each(function () {
            const active = $(this).data('kind') === type;

            $(this).toggleClass('d-none', !active).prop('disabled', !active).attr('name', active ? 'value' : null);
        });
    }

    $type.on('change', () => showValueInput($type.val()));

    const table = $('#parameters-table').DataTable({
        serverSide: true,
        processing: true,
        searchDelay: 400,
        order: [[0, 'asc']],
        ajax: App.dataTableSource('api/dpl/commercial-parameters/datatable'),
        columns: [
            { data: 'code', name: 'code', render: code => `<code>${App.escape(code)}</code>` },
            { data: 'value_type', orderable: false, searchable: false, render: type => typeLabels[type] || App.escape(type) },
            { data: 'typed_value', orderable: false, searchable: false, render: (value, type, row) => formatValue(value, row.value_type) },
            { data: 'description', orderable: false, searchable: false, render: value => value ? App.escape(value) : muted('-') },
            {
                data: 'is_active',
                name: 'is_active',
                searchable: false,
                render: active => active
                    ? '<span class="badge bg-label-success">Aktif</span>'
                    : '<span class="badge bg-label-secondary">Nonaktif</span>'
            },
            { data: null, orderable: false, searchable: false, className: 'text-end text-nowrap', render: renderActions }
        ],
        language: { emptyTable: 'Belum ada parameter', zeroRecords: 'Parameter tidak ditemukan', search: 'Cari:' }
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

    const findRow = id => table.rows().data().toArray().find(parameter => parameter.id === id);

    function openForm(parameter = null) {
        editingId = parameter ? parameter.id : null;

        $form[0].reset();
        App.clearErrors($form);
        $('#parameter-modal-title').text(parameter ? 'Edit Parameter' : 'Tambah Parameter');

        const type = parameter ? parameter.value_type : 'number';
        $type.val(type);
        showValueInput(type);

        if (parameter) {
            $form.find('[name="code"]').val(parameter.code);
            $form.find('[name="description"]').val(parameter.description);
            $form.find('[name="value"]').val(type === 'boolean' ? (parameter.typed_value ? '1' : '0') : parameter.typed_value);
            $('#parameter-active').prop('checked', parameter.is_active);
        }

        modal.show();
    }

    $('#btn-add-parameter').on('click', () => openForm());

    $('#parameters-table').on('click', '.btn-edit', function () {
        openForm(findRow($(this).data('id')));
    });

    $('#parameters-table').on('click', '.btn-delete', function () {
        const parameter = findRow($(this).data('id'));

        App.confirm(`Hapus parameter ${parameter.code}? Bila masih dipakai program, sebaiknya nonaktifkan saja.`, 'Ya, hapus')
            .then(confirmed => {
                if (!confirmed) {
                    return;
                }

                $.ajax({ url: App.url(`api/dpl/commercial-parameters/${parameter.id}`), type: 'DELETE' })
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

        // Kode selalu huruf besar (sesuai format KODE_PARAMETER).
        const $code = $form.find('[name="code"]');
        $code.val($code.val().toUpperCase().trim());

        $.ajax({
            url: App.url(editingId ? `api/dpl/commercial-parameters/${editingId}` : 'api/dpl/commercial-parameters'),
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
