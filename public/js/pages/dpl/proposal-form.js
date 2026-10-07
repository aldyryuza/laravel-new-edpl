/**
 * Form draft DPL (header + item).
 *
 * Buat: POST api/dpl/proposals, lalu pindah ke halaman detail (nomor DPL dibuat server).
 * Detail: data dimuat dari GET api/dpl/proposals/{id}; bila draft milik user, bisa disimpan via PUT.
 * Item dikelola di browser (tabel + modal) dan dikirim bersama header sebagai JSON.
 *
 * Aturan item (juga ditegakkan server):
 * - HNA = harga product yang berlaku pada Date Start sesuai jenis harga item (GET api/dpl/proposals/pricing),
 *   default PRICE_BARU_2026 (price), bisa diganti PRICE_LAMA_2025 (regular_price);
 *   HNA - Disc On = HNA × (1 − (Principle ON + Distributor ON)%), HNA+PPN - Disc On = HNA × (1 + PPN%) × (1 − ...),
 *   dibulatkan ke rupiah (desimal >= 0,5 ke atas, < 0,5 ke bawah).
 * - Minimum order wajib diisi.
 * - Discount Share Principle OFF diisi -> Buyer reward dicentang.
 * - Direct -> Logistic Fee wajib (boleh 0); Ambil Dari Pabrik -> Logistic Fee 0; keduanya tidak boleh bersamaan.
 * - Satu product hanya sekali per distributor.
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
    const $direct = $('#item-is_direct_delivery');
    const $pickup = $('#item-is_factory_pickup');
    const $logisticFee = $('#item-logistic-fee');
    const COLUMNS = 12;

    const FLAGS = ['is_direct_delivery', 'is_factory_pickup', 'is_buyer_reward', 'is_support_satoria', 'is_returnable'];
    const TERMS = [
        'principle_discount_on', 'principle_discount_off', 'distributor_discount_on', 'distributor_discount_off',
        'margin', 'logistic_fee', 'logistic_support'
    ];
    const $priceType = $('#item-price-type');
    const defaultPriceType = $priceType.find('option').first().val();

    /** Item di browser: { id, product_id, distributor_id, ..., labels: {product, distributor, bonus} } */
    let items = [];
    let editingIndex = null;
    let editable = canSave && !proposalId;
    /** Harga acuan untuk Date Start saat ini: { ppn: number|null, prices: {product_id: {jenis_harga: number|null}} } */
    let pricing = { date: null, ppn: null, prices: {} };

    const muted = text => `<span class="text-body-secondary">${text}</span>`;
    const isBlank = value => value === null || value === undefined || value === '';
    const number = value => (isBlank(value) ? muted('-') : App.escape(String(Number(value))));
    /**
     * Bulatkan ke rupiah: desimal >= 0,5 ke atas, < 0,5 ke bawah (nilai selalu positif).
     * toFixed(6) dulu agar galat float (mis. 2,4999999 dari 2,5) tidak membulatkan ke bawah.
     */
    const rupiah = value => (value === null ? null : Math.round(Number(value.toFixed(6))));
    const formatRupiah = value => (value === null ? '-' : value.toLocaleString('id-ID', { maximumFractionDigits: 0 }));
    const money = value => (value === null ? muted('-') : App.escape(formatRupiah(value)));

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

    // ---- Harga acuan (HNA/PPN) ----

    function loadPricing() {
        const date = $form.find('[name="date_start"]').val();

        if (!date || date === pricing.date) {
            return;
        }

        $.get(App.url('api/dpl/proposals/pricing'), { date })
            .done(res => {
                pricing = {
                    date,
                    ppn: isBlank(res.data.ppn_percent) ? null : Number(res.data.ppn_percent),
                    prices: Object.fromEntries(Object.entries(res.data.prices).map(([id, byType]) => [
                        id,
                        Object.fromEntries(Object.entries(byType).map(([type, price]) => [type, isBlank(price) ? null : Number(price)]))
                    ]))
                };
                renderItems();
                updateItemPrices();
            })
            .fail(xhr => App.handleError(xhr));
    }

    /** @returns {{hnaDisc: number|null, hnaPpnDisc: number|null}} */
    function hnaOf(productId, priceType) {
        const price = pricing.prices[productId]?.[priceType || defaultPriceType];
        return price === undefined ? null : price;
    }

    function prices(productId, priceType, principleOn, distributorOn) {
        const hna = hnaOf(productId, priceType);

        if (hna === null) {
            return { hnaDisc: null, hnaPpnDisc: null };
        }

        const factor = 1 - ((Number(principleOn) || 0) + (Number(distributorOn) || 0)) / 100;

        return {
            hnaDisc: rupiah(hna * factor),
            hnaPpnDisc: pricing.ppn === null ? null : rupiah(hna * (1 + pricing.ppn / 100) * factor)
        };
    }

    function updateItemPrices() {
        const productId = $('#item-product').val();
        const $info = $('#item-price-info');

        if (!productId) {
            $('#item-hna-disc, #item-hna-ppn-disc').val('');
            $info.text('');
            return;
        }

        const priceType = $priceType.val();
        const hna = hnaOf(productId, priceType);
        const result = prices(productId, priceType, valueOrNull('principle_discount_on'), valueOrNull('distributor_discount_on'));

        $('#item-hna-disc').val(formatRupiah(result.hnaDisc));
        $('#item-hna-ppn-disc').val(formatRupiah(result.hnaPpnDisc));

        if (hna === null) {
            $info.text(`Harga ${priceType} belum ada untuk Date Start ${pricing.date || ''}.`);
        } else if (pricing.ppn === null) {
            $info.text('Parameter komersial PPN_PERCENT belum diatur.');
        } else {
            $info.text(`HNA ${hna.toLocaleString('id-ID', { maximumFractionDigits: 4 })}, PPN ${pricing.ppn}%.`);
        }
    }

    $form.on('change', '[name="date_start"]', loadPricing);
    $('#item-product, #item-price-type').on('change', updateItemPrices);
    $itemForm.on('input', '[name="principle_discount_on"], [name="distributor_discount_on"]', updateItemPrices);

    // ---- Aturan otomatis item ----

    $itemForm.on('input', '[name="principle_discount_off"]', function () {
        if (Number($(this).val()) > 0) {
            $('#item-is_buyer_reward').prop('checked', true);
        }
    });

    /** Direct dan Ambil Dari Pabrik saling eksklusif; logistic fee tampil untuk keduanya, 0 untuk pabrik. */
    function applyDeliveryMode(changed = null) {
        if (changed === 'direct' && $direct.is(':checked')) {
            $pickup.prop('checked', false);
        }
        if (changed === 'pickup' && $pickup.is(':checked')) {
            $direct.prop('checked', false);
        }

        const direct = $direct.is(':checked');
        const pickup = $pickup.is(':checked');

        $('#item-logistic-fee-group').toggleClass('d-none', !direct && !pickup);

        if (pickup) {
            $logisticFee.val(0).prop('readonly', true);
        } else {
            $logisticFee.prop('readonly', false);
            if (!direct) {
                $logisticFee.val('');
            }
        }
    }

    $direct.on('change', () => applyDeliveryMode('direct'));
    $pickup.on('change', () => applyDeliveryMode('pickup'));

    // ---- Tabel & modal item ----

    function setEditable(state) {
        editable = state;
        $form.find('input, select, textarea').not('[readonly]').prop('disabled', !state);
        $('#proposal-team').prop('disabled', !state || $('#proposal-team option').length === 1);
        $form.find('[data-edit-only]').toggleClass('d-none', !state);
        renderItems();
    }

    function deliveryLabel(item) {
        if (item.is_direct_delivery) {
            return `Direct (fee ${number(item.logistic_fee)})`;
        }

        return item.is_factory_pickup ? 'Ambil Dari Pabrik' : muted('Distributor');
    }

    function renderItems() {
        if (items.length === 0) {
            $itemsBody.html(`<tr><td colspan="${COLUMNS}" class="text-center">${muted('Belum ada item')}</td></tr>`);
            return;
        }

        $itemsBody.html(items.map((item, index) => {
            const result = prices(item.product_id, item.price_type, item.principle_discount_on, item.distributor_discount_on);

            return `
            <tr data-index="${index}">
                <td>${index + 1}</td>
                <td>${App.escape(item.labels.product)}</td>
                <td>${App.escape(item.labels.distributor)}</td>
                <td class="text-end">${number(item.minimum_order)}</td>
                <td class="text-end">${number(item.principle_discount_on)} / ${number(item.principle_discount_off)}</td>
                <td class="text-end">${number(item.distributor_discount_on)} / ${number(item.distributor_discount_off)}</td>
                <td>${App.escape(item.price_type || defaultPriceType)}</td>
                <td class="text-end">${money(result.hnaDisc)}</td>
                <td class="text-end">${money(result.hnaPpnDisc)}</td>
                <td>${deliveryLabel(item)}</td>
                <td>${item.labels.bonus ? App.escape(item.labels.bonus) : muted('-')}</td>
                <td class="text-end text-nowrap">${editable ? `
                    <button type="button" class="btn btn-sm btn-icon btn-text-secondary btn-edit-item" title="Edit"><i class="icon-base bx bx-edit"></i></button>
                    <button type="button" class="btn btn-sm btn-icon btn-text-danger btn-remove-item" title="Hapus"><i class="icon-base bx bx-trash"></i></button>`
                    : `<button type="button" class="btn btn-sm btn-icon btn-text-secondary btn-edit-item" title="Lihat"><i class="icon-base bx bx-show"></i></button>`}
                </td>
            </tr>`;
        }).join(''));
    }

    function openItem(index = null) {
        editingIndex = index;
        const item = index === null ? null : items[index];

        $itemForm[0].reset();
        App.clearErrors($itemForm);
        $('#item-modal-title').text(item ? (editable ? `Edit Item #${index + 1}` : `Item #${index + 1}`) : 'Tambah Item');

        selectValue($('#item-distributor'), item?.distributor_id, item?.labels.distributor);
        selectValue($('#item-bonus'), item?.bonus_scheme_id, item?.labels.bonus);
        $itemForm.find('[name="minimum_order"]').val(item?.minimum_order ?? '');
        $itemForm.find('[name="remark"]').val(item?.remark ?? '');
        TERMS.forEach(field => $itemForm.find(`[name="${field}"]`).val(item && !isBlank(item[field]) ? Number(item[field]) : ''));
        FLAGS.forEach(flag => $itemForm.find(`[name="${flag}"]`).prop('checked', Boolean(item?.[flag])));
        $priceType.val(item?.price_type || defaultPriceType);
        selectValue($('#item-product'), item?.product_id, item?.labels.product);
        applyDeliveryMode();

        $itemForm.find('input, select, textarea').not('[readonly]').prop('disabled', !editable);
        $itemForm.find('[type="submit"]').toggleClass('d-none', !editable);
        itemModal.show();
    }

    function valueOrNull(name) {
        const value = $itemForm.find(`[name="${name}"]`).val();
        return isBlank(value) ? null : value;
    }

    $itemForm.on('submit', function (event) {
        event.preventDefault();

        const productId = Number(valueOrNull('product_id'));
        const distributorId = Number(valueOrNull('distributor_id'));
        const errors = {};

        if (!productId) {
            errors.product_id = 'Product wajib dipilih.';
        }
        if (!distributorId) {
            errors.distributor_id = 'Distributor wajib dipilih.';
        }
        if (productId && distributorId && items.some((other, index) => index !== editingIndex
            && other.product_id === productId && other.distributor_id === distributorId)) {
            errors.product_id = 'Product ini sudah ada untuk distributor yang sama.';
        }
        if (isBlank(valueOrNull('minimum_order'))) {
            errors.minimum_order = 'Minimum order wajib diisi.';
        }
        if ($direct.is(':checked') && isBlank($logisticFee.val())) {
            errors.logistic_fee = 'Logistic fee wajib diisi (boleh 0) untuk item Direct.';
        }
        if (Object.keys(errors).length) {
            App.showErrors($itemForm, errors);
            return;
        }

        const item = {
            id: editingIndex === null ? null : items[editingIndex].id,
            product_id: productId,
            distributor_id: distributorId,
            bonus_scheme_id: valueOrNull('bonus_scheme_id') ? Number(valueOrNull('bonus_scheme_id')) : null,
            minimum_order: valueOrNull('minimum_order'),
            price_type: $priceType.val(),
            remark: valueOrNull('remark'),
            labels: {
                product: $('#item-product option:selected').text(),
                distributor: $('#item-distributor option:selected').text(),
                bonus: valueOrNull('bonus_scheme_id') ? $('#item-bonus option:selected').text() : null
            }
        };
        TERMS.forEach(field => { item[field] = valueOrNull(field); });
        FLAGS.forEach(flag => { item[flag] = $itemForm.find(`[name="${flag}"]`).is(':checked'); });

        if (!item.is_direct_delivery && !item.is_factory_pickup) {
            item.logistic_fee = null;
        }

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

    // ---- Simpan ----

    function payload() {
        const data = {
            proposal_type: $form.find('[name="proposal_type"]').val(),
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
        $('#proposal-offer').val(proposal.date_offer);
        $form.find('[name="proposal_type"]').val(proposal.proposal_type);
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
                price_type: item.terms?.price_type || defaultPriceType,
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
        loadPricing();
    }

    if (proposalId) {
        setEditable(false);
        $.get(App.url(`api/dpl/proposals/${proposalId}`))
            .done(res => fill(res.data))
            .fail(xhr => App.handleError(xhr));
    } else {
        $form.find('[name="date_start"]').val($('#proposal-offer').val());
        setEditable(editable);
        loadPricing();
    }
});
