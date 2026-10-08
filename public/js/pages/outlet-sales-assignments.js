/**
 * PIC outlet (AM / SPV). Pemilik DPL anak konsolidasi Group RS = SPV, bila kosong AM.
 *
 * Tabel: Yajra server-side (GET api/outlet-sales-assignments/datatable), form tambah/edit di modal
 * (outlet & user select2 AJAX ke api/outlet-sales-assignments/outlet-options dan user-options).
 * Import Excel lewat js/excel-import.js.
 */
'use strict';

$(function () {
    const $page = $('#assignments-page');
    const can = { update: $page.data('can-update') === 1, delete: $page.data('can-delete') === 1 };

    const $form = $('#assignment-form');
    const $submit = $form.find('[type="submit"]');
    const $outlet = $('#assignment-outlet');
    const $am = $('#assignment-am');
    const $spv = $('#assignment-spv');
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('assignment-modal'));
    let editingId = null;

    const codeName = (item, code) => item
        ? `${App.escape(item.name)}<br><small class="text-body-secondary">${App.escape(item[code])}</small>`
        : '<span class="text-body-secondary">-</span>';

    function ajaxSelect($select, path, placeholder, allowClear) {
        $select.wrap('<div class="position-relative"></div>').select2({
            dropdownParent: $select.parent(),
            placeholder: placeholder,
            allowClear: allowClear,
            width: '100%',
            ajax: {
                url: App.url(path),
                delay: 300,
                data: params => ({ q: params.term || '' }),
                processResults: res => ({ results: res.data }),
                error: (xhr, status) => (status === 'abort' ? null : App.handleError(xhr))
            }
        });
    }

    ajaxSelect($outlet, 'api/outlet-sales-assignments/outlet-options', 'Cari outlet', false);
    ajaxSelect($am, 'api/outlet-sales-assignments/user-options', 'Cari user', true);
    ajaxSelect($spv, 'api/outlet-sales-assignments/user-options', 'Cari user', true);

    const table = $('#assignments-table').DataTable({
        serverSide: true,
        processing: true,
        searchDelay: 400,
        order: [],
        ajax: App.dataTableSource('api/outlet-sales-assignments/datatable'),
        columns: [
            { data: 'outlet', orderable: false, searchable: false, render: outlet => codeName(outlet, 'code') },
            { data: 'am', orderable: false, searchable: false, render: user => codeName(user, 'nik') },
            { data: 'spv', orderable: false, searchable: false, render: user => codeName(user, 'nik') },
            { data: null, orderable: false, searchable: false, className: 'text-end text-nowrap', render: renderActions }
        ],
        language: { emptyTable: 'Belum ada PIC outlet', zeroRecords: 'PIC outlet tidak ditemukan', search: 'Cari outlet/user:' }
    });

    // Muat ulang tabel setelah import Excel berhasil (lihat js/excel-import.js).
    $(document).on('excel-import:done', () => table.ajax.reload());

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

    const findRow = id => table.rows().data().toArray().find(assignment => assignment.id === id);

    // Opsi tersimpan diisi langsung agar langsung terpilih (opsi lain dimuat lewat AJAX).
    function preset($select, item, code) {
        $select.empty();

        if (item) {
            $select.append(new Option(`${item.name} (${item[code]})`, item.id, true, true));
        }

        $select.trigger('change');
    }

    function openForm(assignment = null) {
        editingId = assignment ? assignment.id : null;

        $form[0].reset();
        App.clearErrors($form);
        $('#assignment-modal-title').text(assignment ? 'Edit PIC' : 'Tambah PIC');

        preset($outlet, assignment ? assignment.outlet : null, 'code');
        preset($am, assignment ? assignment.am : null, 'nik');
        preset($spv, assignment ? assignment.spv : null, 'nik');
        modal.show();
    }

    $('#btn-add-assignment').on('click', () => openForm());

    $('#assignments-table').on('click', '.btn-edit', function () {
        openForm(findRow($(this).data('id')));
    });

    $('#assignments-table').on('click', '.btn-delete', function () {
        const assignment = findRow($(this).data('id'));

        App.confirm(`Hapus PIC outlet ${assignment.outlet ? assignment.outlet.code : ''}?`, 'Ya, hapus').then(confirmed => {
            if (!confirmed) {
                return;
            }

            $.ajax({ url: App.url(`api/outlet-sales-assignments/${assignment.id}`), type: 'DELETE' })
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
            url: App.url(editingId ? `api/outlet-sales-assignments/${editingId}` : 'api/outlet-sales-assignments'),
            type: editingId ? 'PUT' : 'POST',
            data: {
                outlet_id: $outlet.val() || '',
                am_user_id: $am.val() || '',
                spv_user_id: $spv.val() || ''
            }
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
