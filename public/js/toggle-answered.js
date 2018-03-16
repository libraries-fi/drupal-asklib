(function($) {
  "use strict";

  Drupal.behaviors.asklibToggleAnswered = {
    attach: function(context, settings) {
      var button_preview = $("#edit-actions li.save-and-preview", context);
      var button_submit = $("#edit-actions li.submit", context);
      var toggle_class = "secondary-action";

      $("#edit-skip-email", context).on("change", function(event) {
        if (this.checked) {
          button_submit.removeClass(toggle_class).prependTo(button_submit.parent());
          button_preview.addClass(toggle_class).appendTo(button_preview.parent());
        } else {
          button_preview.removeClass(toggle_class).prependTo(button_submit.parent());
          button_submit.addClass(toggle_class).appendTo(button_preview.parent());
        }
      }).trigger("change");
    }
  }
}(jQuery));
