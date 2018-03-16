(function($) {
  "use strict";

  $(".rating-container").each(function(i, element) {
    var container = $(element);

    container.find(".rating-form :submit").on("click", function(event) {
      event.preventDefault();
      var form = $(this).closest("form");
      var data = form.serializeArray();

      data.push({
        name: "p",
        value: event.currentTarget.value
      });

      $.post(form.attr("action"), data).done(function(response) {
        form.parent().slideUp(function() { $(this).remove() });
        container.find("[data-bind='votes']").text(response.votes);
        container.find(".rating-fill").css({width: response.rating + "%"});
      });
    });

    container.find(".rating-form").on("submit", function(event) {
      event.preventDefault();
    });

  });

 }(jQuery));
