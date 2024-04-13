/* !function to prevent library conflicts */
!function ($) {

  $.fn.closeModal = async function (){
    var main = $(this);
    var modal = main.find('.filters-wrapper');
    var body = $(document.body);
    body.removeClass('modal-open');
    modal.removeClass('modal-open');
    main.focus();
  }
  $.fn.filterFocus = async function(argument){
    var target = argument['#attributes']['data-drupal-selector'];
    var HTMLtarget = $('input#'+target);
    var focusTrap = $('a#focus-trap');
    await sleep(200);
    document.getElementById(target).blur();
    var checked = HTMLtarget[0].checked;
    if(checked == true){
      document.getElementById(target).setAttribute('aria-label', 'checked');
    }
    else{
      document.getElementById(target).setAttribute('aria-label', 'not checked');
    }

    document.getElementById(target).focus();
  }
  function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
  }
}(window.jQuery);
/* window.jQuery to end !function */
