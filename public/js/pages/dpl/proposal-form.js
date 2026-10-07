/**
 * Form draft DPL (header + item).
 *
 * Buat: POST api/dpl/proposals, lalu pindah ke halaman detail (nomor DPL dibuat server).
 * Detail: data dimuat dari GET api/dpl/proposals/{id}; bila draft milik user, bisa disimpan via PUT.
 * Item dikelola di browser (tabel + modal) dan dikirim bersama header sebagai JSON.
 */
'use strict';

$(function () {
    const $form = $('#proposal-form');
    const $submit = $form.find('[type="submit"]');
    const proposalId = $form.data('proposal-id') || null;
    const showUrl = $form.data('show-url');
    const canSave = $form.data('can-save') === 1;

    const $itemForm = $('#item-form');
    const itemModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('item-modal'));
    const $itemsBody = $('#items-table tbody');

    const FLAGS = ['is_direct_delivery', 'is_buyer_reward', 'is_bonus_conversion', 'is_support_satoria', 'is_returnable'];
    const TERMS = ['principle_discount_on', 'principle_discount_off', 'distributor_discount_on', 'distributor_discount_off', 'negotiated_price', 'margin', 'logistic_support'];

    /** Item di browser: { id, product_id, distributor_id, ..., labels: {product, distributor, bonus} } */
    let items = [];
    let editingIndex = null;
    let editable = canSave && !proposalId;

    const muted = text => `<span class="text-body-secondary">${text}</span>`;
    const number = value => (value === null || value === undefined || value === '') ? muted('-') : App.escape(String(Number(value)));

    initSelect2('#item-product, #item-distributor, #item-bonus');

    $('#proposal-outlet').wrap('<div class="position-relative"></div>').select2({
        dropdownParent: $('#proposal-outlet').parent(),
        placeholder: 'Cari outlet',
        width: '100%',
        ajax: {
            url: App.url('api/dpl/proposals/outlet-options'),
            delay: 300,
            data: params => ({ q: params.term || '' }),
            processResults: res => ({ results: res.data }),
            error: (xhr, status) => (status === 'abort' ? null : App.handleError(xhr))
        }
    });

    function initSelect2(selector) {
        $(selector).each(function () {
            const $select = $(this);

            $select.wrap('<div class="position-relative"></div>').select2({
                dropdownParent: $select.parent(),
                placeholder: $select.find('option[value=""]').text(),
                allowClear: !$select.prop('required'),
                width: '100%'
            });
        });
    }

    /** Pastikan option ada (mis. distributor yang kini di luar wilayah/nonaktif) lalu pilih. */
    function selectValue($select, value, label) {
        if (value && $select.find(`option[value="${value}"]`).length === 0) {
            $select.append(new Option(label || `#${value}`, value));
        }

        $select.val(value ? String(value) : '').trigger('change');
    }

    function setEditable(state) {
        editable = state;
        $form.find('input, select, textarea').not('[readonly]').prop('disabled', !state);
        $('#proposal-team').prop('disabled', !state || $('#proposal-team option').length === 1);
        $form.find('[data-edit-only]').toggleClass('d-none', !state);
        renderItems();
    }

    function renderItems() {
        if (items.length === 0) {
            $itemsBody.html(`<tr><td colspan="9" class="text-center">${muted('Belum ada item')}</td></tr>`);
            return;
        }

        $itemsBody.html(items.map((item, index) => `
            <tr data-index="${index}">
                <td>${index + 1}</td>
                <td>${App.escape(item.labels.product)}</td>
                <td>${App.escape(item.labels.distributor)}</td>
                <td class="text-end">${number(item.minimum_order)}</td>
                <td class="text-end">${number(item.principle_discount_on)} / ${number(item.principle_discount_off)}</td>
                <td class="text-end">${number(item.distributor_discount_on)} / ${number(item.distributor_discount_off)}</td>
                <td class="text-end">${number(item.negotiated_price)}</td>
                <td>${item.labels.bonus ? App.escape(item.labels.bonus) : muted('-')}</td>
                <td class="text-end text-nowrap">${editable ? `
                    <button type="button" class="btn btn-sm btn-icon btn-text-secondary btn-edit-item" title="Edit"><i class="icon-base bx bx-edit"></i></button>
                    <button type="button" class="btn btn-sm btn-icon btn-text-danger btn-remove-item" title="Hapus"><i class="icon-base bx bx-trash"></i></button>`
                    : `<button type="button" class="btn btn-sm btn-icon btn-text-secondary btn-edit-item" title="Lihat"><i class="icon-base bx bx-show"></i></button>`}
                </td>
            </tr>`).join(''));
    }

    function openItem(index = null) {
        editingIndex = index;
        const item = index === null ? null : items[index];

        $itemForm[0].reset();
        App.clearErrors($itemForm);
        $('#item-modal-title').text(item ? (editable ? `Edit Item #${index + 1}` : `Item #${index + 1}`) : 'Tambah Item');

        selectValue($('#item-product'), item?.product_id, item?.labels.product);
        selectValue($('#item-distributor'), item?.distributor_id, item?.labels.distributor);
        selectValue($('#item-bonus'), item?.bonus_scheme_id, item?.labels.bonus);
        $itemForm.find('[name="minimum_order"]').val(item?.minimum_order ?? '');
        $itemForm.find('[name="remark"]').val(item?.remark ?? '');
        TERMS.forEach(field => $itemForm.find(`[name="${field}"]`).val(item && item[field] !== null ? Number(item[field]) : ''));
        FLAGS.forEach(flag => $itemForm.find(`[name="${flag}"]`).prop('checked', Boolean(item?.[flag])));

        $itemForm.find('input, select, textarea').prop('disabled', !editable);
        $itemForm.find('[type="submit"]').toggleClass('d-none', !editable);
        itemModal.show();
    }

    const valueOrNull = name => {
        const value = $itemForm.find(`[name="${name}"]`).val();
        return value === '' || value === null ? null : value;
    };

    $itemForm.on('submit', function (event) {
        event.preventDefault();

        const errors = {};
        if (!valueOrNull('product_id')) {
            errors.product_id = 'Product wajib dipilih.';
        }
        if (!valueOrNull('distributor_id')) {
            errors.distributor_id = 'Distributor wajib dipilih.';
        }
        if (Object.keys(errors).length) {
            App.showErrors($itemForm, errors);
            return;
        }

        const item = {
            id: editingIndex === null ? null : items[editingIndex].id,
            product_id: Number(valueOrNull('product_id')),
            distributor_id: Number(valueOrNull('distributor_id')),
            bonus_scheme_id: valueOrNull('bonus_scheme_id') ? Number(valueOrNull('bonus_scheme_id')) : null,
            minimum_order: valueOrNull('minimum_order'),
            remark: valueOrNull('remark'),
            labels: {
                product: $('#item-product option:selected').text(),
                distributor: $('#item-distributor option:selected').text(),
                bonus: valueOrNull('bonus_scheme_id') ? $('#item-bonus option:selected').text() : null
            }
        };
        TERMS.forEach(field => { item[field] = valueOrNull(field); });
        FLAGS.forEach(flag => { item[flag] = $itemForm.find(`[name="${flag}"]`).is(':checked'); });

        if (editingIndex === null) {
            items.push(item);
        } else {
            items[editingIndex] = item;
        }

        itemModal.hide();
        renderItems();
    });

    $('#btn-add-item').on('click', () => openItem());

    $itemsBody.on('click', '.btn-edit-item', function () {
        openItem($(this).closest('tr').data('index'));
    });

    $itemsBody.on('click', '.btn-remove-item', function () {
        items.splice($(this).closest('tr').data('index'), 1);
        renderItems();
    });

    function payload() {
        const data = {
            proposal_type: $form.find('[name="proposal_type"]').val(),
            date_offer: $form.find('[name="date_offer"]').val(),
            date_start: $form.find('[name="date_start"]').val(),
            date_end: $form.find('[name="date_end"]').val(),
            is_contract: $('#proposal-contract').is(':checked'),
            description: $form.find('[name="description"]').val() || null,
            items: items.map(({ labels, ...item }) => item)
        };

        if (!proposalId) {
            data.outlet_id = $('#proposal-outlet').val();
            data.team_code = $('#proposal-team').val() || null;
        }

        return data;
    }

    /** Error header ke field; error item ditandai di baris dan diringkas di notifikasi. */
    function showValidationErrors(errors) {
        const headerErrors = {};
        const itemMessages = [];

        $itemsBody.find('tr').removeClass('table-danger');

        Object.entries(errors).forEach(([field, messages]) => {
            const match = field.match(/^items\.(\d+)\./);

            if (match) {
                $itemsBody.find(`tr[data-index="${match[1]}"]`).addClass('table-danger');
                itemMessages.push(`Item #${Number(match[1]) + 1}: ${messages[0]}`);
            } else {
                headerErrors[field] = messages;
            }
        });

        App.showErrors($form, headerErrors);

        if (itemMessages.length) {
            App.notify('error', itemMessages.join(' | '));
        }
    }

    $form.on('submit', function (event) {
        event.preventDefault();
        $submit.prop('disabled', true);

        $.ajax({
            url: App.url(proposalId ? `api/dpl/proposals/${proposalId}` : 'api/dpl/proposals'),
            type: proposalId ? 'PUT' : 'POST',
            contentType: 'application/json',
            data: JSON.stringify(payload())
        })
            .done(res => {
                App.notify('success', res.message);

                if (!proposalId) {
                    window.location.href = `${showUrl}/${res.data.id}`;
                    return;
                }

                fill(res.data);
            })
            .fail(xhr => (xhr.status === 422 && xhr.responseJSON?.errors
                ? showValidationErrors(xhr.responseJSON.errors)
                : App.handleError(xhr)))
            .always(() => $submit.prop('disabled', false));
    });

    function fill(proposal) {
        $('#proposal-status').text(proposal.proposal_status);
        $('#proposal-outlet-text').val(`${proposal.outlet.name} (${proposal.outlet.code})`
            + (proposal.outlet.area ? ` - ${proposal.outlet.area.name}` : ''));
        $('#proposal-team-text').val(proposal.team_code);
        $form.find('[name="proposal_type"]').val(proposal.proposal_type);
        $form.find('[name="date_offer"]').val(proposal.date_offer);
        $form.find('[name="date_start"]').val(proposal.date_start);
        $form.find('[name="date_end"]').val(proposal.date_end);
        $('#proposal-contract').prop('checked', proposal.is_contract);
        $form.find('[name="description"]').val(proposal.description || '');

        items = proposal.items.map(item => {
            const result = {
                id: item.id,
                product_id: item.product_id,
                distributor_id: item.distributor_id,
                bonus_scheme_id: item.bonus_scheme_id,
                minimum_order: item.minimum_order,
                remark: item.remark,
                labels: {
                    product: item.product ? `${item.product.name} (${item.product.code})` : `#${item.product_id}`,
                    distributor: item.distributor ? `${item.distributor.name} (${item.distributor.code})` : `#${item.distributor_id}`,
                    bonus: item.bonus_scheme ? item.bonus_scheme.name : null
                }
            };
            TERMS.forEach(field => { result[field] = item.terms ? item.terms[field] : null; });
            FLAGS.forEach(flag => { result[flag] = item[flag]; });

            return result;
        });

        setEditable(canSave && proposal.can_edit);
    }

    if (proposalId) {
        setEditable(false);
        $.get(App.url(`api/dpl/proposals/${proposalId}`))
            .done(res => fill(res.data))
            .fail(xhr => App.handleError(xhr));
    } else {
        const today = new Date().toLocaleDateString('en-CA');
        $form.find('[name="date_offer"]').val(today);
        $form.find('[name="date_start"]').val(today);
        setEditable(editable);
    }
});
