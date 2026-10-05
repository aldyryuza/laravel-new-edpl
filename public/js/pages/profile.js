/**
 * Profil milik user yang login: update data profil dan ganti password.
 */
'use strict';

$(function () {
    function submit($form, url, onSuccess) {
        const $button = $form.find('[type="submit"]');

        App.clearErrors($form);
        $button.prop('disabled', true);

        $.ajax({ url: App.url(url), type: 'PUT', data: $form.serialize() })
            .done(res => {
                App.notify('success', res.message);
                onSuccess(res);
            })
            .fail(xhr => App.handleError(xhr, $form))
            .always(() => $button.prop('disabled', false));
    }

    $('#profile-form').on('submit', function (event) {
        event.preventDefault();

        submit($(this), 'api/profile', res => {
            // Nama di navbar ikut diperbarui tanpa reload.
            $('[data-auth-name]').text(res.data.name);
            $('[data-auth-email]').text(res.data.email);
        });
    });

    $('#password-form').on('submit', function (event) {
        event.preventDefault();

        const $form = $(this);
        submit($form, 'api/profile/password', () => $form[0].reset());
    });
});
