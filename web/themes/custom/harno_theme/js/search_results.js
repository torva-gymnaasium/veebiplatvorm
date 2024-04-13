/**
 * @file
 * Global utilities.
 */
(function ($, Drupal) {
  'use strict';
  Drupal.behaviors.harno_theme_search_results = {
    attach: function (context, settings) {
      $(document, context).each( function() {
        //tõsta pealkirja vastuste koguarv
        var search_total = 0;
        if ($('#search_result_total_span').length) {
          search_total = $('#search_result_total_span').html();
        }
        $('h1').html(Drupal.t('Search results') + ' (' + search_total + ')');

        //millised sisutüübi filtri kastid ära peita
        if($('#search_page_url_span').length) {
          var url = $('#search_page_url_span').text();
          $.ajax({
            url: url,
            cache: true,
            success: function (response) {
              var parsedResponse = $.parseHTML(response);
              for (var o = 1; o <= 8; o++) {
                if ($(parsedResponse).find('#search_result_' + o + '_span').length) {
                  $('#search-item-' + o + '-span').show();
                }
              }
            }
          });
        }
        else {
          var hide_parent = 1;
          for (var o = 1; o <= 8; o++) {
            if ($('#search_result_' + o + '_span').length) {
              hide_parent = 0;
              $('#search-item-' + o + '-span').show();
            }
          }
          if (hide_parent) {
            $('.filters-top').hide();
          }
        }

        //eemaldada sisutüübi filter, kui otsingusõna muutub
        $("input[name='keys']").on("change", function() {
          $('.alert').fadeOut();
          for (var o = 1; o <= 8; o++) {
            if ($('#search-item-' + o).length) {
              $('#search-item-' + o).prop('checked', false);
            }
          }
        });

        //vormi submit kui on valitud filter
        $( '.search_type_checkbox' ).on( "click", function() {
          if ( !$('.filters-wrapper').hasClass('modal-open') ) {
            $('#views-exposed-form-general-search-general-search .search-submit-btn').click();
          }
        });

        //vormi submit enteri vajutamisel peab enne tühjendama filtrid ja siis trigerdama ajaxi
        $( "input[name='keys']" ).keypress(function(event) {
          if (event.keyCode == 13) {
            event.preventDefault();
            $(this).change();
            $('#views-exposed-form-general-search-general-search .search-submit-btn').click();
          }
        });

        $( document ).ajaxComplete(function() {
          $wpm.initialize();
        });
      });
    }
  };

})(jQuery, Drupal);
