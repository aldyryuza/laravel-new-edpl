/**
 * Management menu.
 *
 * Tabel: Yajra server-side (GET api/menus/datatable), urutan pohon parent -> sub menu.
 * Pilihan parent di form: GET api/menus (semua menu), dimuat saat form dibuka.
 */
'use strict';

$(function () {
    const $page = $('#menus-page');
    const can = { update: $page.data('can-update') === 1, delete: $page.data('can-delete') === 1 };

    const $form = $('#menu-form');
    const $submit = $form.find('[type="submit"]');
    const $parent = $('#menu-parent');
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('menu-modal'));
    let editingId = null;

    const indent = depth => '&mdash; '.repeat(depth);

    function renderActions(menu) {
        let html = '';

        if (can.update) {
            html += `<button type="button" class="btn btn-sm btn-icon btn-text-secondary btn-edit" data-id="${menu.id}" title="Edit">
                        <i class="icon-base bx bx-edit"></i></button>`;
        }

        if (can.delete) {
            html += `<button type="button" class="btn btn-sm btn-icon btn-text-danger btn-delete" data-id="${menu.id}" title="Hapus">
                        <i class="icon-base bx bx-trash"></i></button>`;
        }

        return html;
    }

    const table = $('#menus-table').DataTable({
        serverSide: true,
        processing: true,
        searchDelay: 400,
        // Urutan pohon ditentukan server, jadi sorting per kolom dimatikan.
        ordering: false,
        pageLength: 50,
        ajax: App.dataTableSource('api/menus/datatable'),
        createdRow: (row, menu) => {
            $('td', row).eq(0).css('padding-left', `${1.25 + menu.depth * 1.5}rem`);
        },
        columns: [
            {
                data: 'title',
                name: 'title',
                render: (title, type, menu) => `${menu.icon ? `<i class="icon-base ${App.escape(menu.icon)} me-2"></i>` : ''}${App.escape(title)}`
            },
            { data: 'code', name: 'code', render: code => `<code>${App.escape(code)}</code>` },
            {
                data: 'route',
                name: 'route',
                render: route => route ? App.escape(route) : '<span class="text-body-secondary">grup</span>'
            },
            { data: 'sort_order', searchable: false, className: 'text-center' },
            {
                data: 'is_active',
                searchable: false,
                className: 'text-center',
                render: active => active
                    ? '<span class="badge bg-label-success">Aktif</span>'
                    : '<span class="badge bg-label-secondary">Nonaktif</span>'
            },
            { data: null, searchable: false, className: 'text-end text-nowrap', render: renderActions }
        ],
        language: { emptyTable: 'Belum ada menu', zeroRecords: 'Menu tidak ditemukan', search: 'Cari:' }
    });

    const findRow = id => table.rows().data().toArray().find(menu => menu.id === id);

    /** Opsi parent: semua menu kecuali menu yang sedang diedit beserta turunannya. */
    function fillParentOptions(menus, excludeId = null) {
        const excluded = new Set();

        if (excludeId) {
            excluded.add(excludeId);
            // Data urut pohon (parent sebelum anak), jadi satu kali lewat sudah cukup.
            menus.forEach(menu => {
                if (excluded.has(menu.parent_id)) {
                    excluded.add(menu.id);
                }
            });
        }

        $parent.html('<option value="">- Tanpa parent (menu utama) -</option>' + menus
            .filter(menu => !excluded.has(menu.id))
            .map(menu => `<option value="${menu.id}">${indent(menu.depth)}${App.escape(menu.title)}</option>`)
            .join(''));
    }

    function openForm(menu = null) {
        editingId = menu ? menu.id : null;

        $.get(App.url('api/menus'))
            .done(res => {
                $form[0].reset();
                App.clearErrors($form);
                fillParentOptions(res.data, editingId);
                $('#menu-modal-title').text(menu ? 'Edit Menu' : 'Tambah Menu');

                if (menu) {
                    ['title', 'code', 'route', 'icon', 'sort_order'].forEach(field => {
                        $form.find(`[name="${field}"]`).val(menu[field] ?? '');
                    });
                    $parent.val(menu.parent_id ?? '');
                    $('#menu-active').prop('checked', menu.is_active);
                }

                modal.show();
            })
            .fail(xhr => App.handleError(xhr));
    }

    $('#btn-add-menu').on('click', () => openForm());

    $('#menus-table').on('click', '.btn-edit', function () {
        openForm(findRow($(this).data('id')));
    });

    $('#menus-table').on('click', '.btn-delete', function () {
        const menu = findRow($(this).data('id'));

        App.confirm(`Hapus menu ${menu.title}? Hak akses role untuk menu ini ikut terhapus.`, 'Ya, hapus').then(confirmed => {
            if (!confirmed) {
                return;
            }

            $.ajax({ url: App.url(`api/menus/${menu.id}`), type: 'DELETE' })
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
            url: App.url(editingId ? `api/menus/${editingId}` : 'api/menus'),
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
