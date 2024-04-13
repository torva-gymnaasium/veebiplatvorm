/* !function to prevent library conflicts */
(function ($,Drupal) {
  $(document).ready(function () {
    $("input[data-remove-dates]").on('click',function(){
      var yearToRemove = $(this).attr("data-remove-dates");

      $("input[name='date_end']").val('');
      $("input[name='date_start']").val('');
      $("input[name='date_start_mobile']").val('').trigger('focusout');
      $("input[name='date_end_mobile']").val('').trigger('change').trigger('focusout');
      $("button[id=edit-ready--2]").trigger("click").trigger('focusout');
      //Remove from active filter bar
      $(this.parentElement).remove();

    });
    $("input[data-remove-days]").on('click',function(){
      var $days_all = $('input#edit-days-month');
      $days_all.trigger('click');
      $(this.parentElement).remove();
    });
    $("input[data-remove-checkbox-item]").on('click',function(){
      var toRemove = $(this).attr("data-remove-checkbox-value");
      var $mobile_filter = $("input[name='event_type["+toRemove+"]'");
      if ($mobile_filter){
        $mobile_filter.trigger("click");
        $(this.parentElement).remove();
      }
      //Remove from active filter bar

    });
  });

})(jQuery,  Drupal);
