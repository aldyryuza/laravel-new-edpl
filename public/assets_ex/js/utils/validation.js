var validation = {
  run: function () {
    $('.data-error').remove();
    var required = $('.required');
    var empty = 0;
    var is_valid = 1;

    $.each(required, function () {
      var value = $(this).val();
      if (value == '') {
        empty += 1;
        $(this).after('<p style="color:#ff5b5c;" class="data-error">* ' + $(this).attr('error') + ' Harus Diisi</p>');
      }
    });

    if (empty > 0) {
      is_valid = 0;
    }

    return is_valid;
  },

  runWithElement: function (elm) {
    let data = $(elm);
    let empty = 0;
    let is_valid = 1;

    $.each(data, function(){
      let $this = $(this);
      $this.find('.data-error').remove();
      let required = $this.find('.required');
  
      $.each(required, function () {
        let value = $(this).val();
        if (value == '') {
          empty += 1;
          $(this).after('<p style="color:#ff5b5c;" class="data-error">* ' + $(this).attr('error') + ' Harus Diisi</p>');
          $(this).addClass('is-invalid');
        }else{
          $(this).removeClass('is-invalid');
        }
      });
  
      if (empty > 0) {
        is_valid = 0;
      }
    });    

    return is_valid;
  }
};