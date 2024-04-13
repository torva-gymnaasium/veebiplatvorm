!function ($) {
  $(document).ready(function () {
    $.fn.accordion = function () {
      $(this).each(function () {
        var main = $(this);

        var accordionTitle = main.find('.accordion__title');
        accordionTitle.on('click', function () {
          var el = $(this);
          var accordionParent = el.parent();
          var accordionButton = el.find('.btn-accordion');
          accordionParent.toggleClass('active');

          if (accordionParent.hasClass('active')) {
            accordionButton.attr('aria-expanded', 'true')
          } else {
            accordionButton.attr('aria-expanded', 'false')
          }
        });
      });
    }
    $.fn.movingLabel = function() {
      $(this).each(function() {
        var main = $(this);
        var input = main.find('input, select, textarea, button');

        input.val() == "" || input.val() == null ? main.removeClass('is-focused') : main.addClass('is-focused');

        input.on('focus click', function() {
          main.addClass('is-focused');
        }).on('blur', function() {
          main.removeClass('is-focused');
          if (input.val() == "") {
            main.removeClass('is-focused');
          } else {
            main.addClass('is-focused');
          }
        });
      });
    }
    $.fn.filtersModal = function() {
      var main = $(this);
      var modal = main.parents().find('.filters-wrapper');
      var modalClose = modal.find('.btn-close');
      var firstItem = modal.find('.modal-header').children().first();
      var lastItem = modal.find('.filters-bottom').children().last();
      var lastItemContact = modal.find('.form-row-items').children().last();
      var readyBtn = modal.find('.filters-ready');
      main.find('.mobile-filter-trigger').on('click', function(e) {
        e.preventDefault();
        $('body').addClass('modal-open');
        modal.addClass('modal-open');
        modalClose.focus();

        if (modal.hasClass('modal-open')) {
          modalClose.on('click', function (e) {
            e.preventDefault();
            $('body').removeClass('modal-open');
            modal.removeClass('modal-open');
            main.focus();
          });
        }

        tabFocusTrap(lastItem, modalClose);
        tabFocusTrap(lastItemContact, modalClose);
      });

      function tabFocusTrap(lastItem, close){
        $(document).on('keyup', function(e){
          lastItem.on('keyup', function(e){
            if(e.keyCode == 9 || e.which == 9) {
              e.preventDefault();
              close.focus();
            }
          });

          firstItem.on('keyup', function(e){
            if(e.keyCode == 9 || e.which == 9 && e.keyCode == 16 || e.which == 16) {
              e.preventDefault();
              readyBtn.focus();
            }
          });

          if(e.keyCode == 27){
            modal.removeClass('modal-open');
            main.focus();
          }
        });
      }
    }
  });
}(window.jQuery);
