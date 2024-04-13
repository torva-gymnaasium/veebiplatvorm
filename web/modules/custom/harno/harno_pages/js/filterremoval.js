!function ($) {
    $("span[data-remove-checkbox-item]").on('click',function(){
      console.log('pressed');
      var yearToRemove = $(this).attr("data-remove-checkbox-item");
      $("input[name='years["+yearToRemove+"]']").trigger("click");
      //Remove from active filter bar
      //$(this.parentElement).remove();

    });
}(window.jQuery);
