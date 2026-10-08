/**
 * Form draft DPL (header + item).
 *
 * Buat: POST api/dpl/proposals, lalu pindah ke halaman detail (nomor DPL dibuat server).
 * Header: Bed dari master outlet; "25% Bed" = Bed × rasio × hari (read-only), manual bila bed 0/kosong.
 * Detail: data dimuat dari GET api/dpl/proposals/{id}; bila draft milik user, bisa disimpan via PUT.
 * Item dikelola di browser (tabel + modal) dan dikirim bersama header sebagai JSON.
 * Ajukan: POST api/dpl/proposals/{id}/submit (draft milik user dengan item tersimpan; perubahan belum tersimpan
 * disimpan dulu). Panel approval (DPL bukan DRAFT): GET api/dpl/proposals/{id}/approval -> matriks item × step,
 * approve/reject terpilih/semua (POST .../approve|reject, alasan wajib untuk reject), dan riwayat approval.
 *
 * Aturan item (juga ditegakkan server):
 * - Pilih Product (radio): Satuan (default) = satu product dicari, HNA langsung tampil di modal;
 *   Group (1L/500ml/250ml/100ml/25ml dari volume, IV CAT WING/IV CAT PEN dari master Product) = semua product di
 *   group otomatis terpilih (Pilih semua/Kosongkan), HNA tampil di tabel setelah disimpan. Satu product = satu item;
 *   margin & logistic fee tiap product dihitung server saat disimpan (product_ids[], tanpa margin khusus outlet)
 *   dengan distributor & syarat yang sama. Edit item hanya mengubah syarat, bukan product.
 * - HNA = harga product yang berlaku pada Date Start sesuai jenis harga item (GET api/dpl/proposals/pricing),
 *   default PRICE_BARU_2026 (price), bisa diganti PRICE_LAMA_2025 (regular_price, hanya product 500 ml).
 *   HNA - Disc On = HNA × (1 − (Principle ON + Distributor ON)%), HNA+PPN - Disc On = HNA × (1 + PPN%) × (1 − ...),
 *   dibulatkan ke rupiah (desimal >= 0,5 ke atas, < 0,5 ke bawah).
 * - Mode Satuan: HNA - Disc On bisa diketik; Discount Share Principle ON dihitung balik
 *   = (1 − target / HNA) × 100 − Distributor ON (2 desimal, tidak boleh negatif).
 * - Discount (Principle/Distributor ON/OFF) ditampilkan 2 angka di belakang koma; kosong = 0 (default 0.00).
 * - Total Calculator dihitung server (POST api/dpl/proposals/calculate, sama dengan yang disimpan);
 *   item di atas batas team ditandai merah ("Total discount is more than X%").
 * - Margin & Logistic Fee terisi otomatis dari server (GET api/dpl/proposals/item-defaults) saat product, distributor,
 *   Direct, atau Ambil Dari Pabrik berubah; tetap bisa diubah user.
 * - Minimum order per bulan wajib diisi.
 * - Discount Share Principle OFF diisi -> Buyer reward dicentang.
 * - Logistic Fee selalu ada (otomatis, read-only); Direct -> boleh diganti manual dan wajib (boleh 0);
 *   Ambil Dari Pabrik -> 0; Direct dan Ambil Dari Pabrik tidak boleh bersamaan.
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
    const $group = $('#item-group');
    const $products = $('#item-product');
    const $single = $('#item-product-single');
    const $direct = $('#item-is_direct_delivery');
    const $pickup = $('#item-is_factory_pickup');
    const $logisticFee = $('#item-logistic-fee');
    const COLUMNS = 15;

    const FLAGS = ['is_direct_delivery', 'is_factory_pickup', 'is_buyer_reward', 'is_support_satoria', 'is_returnable'];
    const TERMS = [
        'principle_discount_on', 'principle_discount_off', 'distributor_discount_on', 'distributor_discount_off',
        'margin', 'logistic_fee', 'logistic_support'
    ];
    const $priceType = $('#item-price-type');
    const defaultPriceType = $priceType.find('option').first().val();

    /** Product per group: [{id, label, group, volume}] */
    const PRODUCTS = JSON.parse(document.getElementById('dpl-products').textContent);
    const productById = Object.fromEntries(PRODUCTS.map(product => [product.id, product]));

    /** Item di browser: { id, product_id, distributor_id, ..., labels: {product, distributor, bonus} } */
    let items = [];
    let editingIndex = null;
    let editable = canSave && !proposalId;
    /** Harga acuan untuk Date Start saat ini: { ppn: number|null, prices: {product_id: {jenis_harga: number|null}} } */
    let pricing = { date: null, ppn: null, prices: {} };

    const muted = text => `<span class="text-body-secondary">${text}</span>`;
    const isBlank = value => value === null || value === undefined || value === '';
    const number = value => (isBlank(value) ? muted('-') : App.escape(String(Number(value))));
    /** Discount: 2 angka di belakang koma (format Indonesia, mis. 10,50). */
    const DISCOUNTS = ['principle_discount_on', 'principle_discount_off', 'distributor_discount_on', 'distributor_discount_off', 'logistic_support'];
    const discount = value => (isBlank(value) ? muted('-')
        : App.escape(Number(value).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })));
    /**
     * Bulatkan ke rupiah: desimal >= 0,5 ke atas, < 0,5 ke bawah (nilai selalu positif).
     * toFixed(6) dulu agar galat float (mis. 2,4999999 dari 2,5) tidak membulatkan ke bawah.
     */
    const rupiah = value => (value === null ? null : Math.round(Number(value.toFixed(6))));
    const formatRupiah = value => (value === null ? '-' : value.toLocaleString('id-ID', { maximumFractionDigits: 0 }));
    const money = value => (value === null ? muted('-') : App.escape(formatRupiah(value)));

    initSelect2('#item-distributor, #item-bonus');

    PRODUCTS.forEach(product => $single.append(new Option(product.label, product.id)));
    $single.wrap('<div class="position-relative"></div>').select2({
        dropdownParent: $single.parent(),
        placeholder: 'Cari product',
        width: '100%'
    });

    $products.wrap('<div class="position-relative"></div>').select2({
        dropdownParent: $products.parent(),
        placeholder: 'Pilih group dulu',
        closeOnSelect: false,
        width: '100%'
    });

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

    // ---- Bed & "25% Bed" (order minimum header = Bed × rasio × hari; manual bila bed 0/kosong) ----

    const $bedOrder = $('#proposal-bed-order');
    const bedOrderRatio = Number($('#proposal-bed-order-group').data('ratio')) || null;
    const bedOrderDays = Number($('#proposal-bed-order-group').data('days')) || null;

    /** @param {number|null} bed  @param {string|number|null} savedValue nilai tersimpan (dipakai bila bed kosong) */
    function applyBed(bed, savedValue = null) {
        const hasBed = Number(bed) > 0 && bedOrderRatio !== null && bedOrderDays !== null;

        $('#proposal-bed').val(isBlank(bed) ? '' : bed);

        if (hasBed) {
            $bedOrder.val((Math.round(Number(bed) * bedOrderRatio * bedOrderDays * 100) / 100).toFixed(2));
        } else if (savedValue !== undefined) {
            $bedOrder.val(isBlank(savedValue) ? '' : Number(savedValue));
        }

        $bedOrder.prop('readonly', hasBed);
        $('#proposal-bed-order-info').text(hasBed
            ? `Bed × ${bedOrderRatio} × ${bedOrderDays}.`
            : 'Bed outlet kosong/0: isi manual.');
    }

    $('#proposal-outlet')
        .on('select2:select', event => applyBed(event.params.data.bed_count ?? null, ''))
        .on('select2:clear', () => applyBed(null, ''));

    // ---- Harga acuan (HNA/PPN), dipakai tabel item ----

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

    function hnaOf(productId, priceType) {
        const price = pricing.prices[productId]?.[priceType || defaultPriceType];
        return price === undefined ? null : price;
    }

    /** @returns {{hnaDisc: number|null, hnaPpnDisc: number|null}} */
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

    $form.on('change', '[name="date_start"]', loadPricing);

    // ---- Group & product ----

    const productMode = () => $itemForm.find('[name="product_mode"]:checked').val();

    /** Product terpilih sesuai mode (Satuan: satu, Group: banyak). */
    function selectedProductIds() {
        if (productMode() === 'single') {
            return $single.val() ? [Number($single.val())] : [];
        }

        return ($products.val() || []).map(Number);
    }

    function applyProductMode() {
        const mode = productMode();

        $itemForm.find('[data-product-mode]').each(function () {
            $(this).toggleClass('d-none', $(this).data('product-mode') !== mode);
        });
        applyPriceTypeVolumes();
        updateItemPrices();

        // Mode Group: margin tiap product dihitung server saat item disimpan.
        const group = mode === 'group';
        $itemForm.find('[name="margin"]').prop('readonly', group);
        if (group) {
            $itemForm.find('[name="margin"]').val('');
            $('#item-margin-source').text('Mode Group: margin dihitung per product saat item disimpan.');
        }
    }

    /** Isi pilihan product dengan product di group; semua otomatis terpilih. */
    function setProductOptions(group) {
        const options = PRODUCTS.filter(product => product.group === group);

        $products.empty();
        options.forEach(product => $products.append(new Option(product.label, product.id, true, true)));
        $products.trigger('change');
    }

    /**
     * Mode Satuan: tampilkan HNA - Disc On dan HNA+PPN - Disc On product terpilih.
     * keepHnaDisc = jangan timpa HNA - Disc On (sedang diketik user).
     */
    function updateItemPrices(keepHnaDisc = false) {
        const $info = $('#item-price-info');
        const productId = productMode() === 'single' ? $single.val() : null;

        if (!productId) {
            $('#item-hna-disc, #item-hna-ppn-disc').val('');
            $info.text('');
            return;
        }

        const priceType = $priceType.val();
        const hna = hnaOf(productId, priceType);
        const result = prices(productId, priceType, valueOrNull('principle_discount_on'), valueOrNull('distributor_discount_on'));

        if (keepHnaDisc !== true) {
            $('#item-hna-disc').val(formatRupiah(result.hnaDisc));
            $('#item-hna-disc-info').removeClass('text-danger').text('Bisa diubah: Discount Share Principle ON menyesuaikan.');
        }
        $('#item-hna-ppn-disc').val(formatRupiah(result.hnaPpnDisc));

        if (hna === null) {
            $info.text(`Harga ${priceType} belum ada untuk Date Start ${pricing.date || ''}.`);
        } else if (pricing.ppn === null) {
            $info.text('Parameter komersial PPN_PERCENT belum diatur.');
        } else {
            $info.text(`HNA ${hna.toLocaleString('id-ID', { maximumFractionDigits: 4 })}, PPN ${pricing.ppn}%.`);
        }
    }

    /** Nonaktifkan jenis harga yang tidak berlaku untuk semua product terpilih (mis. PRICE_LAMA_2025 hanya 500 ml). */
    function applyPriceTypeVolumes() {
        const volumes = selectedProductIds().map(id => productById[id]?.volume ?? null);

        $priceType.find('option').each(function () {
            const allowedVolumes = $(this).data('volumes');
            const allowed = !Array.isArray(allowedVolumes)
                || (volumes.length > 0 && volumes.every(volume => volume !== null && allowedVolumes.map(Number).includes(Number(volume))));

            $(this).prop('disabled', !allowed);
        });

        if ($priceType.find('option:selected').prop('disabled')) {
            $priceType.val(defaultPriceType);
        }
    }

    $group.on('change', () => setProductOptions($group.val()));

    /** Pilih semua / kosongkan product di group (untuk mengajukan satu product: Kosongkan lalu pilih). */
    $('#btn-select-all-products').on('click', () => {
        $products.val($products.find('option').toArray().map(option => option.value)).trigger('change');
    });
    $('#btn-clear-products').on('click', () => {
        $products.val([]).trigger('change');
        $products.select2('open');
    });
    $products.on('change', applyPriceTypeVolumes);
    $single.on('change', () => {
        applyPriceTypeVolumes();
        updateItemPrices();
    });
    $itemForm.on('change', '[name="product_mode"]', applyProductMode);
    $priceType.on('change', () => updateItemPrices());

    // Discount yang dikosongkan kembali ke 0.00.
    $itemForm.on('change', DISCOUNTS.map(field => `[name="${field}"]`).join(', '), function () {
        if (isBlank($(this).val())) {
            $(this).val('0.00');
            updateItemPrices();
        }
    });
    $itemForm.on('input', '[name="principle_discount_on"], [name="distributor_discount_on"]', () => updateItemPrices());

    /** Angka format Indonesia (85.000 / 85000 / 85000,5) -> number; null bila kosong/tidak valid. */
    function parseRupiah(text) {
        const normalized = String(text).trim().replace(/\./g, '').replace(',', '.');
        return normalized === '' || Number.isNaN(Number(normalized)) ? null : Number(normalized);
    }

    /**
     * HNA - Disc On diubah -> Discount Share Principle ON dihitung balik:
     * Principle ON = (1 − target / HNA) × 100 − Distributor ON, dibulatkan 2 desimal.
     */
    $('#item-hna-disc').on('input', function () {
        const $info = $('#item-hna-disc-info');
        const productId = $single.val();
        const hna = productId ? hnaOf(productId, $priceType.val()) : null;
        const target = parseRupiah($(this).val());

        $info.removeClass('text-danger').text('Bisa diubah: Discount Share Principle ON menyesuaikan.');

        if (hna === null || hna === 0 || target === null) {
            return;
        }

        const principleOn = Math.round(((1 - target / hna) * 100 - (Number(valueOrNull('distributor_discount_on')) || 0)) * 100) / 100;

        if (principleOn < 0) {
            $info.addClass('text-danger').text('Melebihi harga setelah Discount Share Distributor ON; Principle ON tidak bisa negatif.');
            return;
        }

        $itemForm.find('[name="principle_discount_on"]').val(principleOn.toFixed(2));
        updateItemPrices(true);
    });

    // Setelah selesai mengetik, tampilkan ulang hasil hitung (format rupiah, sesuai diskon yang tersimpan).
    $('#item-hna-disc').on('change', () => updateItemPrices());

    // ---- Aturan otomatis item ----

    $itemForm.on('input', '[name="principle_discount_off"]', function () {
        if (Number($(this).val()) > 0) {
            $('#item-is_buyer_reward').prop('checked', true);
        }
    });

    /** Direct dan Ambil Dari Pabrik saling eksklusif; logistic fee hanya bisa diketik saat Direct, 0 untuk pabrik. */
    function applyDeliveryMode(changed = null) {
        if (changed === 'direct' && $direct.is(':checked')) {
            $pickup.prop('checked', false);
        }
        if (changed === 'pickup' && $pickup.is(':checked')) {
            $direct.prop('checked', false);
        }

        const direct = $direct.is(':checked');
        const pickup = $pickup.is(':checked');

        // Logistic fee selalu ada: otomatis (read-only), Direct boleh diganti manual, Ambil Dari Pabrik = 0.
        if (pickup) {
            $logisticFee.val(0);
        }
        $logisticFee.prop('readonly', !direct || pickup);
    }

    $direct.on('change', () => applyDeliveryMode('direct'));
    $pickup.on('change', () => applyDeliveryMode('pickup'));


    // ---- Kalkulator (dihitung server: POST api/dpl/proposals/calculate) ----

    const CALCULATOR_FIELDS = [...TERMS, 'bonus_scheme_id', 'is_support_satoria', 'is_direct_delivery', 'is_factory_pickup'];
    /** Hasil kalkulator per item (index sama dengan `items`). */
    let calculations = [];
    let teamCode = null;
    let tableRequest = null;
    let modalRequest = null;
    let modalTimer = null;

    const currentTeam = () => teamCode || $('#proposal-team').val() || null;
    // product_id ikut dikirim: batas kalkulator bisa khusus per product.
    const calculatorInput = item => ({
        ...Object.fromEntries(CALCULATOR_FIELDS.map(field => [field, item[field] ?? null])),
        product_id: item.product_id ?? null
    });

    function calculate(itemsToCalculate) {
        return $.ajax({
            url: App.url('api/dpl/proposals/calculate'),
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ team_code: currentTeam(), items: itemsToCalculate.map(calculatorInput) })
        });
    }

    function limitText(result) {
        return result.limit === null ? 'Batas kalkulator team belum diatur.' : `Batas ${Number(result.limit)}%.`;
    }

    /** Hitung ulang Total Diskon semua item di tabel. */
    function recalcItems() {
        tableRequest?.abort();
        calculations = [];

        if (items.length === 0 || !currentTeam()) {
            renderItems();
            return;
        }

        tableRequest = calculate(items)
            .done(res => {
                calculations = res.data;
                renderItems();
            })
            .fail((xhr, status) => (status === 'abort' ? null : App.handleError(xhr)));
    }

    function calculatorCell(index) {
        const result = calculations[index];

        if (!result) {
            return muted('-');
        }

        const total = discount(result.total_discount);

        return result.exceeds_limit
            ? `<span class="badge bg-label-danger" title="Total discount is more than ${App.escape(Number(result.limit))}%">${total}%</span>`
            : `${total}%`;
    }

    /** Total Diskon item yang sedang diisi di modal (ditunda 300 ms selama mengetik). */
    function updateItemCalculator() {
        clearTimeout(modalTimer);
        modalTimer = setTimeout(() => {
            const $total = $('#item-calculator-total');
            const $box = $('#item-calculator');
            const $info = $('#item-calculator-info');

            if (productMode() === 'group' && editingIndex === null) {
                $total.text('-');
                $box.removeClass('alert-danger').addClass('alert-secondary');
                $info.removeClass('text-danger').text('Mode Group: Total Calculator dihitung per item di tabel setelah disimpan.');
                return;
            }

            if (!currentTeam()) {
                $total.text('-');
                $info.text('Pilih team dulu untuk menghitung.');
                return;
            }

            const item = {};
            CALCULATOR_FIELDS.forEach(field => {
                const $input = $itemForm.find(`[name="${field}"]`);
                item[field] = $input.is(':checkbox') ? $input.is(':checked') : valueOrNull(field);
            });
            item.product_id = selectedProductIds()[0] ?? null;

            modalRequest?.abort();
            modalRequest = calculate([item])
                .done(res => {
                    const result = res.data[0];

                    $total.text(`${Number(result.total_discount).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}%`);
                    $box.toggleClass('alert-danger', result.exceeds_limit).toggleClass('alert-secondary', !result.exceeds_limit);
                    $info.toggleClass('text-danger', result.exceeds_limit).text(result.exceeds_limit
                        ? `Total discount is more than ${Number(result.limit)}%: item ini akan ditolak otomatis.`
                        : limitText(result));
                })
                .fail((xhr, status) => (status === 'abort' ? null : App.handleError(xhr)));
        }, 300);
    }

    $itemForm.on('input change', CALCULATOR_FIELDS.map(field => `[name="${field}"]`).join(', '), updateItemCalculator);
    $('#proposal-team').on('change', () => {
        recalcItems();
        updateItemCalculator();
    });

    // ---- Nilai awal Margin & Logistic Fee (GET api/dpl/proposals/item-defaults) ----

    /** Diisi true selama modal dibuka supaya nilai item tersimpan tidak tertimpa. */
    let loadingItem = false;
    let defaultsRequest = null;
    let outletId = null;

    function loadItemDefaults() {
        const groupMode = productMode() === 'group';
        const productId = selectedProductIds()[0];
        const distributorId = valueOrNull('distributor_id');

        if (loadingItem || !editable || !productId || !distributorId || !currentTeam()) {
            return;
        }

        defaultsRequest?.abort();
        defaultsRequest = $.get(App.url('api/dpl/proposals/item-defaults'), {
            product_id: productId,
            distributor_id: distributorId,
            outlet_id: outletId || $('#proposal-outlet').val() || '',
            team_code: currentTeam(),
            is_direct_delivery: $direct.is(':checked') ? 1 : 0,
            is_factory_pickup: $pickup.is(':checked') ? 1 : 0,
            group_mode: groupMode ? 1 : 0
        })
            .done(res => {
                const result = res.data;

                if (groupMode) {
                    $itemForm.find('[name="margin"]').val('');
                    $('#item-margin-source').text('Mode Group: margin dihitung per product saat item disimpan.');
                } else {
                    $itemForm.find('[name="margin"]').val(isBlank(result.margin) ? '' : Number(result.margin));
                    $('#item-margin-source').text(`Otomatis: ${result.margin_source}${isBlank(result.margin) ? ' (belum diatur)' : ''}. Bisa diubah.`);
                }

                if (!$pickup.is(':checked')) {
                    $logisticFee.val(isBlank(result.logistic_fee) ? '' : Number(result.logistic_fee));
                }
                $('#item-logistic-source').text(`Otomatis: ${result.logistic_source}${isBlank(result.logistic_fee) ? ' (belum diatur)' : ''}.`
                    + ($direct.is(':checked') ? ' Bisa diubah karena Direct.' : ''));

                updateItemCalculator();
            })
            .fail((xhr, status) => (status === 'abort' ? null : App.handleError(xhr)));
    }

    $single.add($products).add('#item-distributor').on('change', loadItemDefaults);
    $single.add($products).on('change', updateItemCalculator);
    $direct.add($pickup).on('change', loadItemDefaults);
    $itemForm.on('change', '[name="product_mode"]', loadItemDefaults);

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
            return 'Direct';
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
                <td class="text-end">${discount(item.principle_discount_on)} / ${discount(item.principle_discount_off)}</td>
                <td class="text-end">${discount(item.distributor_discount_on)} / ${discount(item.distributor_discount_off)}</td>
                <td>${App.escape(item.price_type || defaultPriceType)}</td>
                <td class="text-end">${money(result.hnaDisc)}</td>
                <td class="text-end">${money(result.hnaPpnDisc)}</td>
                <td class="text-end">${isBlank(item.margin) ? muted('-') : `${discount(item.margin)}%`}</td>
                <td class="text-end">${isBlank(item.logistic_fee) ? muted('-') : `${discount(item.logistic_fee)}%`}</td>
                <td class="text-end">${calculatorCell(index)}</td>
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

        loadingItem = true;
        $itemForm[0].reset();
        App.clearErrors($itemForm);
        $('#item-margin-source, #item-logistic-source').text('');
        $('#item-modal-title').text(item ? (editable ? `Edit Item #${index + 1}` : `Item #${index + 1}`) : 'Tambah Item');

        selectValue($('#item-distributor'), item?.distributor_id, item?.labels.distributor);
        selectValue($('#item-bonus'), item?.bonus_scheme_id, item?.labels.bonus);
        $itemForm.find('[name="minimum_order"]').val(item?.minimum_order ?? '');
        $itemForm.find('[name="remark"]').val(item?.remark ?? '');
        // Discount kosong = 0 (default 0.00); field lain kosong tetap kosong.
        TERMS.forEach(field => $itemForm.find(`[name="${field}"]`).val(DISCOUNTS.includes(field)
            ? Number(item?.[field] || 0).toFixed(2)
            : (item && !isBlank(item[field]) ? Number(item[field]) : '')));
        FLAGS.forEach(flag => $itemForm.find(`[name="${flag}"]`).prop('checked', Boolean(item?.[flag])));
        $priceType.val(item?.price_type || defaultPriceType);

        // Default mode Satuan; item tersimpan selalu tampil sebagai satuan dan product-nya tetap
        // (hapus & tambah ulang untuk ganti product).
        $('#item-mode-single').prop('checked', true);
        $group.val('');
        $products.empty().trigger('change');
        selectValue($single, item?.product_id, item?.labels.product);
        applyProductMode();
        applyDeliveryMode();
        $('#item-calculator-total').text('-');
        updateItemCalculator();

        $itemForm.find('input, select, textarea').not('[readonly]').prop('disabled', !editable);
        $single.add($itemForm.find('[name="product_mode"]')).prop('disabled', !editable || item !== null);
        $('#item-mode-group').toggleClass('d-none', item !== null);
        $itemForm.find('[type="submit"]').toggleClass('d-none', !editable);
        loadingItem = false;
        itemModal.show();
    }

    function valueOrNull(name) {
        const value = $itemForm.find(`[name="${name}"]`).val();
        return isBlank(value) ? null : value;
    }

    $itemForm.on('submit', function (event) {
        event.preventDefault();

        const productIds = selectedProductIds();
        const groupMode = productMode() === 'group';
        const productField = groupMode ? 'product_id' : 'product_single';
        const distributorId = Number(valueOrNull('distributor_id'));
        const errors = {};

        if (editingIndex === null && groupMode && !$group.val()) {
            errors.product_group = 'Group product wajib dipilih.';
        }
        if (productIds.length === 0) {
            errors[productField] = groupMode ? 'Pilih minimal satu product.' : 'Product wajib dipilih.';
        }
        if (!distributorId) {
            errors.distributor_id = 'Distributor wajib dipilih.';
        }

        const duplicates = productIds.filter(productId => items.some((other, index) => index !== editingIndex
            && other.product_id === productId && other.distributor_id === distributorId));

        if (distributorId && duplicates.length) {
            const names = duplicates.map(id => productById[id]?.label || `#${id}`).join(', ');
            errors[productField] = `Sudah ada untuk distributor yang sama: ${names}.`;
        }
        if (isBlank(valueOrNull('minimum_order'))) {
            errors.minimum_order = 'Minimum order per bulan wajib diisi.';
        }
        if ($direct.is(':checked') && isBlank($logisticFee.val())) {
            errors.logistic_fee = 'Logistic fee wajib diisi (boleh 0) untuk item Direct.';
        }
        if (Object.keys(errors).length) {
            App.showErrors($itemForm, errors);
            return;
        }

        // Syarat yang sama untuk semua product terpilih.
        const shared = {
            distributor_id: distributorId,
            bonus_scheme_id: valueOrNull('bonus_scheme_id') ? Number(valueOrNull('bonus_scheme_id')) : null,
            minimum_order: valueOrNull('minimum_order'),
            price_type: $priceType.val(),
            remark: valueOrNull('remark')
        };
        TERMS.forEach(field => { shared[field] = DISCOUNTS.includes(field) ? (valueOrNull(field) ?? '0') : valueOrNull(field); });
        FLAGS.forEach(flag => { shared[flag] = $itemForm.find(`[name="${flag}"]`).is(':checked'); });

        const labels = productId => ({
            product: productById[productId]?.label || $single.find(`option[value="${productId}"]`).text() || `#${productId}`,
            distributor: $('#item-distributor option:selected').text(),
            bonus: shared.bonus_scheme_id ? $('#item-bonus option:selected').text() : null
        });

        if (editingIndex !== null) {
            const current = items[editingIndex];
            items[editingIndex] = { id: current.id, product_id: current.product_id, ...shared, labels: labels(current.product_id) };
            itemModal.hide();
            recalcItems();
            return;
        }

        if (productMode() !== 'group') {
            productIds.forEach(productId => items.push({ id: null, product_id: productId, ...shared, labels: labels(productId) }));
            itemModal.hide();
            recalcItems();
            return;
        }

        // Mode Group: margin & logistic fee dihitung per product di server (legacy getProductPrice).
        // Direct: logistic fee di form (boleh diubah manual) berlaku untuk semua product.
        const $submitItem = $itemForm.find('[type="submit"]').prop('disabled', true);

        $.get(App.url('api/dpl/proposals/item-defaults'), {
            product_ids: productIds,
            distributor_id: distributorId,
            outlet_id: outletId || $('#proposal-outlet').val() || '',
            team_code: currentTeam(),
            is_direct_delivery: shared.is_direct_delivery ? 1 : 0,
            is_factory_pickup: shared.is_factory_pickup ? 1 : 0,
            group_mode: 1
        })
            .done(res => {
                productIds.forEach(productId => {
                    const result = res.data[productId] || {};
                    const logisticFee = shared.is_factory_pickup ? '0'
                        : (shared.is_direct_delivery ? shared.logistic_fee : (result.logistic_fee ?? null));

                    items.push({
                        id: null,
                        product_id: productId,
                        ...shared,
                        margin: result.margin ?? null,
                        logistic_fee: logisticFee,
                        labels: labels(productId)
                    });
                });
                itemModal.hide();
                recalcItems();
            })
            .fail(xhr => App.handleError(xhr))
            .always(() => $submitItem.prop('disabled', false));
    });

    $('#btn-add-item').on('click', () => openItem());

    $itemsBody.on('click', '.btn-edit-item', function () {
        openItem($(this).closest('tr').data('index'));
    });

    $itemsBody.on('click', '.btn-remove-item', function () {
        items.splice($(this).closest('tr').data('index'), 1);
        recalcItems();
    });

    // ---- Simpan ----

    function payload() {
        const data = {
            proposal_type: $form.find('[name="proposal_type"]').val(),
            date_start: $form.find('[name="date_start"]').val(),
            date_end: $form.find('[name="date_end"]').val(),
            is_contract: $('#proposal-contract').is(':checked'),
            bed_order_minimum: isBlank($bedOrder.val()) ? null : $bedOrder.val(),
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

    /** Simpan draft (buat/ubah); dipakai tombol Simpan Draft dan sebelum Ajukan bila ada perubahan. */
    function saveDraft() {
        $submit.prop('disabled', true);

        return $.ajax({
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
    }

    $form.on('submit', function (event) {
        event.preventDefault();
        saveDraft();
    });

    // ---- Open item yang ditolak otomatis (pengaju minta, GM membuka) ----

    function renderOpenPanel(proposal) {
        const $panel = $('#approval-open');
        const autoIds = proposal.auto_rejected_item_ids || [];
        const pendingIds = proposal.pending_open_item_ids || [];

        if (autoIds.length === 0 || (!proposal.can_request_open && !proposal.can_review_open)) {
            $panel.addClass('d-none').empty();
            return;
        }

        const rows = autoIds.map(id => {
            const pending = pendingIds.includes(id);
            let buttons = '';

            if (proposal.can_request_open && !pending) {
                buttons += `<button type="button" class="btn btn-xs btn-warning btn-open-request" data-id="${id}">Minta Open</button> `;
            }
            if (proposal.can_review_open) {
                buttons += `<button type="button" class="btn btn-xs btn-success btn-open-review" data-id="${id}" data-approve="1">Open</button> `;
                if (pending) {
                    buttons += `<button type="button" class="btn btn-xs btn-label-danger btn-open-review" data-id="${id}" data-approve="0">Tolak Permintaan</button>`;
                }
            }

            return `<li class="mb-1">Item ${App.escape(itemNumber(id))}${pending ? ' <span class="badge bg-label-info">permintaan Open menunggu</span>' : ''} ${buttons}</li>`;
        }).join('');

        $panel.removeClass('d-none').html(`<div class="fw-medium mb-2">Item ditolak otomatis (melewati batas kalkulator):</div><ul class="mb-0 ps-4">${rows}</ul>`);
    }

    $(document).on('click', '#approval-open .btn-open-request', function () {
        const itemId = $(this).data('id');

        Swal.fire({
            title: 'Minta Open',
            input: 'textarea',
            inputLabel: 'Alasan',
            showCancelButton: true,
            confirmButtonText: 'Kirim',
            cancelButtonText: 'Batal',
            inputValidator: value => (value && value.trim() ? null : 'Alasan wajib diisi.')
        }).then(result => {
            if (!result.isConfirmed) {
                return;
            }

            $.ajax({
                url: App.url(`api/dpl/proposals/${proposalId}/override-request`),
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ item_id: itemId, reason: result.value })
            })
                .done(res => {
                    App.notify('success', res.message);
                    fill(res.data);
                })
                .fail(xhr => App.handleError(xhr));
        });
    });

    $(document).on('click', '#approval-open .btn-open-review', function () {
        const itemId = $(this).data('id');
        const approve = $(this).data('approve') === 1;

        Swal.fire({
            title: approve ? 'Open item?' : 'Tolak permintaan Open?',
            input: 'textarea',
            inputLabel: 'Catatan (opsional)',
            showCancelButton: true,
            confirmButtonText: approve ? 'Open' : 'Tolak',
            cancelButtonText: 'Batal'
        }).then(result => {
            if (!result.isConfirmed) {
                return;
            }

            $.ajax({
                url: App.url(`api/dpl/proposals/${proposalId}/override`),
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ item_ids: [itemId], approve, reason: result.value || null })
            })
                .done(res => {
                    App.notify('success', res.message);
                    fill(res.data);
                })
                .fail(xhr => App.handleError(xhr));
        });
    });

    // ---- Ajukan & status ----

    const $submitProposal = $('#btn-submit-proposal');
    const STATUS_COLORS = {
        DRAFT: 'secondary', SUBMITTED: 'info', IN_APPROVAL: 'info', PARTIALLY_APPROVED: 'warning',
        APPROVED: 'success', REJECTED: 'danger', CANCELLED: 'dark'
    };
    const PROGRAM_BADGES = { ACTIVE: ['success', 'Aktif'], EXPIRED: ['secondary', 'Expired'], SUPERSEDED: ['dark', 'Superseded'] };
    /** Payload terakhir yang tersimpan di server; beda = ada perubahan belum disimpan. */
    let savedPayload = null;

    function showStatus(proposal) {
        $('#proposal-status')
            .text(proposal.proposal_status)
            .attr('class', `badge bg-label-${STATUS_COLORS[proposal.proposal_status] || 'secondary'}`);

        const program = PROGRAM_BADGES[proposal.program_status];
        $('#proposal-program-status')
            .text(program ? `Program ${program[1]}` : '')
            .attr('class', program ? `badge bg-${program[0]}` : 'badge d-none');
    }

    function submitProposal() {
        $submitProposal.prop('disabled', true);

        $.ajax({ url: App.url(`api/dpl/proposals/${proposalId}/submit`), type: 'POST' })
            .done(res => {
                App.notify(res.data.proposal_status === 'REJECTED' ? 'error' : 'success', res.message);
                fill(res.data);
            })
            .fail(xhr => (xhr.status === 422 && xhr.responseJSON?.errors
                ? App.notify('error', Object.values(xhr.responseJSON.errors).flat().join(' '))
                : App.handleError(xhr)))
            .always(() => $submitProposal.prop('disabled', false));
    }

    $submitProposal.on('click', () => {
        App.confirm('Ajukan DPL? Setelah diajukan draft tidak bisa diubah.', 'Ya, ajukan').then(confirmed => {
            if (!confirmed) {
                return;
            }

            if (savedPayload !== JSON.stringify(payload())) {
                saveDraft().done(submitProposal);
                return;
            }

            submitProposal();
        });
    });

    // ---- Panel approval ----

    const $approvalCard = $('#approval-card');
    const canApprove = $approvalCard.data('can-approve') === 1;
    const $approvalForm = $('#approval-form');
    const approvalModal = $approvalForm.length ? bootstrap.Modal.getOrCreateInstance(document.getElementById('approval-modal')) : null;
    const ACTION_LABELS = {
        APPROVE: ['success', 'Approve'], REJECT: ['danger', 'Reject'], AUTO_REJECT: ['danger', 'Auto Reject'], OVERRIDE: ['info', 'Open']
    };
    let actionableIds = [];
    let pendingAction = null;
    let approvalRequest = null;

    const formatDateTime = value => (value
        ? new Date(value).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' })
        : '');
    const itemNumber = itemId => {
        const index = items.findIndex(item => item.id === itemId);

        return index === -1 ? '-' : `#${index + 1}`;
    };

    function loadApproval(proposal) {
        if (!$approvalCard.length || proposal.proposal_status === 'DRAFT') {
            $approvalCard.addClass('d-none');
            return;
        }

        approvalRequest?.abort();
        approvalRequest = $.get(App.url(`api/dpl/proposals/${proposalId}/approval`))
            .done(res => renderApproval(res.data))
            .fail((xhr, status) => (status === 'abort' ? null : App.handleError(xhr)));
    }

    function stateBadge(state) {
        if (!state) {
            return muted('-');
        }

        if (state.status === 'APPROVED') {
            return `<span class="badge bg-label-success" title="${App.escape(formatDateTime(state.approved_at))}">Approve</span>`;
        }

        if (state.status === 'REJECTED') {
            return `<span class="badge bg-label-danger" title="${App.escape(state.reject_reason || '')}">Reject</span>`;
        }

        return '<span class="badge bg-label-secondary">Menunggu</span>';
    }

    /** Matriks item × step (urut sequence) + riwayat aksi terbaru di atas. */
    function renderApproval(data) {
        const steps = data.instance ? data.instance.steps : [];
        const canAct = canApprove && data.actionable_item_ids.length > 0;

        actionableIds = data.actionable_item_ids;
        $approvalCard.removeClass('d-none');
        $('#approval-actions').toggleClass('d-none', !canAct);

        const states = steps.map(step => Object.fromEntries(step.item_states.map(state => [state.dpl_item_id, state])));
        const stepName = step => (step.workflow_step?.role ? step.workflow_step.role.name : `Step ${step.workflow_step?.sequence ?? ''}`);

        $('#approval-matrix thead').html(`<tr>
            ${canAct ? '<th class="text-center"><input type="checkbox" class="form-check-input" id="approval-check-all" title="Pilih semua" /></th>' : ''}
            <th>#</th>
            <th>Product</th>
            ${steps.map(step => `<th class="text-center">${App.escape(stepName(step))}
                <div class="small text-body-secondary fw-normal text-nowrap">${App.escape(step.assignee_users.map(user => user.name).join(', ')) || '-'}</div></th>`).join('')}
        </tr>`);

        $('#approval-matrix tbody').html(!data.instance
            ? `<tr><td colspan="${3 + steps.length}" class="text-center">${muted('Belum ada data approval')}</td></tr>`
            : items.map((item, index) => `<tr>
                ${canAct ? `<td class="text-center">${actionableIds.includes(item.id)
                    ? `<input type="checkbox" class="form-check-input approval-item-check" value="${item.id}" />`
                    : ''}</td>` : ''}
                <td>${index + 1}</td>
                <td>${App.escape(item.labels.product)}</td>
                ${states.map(byItem => `<td class="text-center">${stateBadge(byItem[item.id])}</td>`).join('')}
            </tr>`).join(''));

        const actions = steps
            .flatMap(step => step.actions.map(action => ({ ...action, stepName: stepName(step) })))
            .sort((a, b) => (b.created_at || '').localeCompare(a.created_at || '') || b.id - a.id);

        $('#approval-history tbody').html(actions.length === 0
            ? `<tr><td colspan="6" class="text-center">${muted('Belum ada riwayat')}</td></tr>`
            : actions.map(action => {
                const label = ACTION_LABELS[action.action];

                return `<tr>
                    <td class="text-nowrap">${App.escape(formatDateTime(action.created_at))}</td>
                    <td>${App.escape(action.stepName)}</td>
                    <td>${action.actor ? `${App.escape(action.actor.name)} ${muted(`(${App.escape(action.actor.nik)})`)}` : muted('-')}</td>
                    <td>${label ? `<span class="badge bg-label-${label[0]}">${label[1]}</span>` : App.escape(action.action)}</td>
                    <td>${App.escape(itemNumber(action.dpl_item_id))}</td>
                    <td>${action.reason ? App.escape(action.reason) : muted('-')}</td>
                </tr>`;
            }).join(''));
    }

    $approvalCard.on('change', '#approval-check-all', function () {
        $approvalCard.find('.approval-item-check').prop('checked', $(this).is(':checked'));
    });

    $approvalCard.on('click', '[data-approval-action]', function () {
        const action = $(this).data('approval-action');
        const itemIds = $(this).data('approval-scope') === 'all'
            ? actionableIds
            : $approvalCard.find('.approval-item-check:checked').toArray().map(el => Number(el.value));

        if (itemIds.length === 0) {
            App.notify('error', 'Pilih item terlebih dahulu.');
            return;
        }

        const reject = action === 'reject';
        pendingAction = { action, itemIds };

        $approvalForm[0].reset();
        App.clearErrors($approvalForm);
        $('#approval-modal-title').text(reject ? 'Reject Item' : 'Approve Item');
        $('#approval-modal-summary').text(`${itemIds.length} item (${itemIds.map(itemNumber).join(', ')}) akan ${reject ? 'ditolak' : 'di-approve'}.`);
        $('#approval-reason-required').toggleClass('d-none', !reject);
        $('#approval-reason').prop('required', reject).attr('placeholder', reject ? 'Wajib diisi' : 'Opsional');
        $('#approval-modal-submit')
            .text(reject ? 'Reject' : 'Approve')
            .attr('class', `btn ${reject ? 'btn-danger' : 'btn-success'}`);
        approvalModal.show();
    });

    $approvalForm.on('submit', function (event) {
        event.preventDefault();

        const reason = $('#approval-reason').val().trim();

        if (pendingAction.action === 'reject' && reason === '') {
            App.showErrors($approvalForm, { reason: ['Alasan reject wajib diisi.'] });
            return;
        }

        const $button = $('#approval-modal-submit').prop('disabled', true);

        $.ajax({
            url: App.url(`api/dpl/proposals/${proposalId}/${pendingAction.action}`),
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ item_ids: pendingAction.itemIds, reason: reason || null })
        })
            .done(res => {
                approvalModal.hide();
                App.notify('success', res.message);
                fill(res.data);
            })
            .fail(xhr => App.handleError(xhr, $approvalForm))
            .always(() => $button.prop('disabled', false));
    });

    function fill(proposal) {
        showStatus(proposal);
        $('#proposal-outlet-text').val(`${proposal.outlet.name} (${proposal.outlet.code})`
            + (proposal.outlet.area ? ` - ${proposal.outlet.area.name}` : ''));
        $('#proposal-team-text').val(proposal.team_code);
        teamCode = proposal.team_code;
        outletId = proposal.outlet_id;
        $('#proposal-offer').val(proposal.date_offer);
        $form.find('[name="proposal_type"]').val(proposal.proposal_type);
        $form.find('[name="date_start"]').val(proposal.date_start);
        $form.find('[name="date_end"]').val(proposal.date_end);
        $('#proposal-contract').prop('checked', proposal.is_contract);
        applyBed(proposal.bed_count_snapshot, proposal.bed_order_minimum);
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
        recalcItems();
        loadPricing();

        savedPayload = JSON.stringify(payload());
        // Ajukan hanya untuk draft; DPL yang sudah diajukan disimpan sebagai "Simpan & Ajukan Ulang".
        const isDraft = proposal.proposal_status === 'DRAFT';
        $submitProposal.toggleClass('d-none', !(canSave && isDraft && proposal.can_edit && proposal.items.length > 0));
        $submit.html(isDraft
            ? '<i class="icon-base bx bx-save me-1"></i> Simpan Draft'
            : '<i class="icon-base bx bx-revision me-1"></i> Simpan &amp; Ajukan Ulang');
        loadApproval(proposal);
        renderOpenPanel(proposal);
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
