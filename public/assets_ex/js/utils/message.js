var message = {
    success: function (elm, message) {
        var html = '<div class="alert  alert-success alert-dismissible fade show" role="alert">';
        html += '<span class="badge badge-pill badge-success">Success</span> ' + message + '';
        html += '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>';
        html += '</div>';

        $(elm).html(html);
    },

    error: function (elm, message) {
        var html = '<div class="alert  alert-danger alert-dismissible fade show" role="alert">';
        html += '<span class="badge badge-pill badge-danger">Error</span> ' + message + '';
        html += '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>';
        html += '</div>';

        $(elm).html(html);
    },

    showDialog: function (content) {
        bootbox.dialog({
            message: content
        });
    },

    showMaterialDialog: function (title, message) {
        $('.modal').find('#title_modal').text(title);
        $('.modal').find('.message_modal').html(message);
        //  console.log($('.modal'));
        $('.modal').modal();
    },

    closeDialog: function () {
        $('.bootbox-close-button').click();
    },

    //untuk menampilkan tooltip
    show_tooltip: function (elm) {
        $(elm).tooltip({
            placement: 'bottom'
        });
    },

    showCustomTooltip: function (elm, position = 'bottom') {
        $(elm).tooltip({
            placement: position
        });
    },

    loadingProses: function (message) {
        var loader = '<div class="loader loader-default is-active" data-text="' + message + '"></div>';
        $('.loader').html(loader);
    },

    closeLoading: function () {
        $('div.loader').removeClass('is-active');
    },

    successMessage: (pesan = 'Berhasil') => {
        let container = $('div.message');
        let html = `<div class="sufee-alert alert with-close alert-success alert-dismissible fade show">
        <span class="badge badge-pill badge-success">Success</span>
        ${pesan}
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">×</span>
        </button>
    </div>`;
    container.html(html);
    // return html;
    },
    
    errorMessage: (pesan = 'Gagal') => {
        let container = $('div.message');
        let html = `<div class="sufee-alert alert with-close alert-danger alert-dismissible fade show">
        <span class="badge badge-pill badge-danger">Error</span>
        ${pesan}
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">×</span>
        </button>
    </div>`;
    container.html(html);
    // return html;
    },
    
    warningMessage: (pesan = 'Warning') => {
        let container = $('div.message');
        let html = `<div class="sufee-alert alert with-close alert-warning alert-dismissible fade show">
        <span class="badge badge-pill badge-warning">Peringatan</span>
        ${pesan}
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">×</span>
        </button>
    </div>`;
    container.html(html);
    // return html;
    },


    infoMessage: (pesan = 'Informasi') => {
        let container = $('div.message');
        let html = `<div class="sufee-alert alert with-close alert-info alert-dismissible fade show">
        <span class="badge badge-pill badge-info">Informasi</span>
        ${pesan}
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">×</span>
        </button>
    </div>`;
    container.html(html);
    // return html;
    }
};
