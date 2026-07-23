(function ($) {
  $.fn.YearPicker = function (options) {
    let settings = $.extend({
      startYear: new Date().getFullYear(),
      format: 'yyyy',
      onSelect: null
    }, options);

    return this.each(function () {
      let $input = $(this);
      let $container = $('<div class="year-picker ui-widget ui-widget-content ui-corner-all"></div>').hide();

      // Template Header (Mirip MonthPicker)
      let header = `
                <div class="ui-widget-header month-picker-header ui-corner-all">
                    <table class="month-picker-year-table">
                        <tr>
                            <td class="month-picker-previous"><a class="prev-12 ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" style="cursor: pointer;"><span class="ui-button-icon-primary ui-icon ui-icon-circle-triangle-w"></span><span class="ui-button-text"></span></a></td>
                            <td class="month-picker-title"><a class="year-range"></a></td>
                            <td class="month-picker-next"><a class="next-12 ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" style="cursor: pointer;"><span class="ui-button-icon-primary ui-icon ui-icon-circle-triangle-e"></span><span class="ui-button-text"></span></a></td>
                        </tr>
                    </table>
                </div>
                <div class="year-grid-container"><table class="month-picker-month-table"></table></div>
            `;

      $container.html(header).appendTo('body');
      let currentBaseYear = parseInt($input.val()) || settings.startYear;

      function renderYears(baseYear) {
        let $table = $container.find('.month-picker-month-table').empty();
        let startAt = baseYear - 4; // Meniru pola MonthPicker (12 tahun)

        $container.find('.year-range').text(`${startAt} - ${startAt + 11}`);

        for (let i = 0; i < 3; i++) {
          let $tr = $('<tr></tr>').appendTo($table);
          for (let j = 0; j < 4; j++) {
            let year = startAt + (i * 4) + j;
            let $td = $(`<td><a class="ui-button ui-state-default">${year}</a></td>`).appendTo($tr);

            $td.find('a').on('click', function () {
              $input.val(year).trigger('change');
              if (settings.onSelect) settings.onSelect(year);
              $container.fadeOut();
            }).hover(
              function () { $(this).addClass('ui-state-hover'); },
              function () { $(this).removeClass('ui-state-hover'); }
            );

            if (year == $input.val()) $td.find('a').addClass('ui-state-active');
          }
        }
      }

      // Navigasi 12 Tahun (Sesuai i18n prev12Years/next12Years) 
      $container.find('.prev-12').click((e) => {
        e.preventDefault();
        e.stopPropagation();
        currentBaseYear -= 12;
        renderYears(currentBaseYear);
      });
      $container.find('.next-12').click((e) => {
        e.preventDefault();
        e.stopPropagation();
        currentBaseYear += 12;
        renderYears(currentBaseYear);
      });

      // Tampilkan/Sembunyikan
      $input.on('click', function () {
        $('.year-picker').not($container).hide(); // Close others
        let offset = $input.offset();
        $container.css({
          top: offset.top + $input.outerHeight(),
          left: offset.left,
          position: 'absolute',
          zIndex: 9999
        });
        renderYears(currentBaseYear);
        $container.fadeIn();
      });

      $(document).on('mousedown', function (e) {
        if (!$container.is(e.target) && $container.has(e.target).length === 0 && !$input.is(e.target)) {
          $container.fadeOut();
        }
      });
    });
  };
})(jQuery);