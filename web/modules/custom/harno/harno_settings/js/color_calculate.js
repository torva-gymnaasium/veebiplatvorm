(function ($, Drupal) {
  Drupal.behaviors.harnoSettingsColorCalculate = {
    attach: function (context, settings) {
      var color_main_object = $('#edit-color-main');
      var color_additional_object = $('#edit-color-additional');

      $( '.form-color' ).change(function() {
        var color_main = color_main_object.val();
        var color_additional = color_additional_object.val();
        var color_lighter = harnoSettingsColorLightening(color_main, 90);

        $('#edit-color-lighter').val(color_lighter);
        $('#edit-color-main-code').html(color_main);
        $('#edit-color-lighter-code').html(color_lighter);
        $('#edit-color-additional-code').html(color_additional);

      });
      function harnoSettingsColorLightening(hex, percent) {
        return '#' + _(hex.replace('#', '')).chunk(2)
          .map(v => parseInt(v.join(''), 16))
          .map(v => ((0 | (1 << 8) + v + (256 - v) * percent / 100).toString(16))
            .substr(1)).join('');
      }
    }
  };
})(jQuery, Drupal);
