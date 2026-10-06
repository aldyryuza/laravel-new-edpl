/**
 * Perilaku modal import Excel (<x-excel-import-modal>).
 *
 * Upload file ke `data-upload-path` form, tampilkan daftar error per baris (422, `errors.file`),
 * dan picu event `excel-import:done` di document setelah sukses.
 */
'use strict';

$(function () {
    const $form = $('#excel-import-form');

    if ($form.length === 0) {
        return;
    }

    const $submit = $form.find('[type="submit"]');
    const $errors = $('#excel-import-errors');
    const modalElement = document.getElementById('excel-import-modal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalElement);

    function resetErrors() {
        App.clearErrors($form);
        $errors.addClass('d-none').find('ul').empty();
    }

    modalElement.addEventListener('show.bs.modal', () => {
        $form[0].reset();
        resetErrors();
    });

    $form.on('submit', function (event) {
        event.preventDefault();
        resetErrors();
        $submit.prop('disabled', true);

        $.ajax({
            url: App.url($form.data('upload-path')),
            type: 'POST',
            data: new FormData($form[0]),
            processData: false,
            contentType: false
        })
            .done(res => {
                modal.hide();
                App.notify('success', res.message);
                $(document).trigger('excel-import:done', [res.data]);
            })
            .fail(xhr => {
                const messages = xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors
                    ? xhr.responseJSON.errors.file
                    : null;

                if (!messages) {
                    App.handleError(xhr, $form);
                    return;
                }

                $errors.find('ul').html(messages.map(message => `<li>${App.escape(message)}</li>`).join(''));
                $errors.removeClass('d-none');
            })
            .always(() => $submit.prop('disabled', false));
    });
});
