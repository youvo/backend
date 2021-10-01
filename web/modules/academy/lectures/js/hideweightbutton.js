(function ($) {
  Drupal.behaviors.hideRowWeightButton = {
    attach: function () {
      $(document).ready(function() {
        $('.tabledrag-toggle-weight-wrapper').hide();
      });
    }
  }
})(jQuery);
