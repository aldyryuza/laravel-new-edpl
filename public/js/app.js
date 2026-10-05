/**
 * Bootstrap aplikasi.
 *
 * Dimuat di semua halaman setelah main.js.
 * Isinya hanya hal yang benar-benar dipakai lintas halaman:
 * CSRF, helper URL, notifikasi, dan penanganan error AJAX sesuai
 * envelope response project ({ success, message, data, errors }).
 */
window.App = (function () {
    'use strict';

    const meta = name => document.querySelector(`meta[name="${name}"]`)?.getAttribute('content') || '';

    const csrfToken = meta('csrf-token');
    const baseUrl = (meta('base-url') || window.location.origin).replace(/\/+$/, '');

    if (window.jQuery) {
        jQuery.ajaxSetup({ headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' } });
    }

    /** Bangun URL absolut dari path relatif. */
    function url(path = '') {
        return `${baseUrl}/${String(path).replace(/^\/+/, '')}`;
    }

    /** Notifikasi singkat. Memakai Notyf bila vendor-nya dimuat halaman ini. */
    function notify(type, message) {
        if (window.Notyf) {
            App._notyf = App._notyf || new Notyf({ duration: 4000, position: { x: 'right', y: 'top' } });
            App._notyf.open({ type: type === 'error' ? 'error' : 'success', message });
            return;
        }

        if (window.Swal) {
            Swal.fire({ icon: type, text: message, timer: 3000, showConfirmButton: false });
            return;
        }

        console[type === 'error' ? 'error' : 'log'](message);
    }

    /** Escape teks sebelum disisipkan ke HTML (render kolom tabel, dsb). */
    function escape(value) {
        return String(value ?? '').replace(/[&<>"']/g, char => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        })[char]);
    }

    /**
     * Dialog konfirmasi. Resolve true jika user menyetujui.
     * Memakai SweetAlert2 bila vendor-nya dimuat, selain itu window.confirm.
     */
    function confirm(message, confirmText = 'Ya, lanjutkan') {
        if (!window.Swal) {
            return Promise.resolve(window.confirm(message));
        }

        return Swal.fire({
            text: message,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: confirmText,
            cancelButtonText: 'Batal',
            buttonsStyling: false,
            customClass: { confirmButton: 'btn btn-danger me-2', cancelButton: 'btn btn-label-secondary' }
        }).then(result => result.isConfirmed);
    }

    /**
     * Opsi `ajax` DataTables untuk endpoint Yajra (`GET api/{resource}/datatable`).
     *
     *   $('#table').DataTable({ serverSide: true, ajax: App.dataTableSource('api/users/datatable'), ... });
     *
     * @param {string} path
     * @param {Function} [extraParams] mengembalikan objek parameter tambahan (mis. filter form)
     */
    function dataTableSource(path, extraParams = null) {
        return {
            url: url(path),
            data: params => (extraParams ? Object.assign(params, extraParams()) : params),
            error: xhr => handleError(xhr)
        };
    }

    /** Hapus tampilan error validasi pada sebuah form. */
    function clearErrors(form) {
        const $form = jQuery(form);
        $form.find('.is-invalid').removeClass('is-invalid');
        $form.find('.invalid-feedback').remove();
    }

    /** Tampilkan error validasi Laravel di dekat field terkait. */
    function showErrors(form, errors = {}) {
        const $form = jQuery(form);
        clearErrors($form);

        Object.keys(errors).forEach(field => {
            const message = Array.isArray(errors[field]) ? errors[field][0] : errors[field];
            const $input = $form.find(`[name="${field}"], [name="${field}[]"]`).first();

            if ($input.length === 0) {
                notify('error', message);
                return;
            }

            $input.addClass('is-invalid');
            $input.after(`<div class="invalid-feedback d-block">${message}</div>`);
        });
    }

    /**
     * Penanganan error AJAX standar.
     * 422 -> error validasi per field, selain itu -> notifikasi.
     */
    function handleError(xhr, form = null) {
        const payload = xhr.responseJSON || {};

        if (xhr.status === 422 && payload.errors) {
            if (form) {
                showErrors(form, payload.errors);
            } else {
                notify('error', payload.message || 'Data yang dikirim tidak valid.');
            }
            return;
        }

        if (xhr.status === 401 || xhr.status === 419) {
            notify('error', 'Sesi berakhir. Silakan login ulang.');
            return;
        }

        notify('error', payload.message || 'Terjadi kesalahan. Silakan coba lagi.');
    }

    /**
     * Theme switcher light/dark/system.
     *
     * Sneat mengikat tombol ini lewat template-customizer.js. Customizer
     * dimatikan (config sneat.customizer), jadi binding-nya dilakukan di sini
     * memakai API window.Helpers, dengan localStorage key yang sama supaya
     * tetap kompatibel kalau customizer dinyalakan lagi.
     */
    function initThemeSwitcher() {
        const helpers = window.Helpers;
        const toggles = document.querySelectorAll('[data-bs-theme-value]');

        // Kalau customizer dinyalakan, biarkan customizer yang menangani.
        if (window.templateCustomizer || !helpers || toggles.length === 0) {
            return;
        }

        const storageKey = `templateCustomizer-${window.templateName}--Theme`;
        const read = () => {
            try {
                return localStorage.getItem(storageKey);
            } catch (e) {
                return null;
            }
        };

        const apply = value => {
            helpers.setTheme(value);
            helpers.showActiveTheme(value);
        };

        apply(read() || document.documentElement.getAttribute('data-bs-theme') || 'light');

        toggles.forEach(toggle => {
            toggle.addEventListener('click', () => {
                const value = toggle.getAttribute('data-bs-theme-value');

                try {
                    localStorage.setItem(storageKey, value);
                } catch (e) {
                    // Mode private/storage penuh: theme tetap berubah, hanya tidak tersimpan.
                }

                apply(value);
            });
        });

        // Ikuti perubahan setting OS selama pilihan user masih "system".
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
            if (read() === 'system') {
                apply('system');
            }
        });
    }

    /**
     * Pencarian menu sidebar.
     *
     * Hanya memfilter item yang sudah dirender server (sudah tersaring hak akses).
     * Item cocok jika judulnya atau judul parent-nya mengandung kata kunci; parent
     * dari item yang cocok ikut tampil dan dibuka. Enter membuka hasil pertama,
     * Esc mengosongkan, "/" memfokuskan kolom pencarian.
     */
    function initSidebarSearch() {
        const input = document.getElementById('sidebar-menu-search');
        const root = document.querySelector('#layout-menu .menu-inner');

        if (!input || !root) {
            return;
        }

        const items = () => root.querySelectorAll('li.menu-item, li.menu-header');
        const children = li => Array.from(li.querySelector(':scope > .menu-sub')?.children || []);
        const title = li => (li.querySelector(':scope > .menu-link > div')?.textContent || '').trim().toLowerCase();

        const empty = document.createElement('li');
        empty.className = 'menu-item d-none';
        empty.innerHTML = '<div class="menu-link text-body-secondary">Menu tidak ditemukan</div>';
        root.appendChild(empty);

        let searching = false;

        /** @returns {boolean} apakah ada item yang tampil */
        function filter(list, query, parentMatched) {
            let anyVisible = false;

            list.forEach(li => {
                if (li === empty) {
                    return;
                }

                if (li.classList.contains('menu-header')) {
                    li.classList.add('d-none');
                    return;
                }

                const selfMatched = parentMatched || title(li).includes(query);
                const subs = children(li);
                const childVisible = subs.length > 0 && filter(subs, query, selfMatched);
                const visible = selfMatched || childVisible;

                li.classList.toggle('d-none', !visible);

                if (subs.length > 0) {
                    li.classList.toggle('open', childVisible);
                }

                anyVisible = anyVisible || visible;
            });

            return anyVisible;
        }

        function apply() {
            const query = input.value.trim().toLowerCase();

            if (query === '') {
                if (searching) {
                    // Kembalikan state buka/tutup seperti sebelum mencari.
                    items().forEach(li => {
                        li.classList.remove('d-none');
                        if (li.dataset.wasOpen !== undefined) {
                            li.classList.toggle('open', li.dataset.wasOpen === '1');
                            delete li.dataset.wasOpen;
                        }
                    });
                    empty.classList.add('d-none');
                    searching = false;
                }
                return;
            }

            if (!searching) {
                items().forEach(li => {
                    li.dataset.wasOpen = li.classList.contains('open') ? '1' : '0';
                });
                searching = true;
            }

            const found = filter(Array.from(root.children), query, false);
            empty.classList.toggle('d-none', found);
        }

        input.addEventListener('input', apply);

        input.addEventListener('keydown', event => {
            if (event.key === 'Escape') {
                input.value = '';
                apply();
                input.blur();
            }

            if (event.key === 'Enter') {
                event.preventDefault();
                const first = Array.from(root.querySelectorAll('li.menu-item:not(.d-none) > a.menu-link:not(.menu-toggle)'))
                    .find(link => link.offsetParent !== null && !link.getAttribute('href')?.startsWith('javascript:'));

                if (first) {
                    window.location.href = first.href;
                }
            }
        });

        document.addEventListener('keydown', event => {
            const target = event.target;
            const typing = target instanceof HTMLElement && (target.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(target.tagName));

            if (event.key === '/' && !typing) {
                event.preventDefault();
                input.focus();
            }
        });
    }

    function init() {
        initThemeSwitcher();
        initSidebarSearch();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    return { baseUrl, csrfToken, url, notify, escape, confirm, dataTableSource, clearErrors, showErrors, handleError };
})();
