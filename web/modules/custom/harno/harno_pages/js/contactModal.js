/* !function to prevent library conflicts */
!function ($) {

  $(function(){
    $.fn.modal = function(){
      $(this).each(function(){
        var main = $(this);
        var href = main.attr('href');
        var html, overlay;
        var modalType = main.attr('data-modaltype');
        var mainWrapper = $('.main-wrapper');
        var visible = false;

        main.on('click', function(e){
          e.preventDefault();
          e.stopPropagation();
          openOverlay(modalType);
          getData();
        });

        function getData(newHref) {

          var tmpHref = newHref ? newHref : href;

          var xhr = $.ajax({
            dataType: "html",
            url: tmpHref,
            cache: false,
            success: function(response){
              html = $(response).find("[data-modal]")[0].outerHTML;
              appendOverlay();
            }
          });
        }

        function openOverlay(type) {
          if(visible){ return false; }
          visible = true;

          var output = '<div class="overlay">';
          output+= 	'</div><!--/overlay-->';

          if(main.parent().hasClass('event-header-buttons')){
            main.parent().append(overlay = $(output));
            setTimeout(function(){
              $('.calendar-share-items').find('a:first').focus();
            },350);
          }else {
            if(!type){
              $('body').addClass('modal-open');
              $('body').append(overlay = $(output));
              mainWrapper.attr('aria-hidden', 'true');
            }else {
              $('body').addClass('modal-secondary');
              $('body').append(overlay = $(output));
              mainWrapper.attr('aria-hidden', 'true');
            }
          }

          overlay.fadeIn(250, function(){});

          setTimeout(function(){
            var closeBtn = overlay.find('.btn-close');
            closeBtn.on('click', function(){
              closeOverlay();
            })
          }, 1000);

          overlay.off('click').on('click', function(e){
            if($(e.target).is('[data-close]')){
              closeOverlay(type);
            }
          });
        }

        function appendOverlay() {
          visible = true;
          if(!visible){
            setTimeout(function(){
              appendOverlay();
            },100);
            return false;
          }

          var output = '';
          output+= '<div class="focus-trap" tabindex="0"></div>'
          output+= html;
          output+= '<div class="focus-trap" tabindex="0"></div>'
          overlay.html(output);

          bindEvents();
        }

        function closeOverlay(type) {
          visible = false;
          html = '';

          setTimeout(function(){
            if(!type){
              mainWrapper.removeAttr('aria-hidden');
              overlay.fadeOut(250, function(){
                $('body').removeClass("modal-open");
                $('.overlay').remove();
                overlay.remove();
                main.focus();
              });
            }else {
              mainWrapper.removeAttr('aria-hidden');
              overlay.fadeOut(250, function(){
                $('body').removeClass("modal-secondary");
                overlay.remove();
                main.focus();
              });
            }
          }, 250);
        }

        function bindEvents() {
          var closeBtn = overlay.find('.btn-close:first');
          var firstItem = overlay.find('.focus-trap:first');
          var lastItem = overlay.find('.focus-trap:last');
          var parent = overlay.parent();

          closeBtn.focus();

          tabFocusTrap(firstItem, lastItem, closeBtn, parent);

          $(document).on('keyup.modal', function(e){
            if(e.code === 'Escape' || e.which == 27 ){
              closeOverlay();
              $(document).off("keyup.modal");
            }
          });

          $(document).on('click.modal', function(e){
            if($(e.target).parents('.overlay').length == 0){
              closeOverlay();
            }
          });

          $wpm.bindObjects(overlay);
        }

        function tabFocusTrap(firstItem, lastItem, close, parent){
          if(!parent.hasClass('event-header-buttons')){
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
                  lastItem.focus();
                }
              });
            });
          }else {
            $(document).on('keyup', function(e){
              lastItem.on('keyup', function(e){
                if(e.keyCode == 9 || e.which == 9) {
                  e.preventDefault();
                  $('.calendar-share-items li>a:first').focus();
                }
              });

              firstItem.on('keyup', function(e){
                if(e.keyCode == 9 || e.which == 9 && e.keyCode == 16 || e.which == 16) {
                  e.preventDefault();
                  $('.calendar-share-items li>a:last').focus();
                }
              });
            });
          }
        }
      });
    }
  });
}(window.jQuery);
/* window.jQuery to end !function */
