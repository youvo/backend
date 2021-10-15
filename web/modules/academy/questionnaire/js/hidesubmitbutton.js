(function ($) {
  Drupal.behaviors.hideSubmitButton = {
    attach: function () {
      $(document).ready(function() {
        if ($('textarea[data-drupal-selector=edit-body]').is(':visible')) {
          $('div[id=edit-actions]').hide();
          $('label[for^=edit-body]').addClass('form-required');
          $('h4.form-item__label--multiple-value-form').addClass('form-required');
        } else {
          $('div[id=edit-actions]').show();
        }
      });
    }
  }
})(jQuery);
