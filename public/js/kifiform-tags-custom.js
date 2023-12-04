(function($) {
  "use strict";

  $(once("asklib-tag-insert", "form.asklib-question-edit-form input.form-autocomplete"))
    .on("kififormtaginsert", function(event, ui) {
      console.log(event);

      if (ui.item.autocompleted == true) {
        // This is valid as long autocomplete is configured to match only Finto terms.
        ui.tag.addClass("finto-taxonomy-tag");
      }
    });
}(jQuery));
