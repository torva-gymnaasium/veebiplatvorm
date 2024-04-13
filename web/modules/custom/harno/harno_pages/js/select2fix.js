/**
 * @file
 * JavaScript behaviors for Select2 integration.
 */

(function ($, Drupal) {
  $('*[data-plugin="selectTwo"]').each(function (){
    var main = $(this);

    //main.select2();

    var label = main.parent().find('.form-label');

    label.on('click', function(){
      main.select2('open');
    })
  });


})(jQuery, Drupal);
