/**
 * Pengaturan hak akses (create/read/update/delete/print/approve) per menu untuk satu role.
 */
'use strict';

$(function () {
    const $page = $('#role-access-page');
    const roleId = $page.data('role-id');
    const canUpdate = $page.data('can-update') === 1;
    const actions = $page.data('actions') || [];
    const endpoint = App.url(`api/roles/${roleId}/access`);

    const $tbody = $('#role-access-table tbody');
    const $save = $('#btn-save-access');

    function render(menus) {
        if (menus.length === 0) {
            $tbody.html(`<tr><td colspan="8" class="text-center text-body-secondary py-6">Belum ada menu</td></tr>`);
            return;
        }

        $tbody.html(menus.map(menu => {
            const indent = menu.depth * 1.5;
            const inactive = menu.is_active ? '' : ' <span class="badge bg-label-secondary ms-1">nonaktif</span>';
            const cells = actions.map(action => `
                <td class="text-center">
                    <input type="checkbox" class="form-check-input check-access" data-action="${action}"
                        aria-label="${action} ${App.escape(menu.title)}"
                        ${menu[`can_${action}`] ? 'checked' : ''} ${canUpdate ? '' : 'disabled'} />
                </td>`).join('');
            const rowToggle = canUpdate
                ? `<td class="text-center"><input type="checkbox" class="form-check-input check-row" aria-label="Semua action ${App.escape(menu.title)}" /></td>`
                : '';

            return `
                <tr data-menu-id="${menu.menu_id}">
                    <td style="padding-left: ${1.25 + indent}rem">
                        ${App.escape(menu.title)}${inactive}
                        <div class="small text-body-secondary">${App.escape(menu.code)}</div>
                    </td>
                    ${cells}
                    ${rowToggle}
                </tr>`;
        }).join(''));

        syncToggles();
    }

    /** Status checkbox "semua" (per baris & per kolom) mengikuti isi tabel. */
    function syncToggles() {
        $tbody.find('tr[data-menu-id]').each(function () {
            const $boxes = $(this).find('.check-access');
            $(this).find('.check-row').prop('checked', $boxes.length > 0 && $boxes.filter(':checked').length === $boxes.length);
        });

        $('.check-column').each(function () {
            const $boxes = $tbody.find(`.check-access[data-action="${$(this).data('action')}"]`);
            $(this).prop('checked', $boxes.length > 0 && $boxes.filter(':checked').length === $boxes.length);
        });
    }

    function load() {
        $.get(endpoint)
            .done(res => {
                render(res.data);
                $save.prop('disabled', false);
            })
            .fail(xhr => App.handleError(xhr));
    }

    $tbody.on('change', '.check-access', syncToggles);

    $tbody.on('change', '.check-row', function () {
        $(this).closest('tr').find('.check-access').prop('checked', this.checked);
        syncToggles();
    });

    $('.check-column').on('change', function () {
        $tbody.find(`.check-access[data-action="${$(this).data('action')}"]`).prop('checked', this.checked);
        syncToggles();
    });

    $save.on('click', function () {
        const access = $tbody.find('tr[data-menu-id]').map(function () {
            const item = { menu_id: $(this).data('menu-id') };

            actions.forEach(action => {
                item[`can_${action}`] = $(this).find(`.check-access[data-action="${action}"]`).is(':checked') ? 1 : 0;
            });

            return item;
        }).get();

        $save.prop('disabled', true);

        $.ajax({ url: endpoint, type: 'PUT', data: { access } })
            .done(res => App.notify('success', res.message))
            .fail(xhr => App.handleError(xhr))
            .always(() => $save.prop('disabled', false));
    });

    load();
});
