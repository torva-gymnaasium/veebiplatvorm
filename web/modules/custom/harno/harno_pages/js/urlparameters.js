/* !function to prevent library conflicts */
!function (Drupal, $, once) {
  Drupal.behaviors.buttonBehaviours = Drupal.behaviors.buttonBehaviours || {};
  Drupal.behaviors.buttonBehaviours = {
    attach(context) {
        var $form = $('form[data-drupal-selector="gallery-filter-form"]');
          $(once('trigger-filter', $('input:radio', $form), context)).on('change', function (event) {
          var $name = $(this).attr('name');
          if ($name == 'days') {
            var $start = $('#edit-date-start');
            var $start_mobile = $('#edit-date-start-mobile');
            var $end = $('#edit-date-end');
            var $end_mobile = $('#edit-date-end-mobile');
            $start.parent().removeClass('is-focused');
            $start_mobile.parent().removeClass('is-focused');
            $end.parent().removeClass('is-focused');
            $end_mobile.parent().removeClass('is-focused');
            $end.val('');
            $end_mobile.val('');
            $start.val('');
            $start_mobile.val('');
          }
        });
        var $form_mobile = $('form[data-drupal-selector="gallery-filter-form-mobile"]');
          $(once('trigger-filter', $('input:radio', $form_mobile), context)).on('change', function (event) {
          var $name = $(this).attr('name');
          if ($name=='days_mobile') {
            var $start = $('#edit-date-start');
            var $start_mobile = $('#edit-date-start-mobile');
            var $end = $('#edit-date-end');
            var $end_mobile = $('#edit-date-end-mobile');
            $start.parent().removeClass('is-focused');
            $start_mobile.parent().removeClass('is-focused');
            $end.parent().removeClass('is-focused');
            $end_mobile.parent().removeClass('is-focused');
            $end.val('');
            $end_mobile.val('');
            $start.val('');
            $start_mobile.val('');
          }
        });
        var $fields = [
          'input:text#edit-date-start',
          'input:text#edit-date-start-mobile',
          'input:text#edit-date-end',
          'input:text#edit-date-end-mobile',
        ]
        $.each($fields, function (index, value) {
          if (value.indexOf("mobile") >= 0){
            $(once('trigger-filter', $(value, $form_mobile), context)).on('input', function (event) {

             var $days = [
                'days',
                'days-mobile'
              ];
              $.each($days, function (index, value) {
                $('#edit-' + value + '-all').prop('checked', true);
              });
            });
            $(value, $form_mobile).on('change', function (event) {
    
             var $days = [
                'days',
                'days-mobile'
              ];
              $.each($days, function (index, value) {
                $('#edit-' + value + '-all').prop('checked', true);
              });
            });
          }
          else {
              $(once('trigger-filter', $(value, $form), context)).on('input', function (event) {
            var  $days = [
                'days',
                'days-mobile'
              ];
              $.each($days, function (index, value) {
                $('#edit-' + value + '-all').prop('checked', true);
              });
            });
            $(value, $form).on('change', function (event) {
    
            var  $days = [
                'days',
                'days-mobile'
              ];
              $.each($days, function (index, value) {
                $('#edit-' + value + '-all').prop('checked', true);
              });
            });
          }
        });
      
    }
  }
  $.fn.formFilter = function () {


    $(this).each(function () {
      $.formFilter.init($(this));
    });

  };


  $.fn.formFilter = function () {
    $.formFilter.initialize($(this));
  };

  $.formFilter = {
    options: {},
    templates: {},
    values: {},
    containers: {
      activeFilters: ".gallery-filter-form"
    },
    initialize: function (form) {

      this.options.form = form;
      this.options.inputs = this.options.form.find(".js-range-slider, input[type='radio'],input[type='text'], input[type='checkbox'], select");
      this.bindFilters();
      this.bindHashChange();
      this.restoreCheckedStatus();
    },
    bindFilters: function () {
      var self = this;
      self.options.inputs.on("change", function (e) {
        var name = $(this).attr('name');
        var val = $(this).val();
        var relations = {
          'edit-date-start': '#edit-date-start-mobile',
          'edit-date-end': '#edit-date-end-mobile',
          'date_start_mobile': '#edit-date-start',
          'date_end_mobile': '#edit-date-end',
          'date_start': '#edit-date-start-mobile',
          'date_end': '#edit-date-end-mobile',
          'departments': '#worker-department-mobile',
          'departments_mobile':'#worker-department',
          'positions': '#worker-position-mobile',
          'positions_mobile':'#worker-position',
        }

        if (relations[name]) {
          self.options.form.find(relations[name]).val(val);
        }
      });
      self.options.inputs.on("input", function (e) {
        e.preventDefault();
        var name = $(this).attr('name');
        var val = $(this).val();
        var relations = {
          'gallerySearch': '#edit-gallerysearchmobile',
          'gallerySearchMobile': '#edit-gallerysearch',
          'calendarSearch': '#edit-calendarsearchmobile',
          'calendarSearchMobile': '#edit-calendarsearch',
          'newsSearch': '#edit-newssearchmobile',
          'newsSearchMobile': '#edit-newssearch',
          'departments': '#worker-department-mobile',
          'departments_mobile':'#worker-department',
          'positions': '#worker-position-mobile',
          'positions_mobile':'#worker-position',
          'years': '#gallery-years',
          'contactsSearchMobile': '#edit-contactssearchmobile',
          'contactsSearch': '#edit-contactssearch',
          'article_type': '#article_type_mobile',
          'event_type': '#event_type_mobile',
          'edit-date-start': '#edit-date-start-mobile',
          'edit-date-end': '#edit-date-end-mobile',
          'date_start_mobile': '#edit-date-start',
          'date_end_mobile': '#edit-date-end',
          'date_start': '#edit-date-start-mobile',
          'date_end': '#edit-date-end-mobile',
        }
        if (name.includes('years[')){
          var regex = / *\[? *.(?!((.*\[))).*\] */g;
          var years_filter = name.match(regex)[0];
          var mobile_element = document.getElementsByName("years-mobile"+years_filter);
          mobile_element[0].checked=$(this).is(':checked');
        }
        if (name == 'days'){
          var mobile_element = document.getElementById('edit-days-mobile-'+val);
          mobile_element.checked = true;
        }
        if (name == 'days_mobile'){
          var mobile_element = document.getElementById('edit-days-'+val);
          mobile_element.checked = true;
        }
        if (name.includes('years-mobile[')){
          var regex = / *\[? *.(?!((.*\[))).*\] */g;
          var years_filter = name.match(regex)[0];
          var desktop_element = document.getElementsByName("years"+years_filter);
          desktop_element[0].checked=$(this).is(':checked');
        }
        if (relations[name]) {
          self.options.form.find(relations[name]).val(val);
        }

        if ($(this)[0].id.includes('edit-article-type')){

          if ($("input[id~='article_type']")) {
            var toFind = '#edit-article-type-mobile-' + $(this)[0].value;
            var boxToCheck = self.options.form.find(toFind);
            boxToCheck.prop('checked', $(this)[0].checked);
          }
          if ($("input[id~='article_type_mobile']")) {
            var toFind = '#edit-article-type-' + $(this)[0].value;
            var boxToCheck = self.options.form.find(toFind);
            boxToCheck.prop('checked', $(this)[0].checked);
          }
        }
        if ($(this)[0].id.includes('edit-event-type')){

          if ($("input[id~='event-type']")) {
            var toFind = '#edit-event-type-mobile-' + $(this)[0].value;
            var boxToCheck = self.options.form.find(toFind);
            boxToCheck.prop('checked', $(this)[0].checked);
          }
          if ($("input[id~='event-type-moile']")) {
            var toFind = '#edit-event-type-' + $(this)[0].value;
            var boxToCheck = self.options.form.find(toFind);
            boxToCheck.prop('checked', $(this)[0].checked);
          }
        }
        self.pushURL();
      });

      self.options.inputs.on("change", function (e) {

        e.preventDefault();

        if ($(this).attr('name') === 'sort') {
          self.options.form.find('#sort-text').text($(this).next('label').text().toLowerCase().trim());
        }
        self.pushURL();
      });

      self.bindHashChange();
    },

    bindDeleteFilter: function () {
      var self = this;

      $(self.containers.activeFilters).find('a').unbind("click").bind("click", function (e) {
        e.preventDefault();
      });

      $(self.containers.activeFilters).find('.delete-filter').unbind("click").bind("click", function (e) {
        e.preventDefault();

        var rel = $(this).attr('rel');

        self.options.inputs.filter('[value="' + rel + '"]').trigger('click');
      });

      $(self.containers.activeFilters).find('.delete-all').unbind("click").bind("click", function (e) {
        e.preventDefault();

        var inputs = self.options.inputs.filter(":checked");

        inputs.trigger('click');
      });
    },

    pushURL: function (from, to) {
      var self = this;
      var inputs = self.options.inputs.filter(":checked, [type='text'], input[type='checkbox'], select");
      var hash = '';
      var hashArray = {};

      if (from || to) {
        var priceRange = "min_price=" + from + "&max_price=" + to
        hashArray.min = from
        hashArray.max = to
      }

      inputs.each(function () {
        var input = $(this);
        var type = input.attr('type');
        var name = input.attr("name");
        var value = input.val() ? input.val().replace(';', '-') : undefined;
        if(name == 'positions'){
          if (value) {
            hashArray[name] = value;
          }
        }
        else if(name == 'positions_mobile'){
          if (value) {
            hashArray[name] = value;
          }
        }
        else if(name == 'departments'){
          if (value) {
            hashArray[name] = value;
          }
        }
        else if(name == 'departments_mobile'){
          if (value) {
            hashArray[name] = value;
          }
        }
        else if (type!='checkbox') {

          if (value) {
            if (hashArray[name]) {
              hashArray[name] = hashArray[name] + "," + value;
            } else {
              hashArray[name] = value;
            }
          }
        }
        else{
          var checked = input.prop('checked');
          if (checked) {
            if (value) {
              if (name) {
                if (hashArray[name]) {
                  hashArray[name] = hashArray[name] + "," + value;
                } else {
                  hashArray[name] = value;
                }
              }

            }
          }
        }


      });
      for (var i in hashArray) {
        if (hash !== "") {
          hash += "&"
        }
        hash += i + "=" + hashArray[i];
      };

      window.history.replaceState(undefined, undefined, '?' + hash);
      $(window).trigger('querychange');

    },

    hashChangeEvent() {
      var hash = window.location.search;
      this.options.hashArray = this.getParameters(hash);
      this.options.hash = hash;
      this.restoreCheckedStatus();
    },

    bindHashChange: function () {
      var self = this;

      $(window).on("querychange", function (e) {
        e.preventDefault();
        self.hashChangeEvent();
      });
    },

    restoreCheckedStatus() {
      var self = this;
      var hash = window.location.search;
      this.options.hashArray = this.getParameters(hash);
      self.options.inputs.each(function () {
        var input = $(this);
        var name = input.attr("name");
        var value = input.val();

        var filterValues = self.options.hashArray[name];

        if (filterValues) {
          //if (filterValues && (self.options.hashArray[name] == value)) {
          filterValues = filterValues.split(',');
          filterValues.forEach(function (item) {
            if (item === value) {
              input.prop("checked", true);
              input.parent().addClass("active").addClass('is-focused');

            }
          });
        } else {
          var checked = input.attr('checked');
          if (checked=='checked'){}
          else {
            input.prop("checked", false);
            input.parent().removeClass("active");
          }
        }
      });

      this.options.inputs.filter('[name="sort"]:checked').each(function () {
        self.options.form.find('#sort-text').text($(this).next('label').text().toLowerCase().trim());
      });
    },
    getParameters(hash) {
     var params = {}
      var keyValuePairs = hash.substr(1).split('&');
      for (var x in keyValuePairs) {
        var split = keyValuePairs[x].split('=', 2);
        params[split[0]] = (split[1]) ? decodeURI(split[1]) : "";
      }
      return params;
    }
  }
  $.fn.filterRefresh = function(){

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
      $("input[name='date_start']").val('');
      $("input[name='date_end']").val('');
      $("input[name='date_start_mobile']").val('');
      $("input[name='date_end_mobile']").val('');
      $("button[id=edit-ready--2]").trigger("click");
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
  $(document).ready(function () {
    $("span[data-remove-item]").on('click',function(){

      var yearToRemove = $(this).attr("data-remove-item");
      $("input[name='years["+yearToRemove+"]']").trigger("click");
      // $("input[name='["+yearToRemove+"]']").trigger("click");
      // const url = new URL(window.location.href)
      // const urlObj = new URL(url);
      // const params = urlObj.searchParams
      // const $checks = $(':checkbox')
      // // on page load check the ones that exist un url
      // params.forEach((val, key) => $checks.filter('[name="' + key + '"]').prop('checked', true));
      //
      // $checks.change(function(){
      //   // append when checkbox gets checked and delete when unchecked
      //   if(this.checked){
      //     params.append(this.name, 'true')
      //   }else{
      //     params.delete('departments');
      //     params.delete(this.name);
      //   }
      //   window.location = urlObj.href;
      //
      // })
      //Remove from active filter bar
      //$(this.parentElement).remove();

    });
    $(window).on('load', function () {

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

        $("input[name='date_end']").val('').trigger('focusout');
        $("input[name='date_start']").val('').trigger('focusout');
        $("input[name='date_end_mobile']").val('').trigger('focusout');
        $("input[name='date_start_mobile']").val('').trigger('change').trigger('focusout');
        $("button[data-drupal-selectior='edit-ready']").trigger("click");
        //Remove from active filter bar
        $(this.parentElement).remove();

      });
      $("input[data-remove-checkbox-item]").on('click',function() {
        var yearToRemove = $(this).attr("data-remove-checkbox-item");
        $("input[name='years[" + yearToRemove + "]']").trigger("click");
        $(this.parentElement).remove();
      });
      $.fn.formFilter = function () {
        $(this).each(function () {
          $.formFilter.init($(this));
        });
      };
    })
    $('[role="filter"]').formFilter();
  });
}(Drupal, jQuery, once);
