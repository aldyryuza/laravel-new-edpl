let Toast = {
    success: (title, message) => {
        let html = `<div class="bs-toast toast toast-ex animate__animated my-2 fade animate__bounce show bg-success" role="alert" aria-live="assertive" aria-atomic="true" data-delay="2000">
        <div class="toast-header bg-success">
            <div class="me-auto fw-semibold">${title}</div>

            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body">
            ${message}
        </div>
    </div>
    `


        $('.toast-content').html(html);
    },

    error: (title, message) => {
        let html = `<div div class="bs-toast toast toast-ex animate__animated my-2 fade animate__bounce show bg-danger" role = "alert" aria - live="assertive" aria-atomic="true" data - delay="2000" >
        <div class="toast-header bg-danger">
          <div class="me-auto fw-semibold">${title}</div>

          <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body">
            ${message}
        </div>
      </div> `

        $('.toast-content').html(html);
    },

    warning: (title, message) => {
        let html = `< div class="bs-toast toast toast-ex animate__animated my-2 fade animate__bounce show bg-warning" role = "alert" aria - live="assertive" aria - atomic="true" data - delay="2000" >
        <div class="toast-header bg-warning">
          <div class="me-auto fw-semibold">${title}</div>

          <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body">
            ${message}
        </div>
      </ > `

        $('.toast-content').html(html);
    },

    info: (title, message) => {
        let html = `< div class="bs-toast toast toast-ex animate__animated my-2 fade animate__bounce show bg-info" role = "alert" aria - live="assertive" aria - atomic="true" data - delay="2000" >
        <div class="toast-header bg-info">
          <div class="me-auto fw-semibold">${title}</div>

          <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body">
            ${message}
        </div>
      </ > `

        $('.toast-content').html(html);
    },

    primary: (title, message) => {
        let html = `< div class="bs-toast toast toast-ex animate__animated my-2 fade animate__bounce show bg-primary" role = "alert" aria - live="assertive" aria - atomic="true" data - delay="2000" >
        <div class="toast-header bg-primary">
          <div class="me-auto fw-semibold">${title}</div>

          <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body">
            ${message}
        </div>
      </ > `

        $('.toast-content').html(html);
    }
};
