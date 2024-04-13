/* !function to prevent library conflicts */
!function ($) {
  $(document).ready(function () {
    $("input[data-remove-position-item]").on('click',function() {
      $("select[name='positions']").val('all').trigger('change');
      $("select[name='positions_mobile']").val('all').trigger('focusout');
      $(this.element).remove();
    });
    $("input[data-remove-department-item]").on('click',function() {
      $("select[name='departments']").val('all').trigger('change');
      $("select[name='departments_mobile']").val('all').trigger('focusout');
      $(this.element).remove();
    });
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
    $("input[data-remove-checkbox-item]").on('click',function(){
      var yearToRemove = $(this).attr("data-remove-checkbox-item");
      $("input[name='years["+yearToRemove+"]']").trigger("click");
      //Remove from active filter bar
      $(this.parentElement).remove();

    });
  });
  $.fn.filterRefresh2 = function(){

    $("input[data-remove-position-item]").on('click',function() {
      $("select[name='positions']").val('all').trigger('change');
      $("select[name='positions_mobile']").val('all').trigger('focusout');
      $(this.element).remove();
    });
    $("input[data-remove-department-item]").on('click',function() {
      $("select[name='departments']").val('all').trigger('change');
      $("select[name='departments_mobile']").val('all').trigger('focusout');
      $(this.element).remove();
    });

    $("input[data-remove-dates]").on('input',function(){
      var yearToRemove = $(this).attr("data-remove-dates");
      $("input[name='date_start']").val('');
      $("input[name='date_end']").val('');
      $("input[name='date_start_mobile']").val('');
      $("input[name='date_end_mobile']").val('').trigger('focusout');
      $("button[id=edit-ready--2]").trigger("change").trigger('focusout');
      //Remove from active filter bar
      $(this.parentElement).remove();

    });
    $("input[data-remove-checkbox-item]").on('click',function(){
      var yearToRemove = $(this).attr("data-remove-checkbox-item");
      $("input[name='years["+yearToRemove+"]']").trigger("click");
      //Remove from active filter bar
      $(this.parentElement).remove();

    });
  }
}(window.jQuery);

