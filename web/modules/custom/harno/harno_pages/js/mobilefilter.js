/**
 * @file
 * Global utilities.
 *
 */
(function ($, Drupal) {

  'use strict';

  Drupal.behaviors.harno_theme_search_results = {
    attach: function (context, settings) {
      $(document, context).each( function() {

        //mobiilis sisutüübi filtri duubeldamine
        var clone_html = '';
        $('.filters-top input:checked').each(function () {
          var input_value = $(this).val();
          var $clone = $('#search-item-' + input_value).clone();
          $clone.find(':checked').prop('checked', false).addClass('search_type_mobile').attr('id', 'mobile-tag-'+ input_value).removeAttr('onchange').attr('name', '');
          $clone.find('.btn-tag').addClass('btn-tag-remove');
          $clone.find('label').attr('for', 'mobile-tag-'+ input_value );
          clone_html = clone_html + $clone.html();
        });
        $('.mobile-filters-output').html(clone_html);

        //mobiili X filtril klikkides muudame algse filtri väärtust ja trigerdame selle muutuse, mis omakorda trigerdab vormi submiti.
        $( '.search_type_mobile' ).on( "click", function() {
          var search_type_id = $(this).val();
          console.log(search_type_id);
          $("input[name='event_type[" + search_type_id + "]']").prop('checked', false).change();
        });

      });

      //vormi submit kui on valitud filter
      $( '.search_type_checkbox' ).on( "click", function() {
        if ( !$('.filters-wrapper').hasClass('modal-open') ) {
          $('#gallery-filter-form #edit-ready').click();
        }
      });
      //vormi submit kui klikitakse mobiilis valmis nuppu
      $( '.filters-ready' ).on( "click", function(event) {
        event.preventDefault();
        $('body').removeClass('modal-open');
        $('#gallery-filter-form #edit-ready').click();
      });
      //vormi submit enteri vajutamisel peab enne tühjendama filtrid ja siis trigerdama ajaxi
      // $( "input[name='keys']" ).keypress(function(event) {
      //   if (event.keyCode == 13) {
      //     event.preventDefault();
      //     $(this).change();
      //     $('.gallery-filter-form #edit-ready').click();
      //   }
      // });

      $( document ).ajaxComplete(function() {
        $wpm.initialize();
      });
    }
  };

})(jQuery, Drupal);
