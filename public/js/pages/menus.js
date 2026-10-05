/**
 * Management menu. Data ditampilkan sebagai pohon (urut parent -> anak) dari api/menus.
 */
'use strict';

$(function () {
    const $page = $('#menus-page');
    const can = { update: $page.data('can-update') === 1, delete: $page.data('can-delete') === 1 };

    const $tbody = $('#menus-table tbody');
    const $form = $('#menu-form');
    const $submit = $form.find('[type="submit"]');
    const $parent = $('#menu-parent');
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('menu-modal'));
    let menus = [];
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

    function render() {
        if (menus.length === 0) {
            $tbody.html('<tr><td colspan="6" class="text-center text-body-secondary py-6">Belum ada menu</td></tr>');
            return;
        }

        $tbody.html(menus.map(menu => `
            <tr>
                <td style="padding-left: ${1.25 + menu.depth * 1.5}rem">
                    ${menu.icon ? `<i class="icon-base ${App.escape(menu.icon)} me-2"></i>` : ''}${App.escape(menu.title)}
                </td>
                <td><code>${App.escape(menu.code)}</code></td>
                <td>${menu.route ? App.escape(menu.route) : '<span class="text-body-secondary">grup</span>'}</td>
                <td class="text-center">${menu.sort_order}</td>
                <td class="text-center">
                    ${menu.is_active
                        ? '<span class="badge bg-label-success">Aktif</span>'
                        : '<span class="badge bg-label-secondary">Nonaktif</span>'}
                </td>
                <td class="text-end text-nowrap">${renderActions(menu)}</td>
            </tr>`).join(''));
    }

    function load() {
        $.get(App.url('api/menus'))
            .done(res => {
                menus = res.data;
                render();
            })
            .fail(xhr => App.handleError(xhr));
    }

    /** Opsi parent: semua menu kecuali menu yang sedang diedit beserta turunannya. */
    function fillParentOptions(excludeId = null) {
        const excluded = new Set();

        if (excludeId) {
            excluded.add(excludeId);
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

        $form[0].reset();
        App.clearErrors($form);
        fillParentOptions(editingId);
        $('#menu-modal-title').text(menu ? 'Edit Menu' : 'Tambah Menu');

        if (menu) {
            ['title', 'code', 'route', 'icon', 'sort_order'].forEach(field => {
                $form.find(`[name="${field}"]`).val(menu[field] ?? '');
            });
            $parent.val(menu.parent_id ?? '');
            $('#menu-active').prop('checked', menu.is_active);
        }

        modal.show();
    }

    $('#btn-add-menu').on('click', () => openForm());

    $tbody.on('click', '.btn-edit', function () {
        openForm(menus.find(menu => menu.id === $(this).data('id')));
    });

    $tbody.on('click', '.btn-delete', function () {
        const menu = menus.find(item => item.id === $(this).data('id'));

        App.confirm(`Hapus menu ${menu.title}? Hak akses role untuk menu ini ikut terhapus.`, 'Ya, hapus').then(confirmed => {
            if (!confirmed) {
                return;
            }

            $.ajax({ url: App.url(`api/menus/${menu.id}`), type: 'DELETE' })
                .done(res => {
                    App.notify('success', res.message);
                    load();
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
                load();
            })
            .fail(xhr => App.handleError(xhr, $form))
            .always(() => $submit.prop('disabled', false));
    });

    load();
});
