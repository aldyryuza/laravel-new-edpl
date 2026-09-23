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

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initThemeSwitcher);
    } else {
        initThemeSwitcher();
    }

    return { baseUrl, csrfToken, url, notify, clearErrors, showErrors, handleError };
})();
