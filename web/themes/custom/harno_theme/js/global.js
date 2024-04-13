
(function ($,Drupal) {
  'use strict';
  Drupal.behaviors.harno_theme_message_close = {
    attach: function (context) {
      $('.btn-notification-close').click(function (event) {
        event.preventDefault();
        $(this).parents('[data-alert]:first').fadeOut('slow', function () {
          $(this).remove();
        });
      });
    }
  };
}(jQuery, Drupal));
(function (Drupal, $, once, window, drupalSettings) {
  $( document ).ready(function() {
    var active_link = $(".side-menu").children("li").children('.active');
    if (active_link){
      if ($(window).width() > 992) {
        if (!active_link.parent().hasClass('has-dropdown')&& !active_link.parent().hasClass('do-nothing')) {
          active_link.parent().toggleClass('has-dropdown').toggleClass('open');
        }
      }
      else{
        if (!active_link.parent().hasClass('has-dropwdon') && !active_link.parent().hasClass('open')) {
          active_link.parent().addClass('has-dropdown').addClass('open');
        }
      }
      var debounce;
      $( window ).resize(function() {
        clearTimeout(debounce);
        debounce = setTimeout(function(){
          if ($(window).width() > 992) {
            if (active_link.parent().hasClass('do-nothing')){
              active_link.parent().removeClass('has-dropdown').removeClass('open');
            }
            // active_link.parent().removeClass('has-dropdown').removeClass('open');
          }
          else{
            if ( active_link.parent().hasClass('has-dropdown')==false){
              active_link.parent().addClass('has-dropdown').addClass('open');
            }
          }
        }, 300);

      });
    }
    // active_link.parentElement.toggleClass('has-dropdown');
  });

  Drupal.behaviors.harno_theme_ajax_before_send = {
    attach: function (context, settings) {
      'use strict';
      /**
       * Prepare the Ajax request before it is sent.
       *
       * @param {XMLHttpRequest} xmlhttprequest
       * @param {object} options
       * @param {object} options.extraData
       */
      Drupal.Ajax.prototype.beforeSend = function (xmlhttprequest, options) {
        // For forms without file inputs, the jQuery Form plugin serializes the
        // form values, and then calls jQuery's $.ajax() function, which invokes
        // this handler. In this circumstance, options.extraData is never used. For
        // forms with file inputs, the jQuery Form plugin uses the browser's normal
        // form submission mechanism, but captures the response in a hidden IFRAME.
        // In this circumstance, it calls this handler first, and then appends
        // hidden fields to the form to submit the values in options.extraData.
        // There is no simple way to know which submission mechanism will be used,
        // so we add to extraData regardless, and allow it to be ignored in the
        // former case.
        if (this.$form) {
          options.extraData = options.extraData || {};

          // Let the server know when the IFRAME submission mechanism is used. The
          // server can use this information to wrap the JSON response in a
          // TEXTAREA, as per http://jquery.malsup.com/form/#file-upload.
          options.extraData.ajax_iframe_upload = '1';

          // The triggering element is about to be disabled (see below), but if it
          // contains a value (e.g., a checkbox, textfield, select, etc.), ensure
          // that value is included in the submission. As per above, submissions
          // that use $.ajax() are already serialized prior to the element being
          // disabled, so this is only needed for IFRAME submissions.
          var v = $.fieldValue(this.element);
          if (v !== null) {
            options.extraData[this.element.name] = v;
          }
        }

        // Disable the element that received the change to prevent user interface
        // interaction while the Ajax request is in progress. ajax.ajaxing prevents
        // the element from triggering a new request, but does not prevent the user
        // from changing its value.
        $(this.element).prop('disabled', true);

        if (!this.progress || !this.progress.type) {
          return;
        }

        var progressIndicatorMethod = 'setProgressIndicator' + this.progress.type.slice(0, 1).toUpperCase() + this.progress.type.slice(1).toLowerCase();
        if (progressIndicatorMethod in this && typeof this[progressIndicatorMethod] === 'function') {
          this[progressIndicatorMethod].call(this);
        }
      };
    }
  };
  /**
   * Overrides the throbber progress indicator.
   */
  Drupal.behaviors.harno_theme_set_progress_indicator_throbber = {
    attach: function (context, settings) {
      Drupal.Ajax.prototype.setProgressIndicatorThrobber = function () {
        // 'ajax-progress' class removed near 'ajax'progress'throbber' to align loader to the centre
        this.progress.element = $('<div class="row"><div class="col-12 md-12 sm-12"><div class="ajax-progress-throbber"><div class="ajax-loader"><div class="spinner-wrapper"><div class="spinner-border" role="status"><span class="sr-only">" + Drupal.t("Loading&nbsp;&hellip;") + "</span></div><!--/spinner-border--> </div><!--/spinner-wrapper--></div></div></div></div>');
        $('article').before(this.progress.element);
      };
    }
  };
  Drupal.behaviors.harno_theme_ajax_progress_throbber = {
    attach: function (context, settings) {
      Drupal.theme.ajaxProgressIndicatorFullscreen = function () {
        return "<div class=\"spinner-wrapper\"><div class=\"spinner-border\" role=\"status\"><span class=\"sr-only\">" + Drupal.t('Loading&nbsp;&hellip;') + "</span></div><!--/spinner-border--> </div><!--/spinner-wrapper-->";
      };
      Drupal.Ajax.prototype.setProgressIndicatorFullscreen = function () {
        this.progress.element = $(Drupal.theme('ajaxProgressIndicatorFullscreen'));
        $('.content-block--filters').after(this.progress.element); //otsingu jaoks
      };
    }
  };
  Drupal.behaviors.harno_theme_search_changer = {
    attach(context) {
      jQuery(once('mymodule-ajax', document, context)).ajaxComplete(function (e, xhr, settings) {
        var searchSelector = document.querySelector(".search_type_checkbox");
        console.log(searchSelector);
        var url = settings.url;
        if (url.indexOf('search_api_autocomplete')=='-1') {
          if (searchSelector) {
            searchSelector.focus();
          }
        }
      });
    }
  };
  jQuery(document).ajaxSuccess(function (event,xhr,settings){
    var invalidElement = document.querySelector('[aria-invalid]');
    if (invalidElement){
      var drupal_message = document.querySelector('#drupal-live-announce');
      if (drupal_message){
        drupal_message.remove();
      }
      invalidElement.parentElement.classList.toggle('is-focused');
      invalidElement.classList.toggle('focus-visible');

      $wpm.bindObjects();
      invalidElement.focus();
    }
    // var webformSelector = document.querySelector("#webformError");
    // if (webformSelector) {
    //   webformSelector.focus();
    // }

  });
  jQuery(document).ajaxComplete(function(event, xhr, settings) {

    setTimeout(function(){
      $wpm.bindObjects();
    }, 120);

  }
  );
})(Drupal, jQuery, once, this, drupalSettings);
