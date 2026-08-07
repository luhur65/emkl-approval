/**
 * Ekstensi jqGrid untuk pencarian global (satu kotak pencarian yang
 * mencari ke semua kolom sekaligus, groupOp "OR"), melengkapi filterToolbar
 * bawaan jqGrid (pencarian per-kolom, groupOp "AND"). Diadaptasi dari pola
 * initGlobalSearch() di project belajarci4 (app/Views/layouts/app.php).
 *
 * Keduanya mengirim parameter `filters` dengan format standar jqGrid:
 * {"groupOp":"AND|OR","rules":[{"field":"...","op":"cn","data":"..."}]}
 * sehingga backend (PHP) cukup punya satu jalur pemrosesan filter.
 *
 * Membutuhkan opsi grid `toolbar: [true, "top"]` supaya jqGrid menyediakan
 * div `#t_<gridId>` sebagai tempat menaruh kotak pencarian global.
 *
 * Opsi:
 * - beforeSearch(grid): dipanggil sebelum reload, mis. utk clear toolbar filter.
 * - reload(grid): cara memuat ulang data. Default: trigger('reloadGrid') bawaan
 *   jqGrid. Grid lazy-loading (datatype:'local' + lazyLoadingGridMonolith.js)
 *   harus mengoper callback custom di sini krn reloadGrid bawaan tdk memuat
 *   ulang dari server.
 */
$.jgrid.extend({
    globalSearch: function (options) {
        var settings = $.extend({
            beforeSearch: function () {},
            reload: null
        }, options || {});
        var grid = $(this);
        var gridId = grid[0].id;
        var inputId = gridId + '_searchText';
        var timer = null;

        $('#t_' + $.jgrid.jqID(gridId)).html(
            '<form class="form-inline">' +
                '<div class="form-group w-100 px-2" id="titlesearch">' +
                    '<label for="' + inputId + '" style="font-weight: normal !important;">Search : </label>' +
                    '<input type="text" class="form-control form-control-sm global-search" id="' + inputId + '" placeholder="Search" autocomplete="off">' +
                '</div>' +
            '</form>'
        );

        $(document)
            .off('input.globalSearch', '#' + $.jgrid.jqID(inputId))
            .on('input.globalSearch', '#' + $.jgrid.jqID(inputId), function () {
                var searchText = $(this).val();

                clearTimeout(timer);
                timer = setTimeout(function () {
                    // Panggil dulu sblm membangun filters: beforeSearch (biasanya
                    // membersihkan toolbar filter) jg menulis ke postData.filters,
                    // jadi kalau dipanggil belakangan filters pencarian global ini
                    // akan langsung tertimpa kosong lagi.
                    settings.beforeSearch.call(grid[0], grid);

                    var colModel = grid.jqGrid('getGridParam', 'colModel');
                    var rules = colModel
                        .filter(function (cm) {
                            return cm.search !== false &&
                                (cm.stype === undefined || cm.stype === 'text' || cm.stype === 'select');
                        })
                        .map(function (cm) {
                            return { field: cm.name, op: 'cn', data: searchText };
                        });

                    grid.jqGrid('setGridParam', {
                        search: searchText !== '' && rules.length > 0,
                        postData: {
                            filters: searchText === '' ? '' : JSON.stringify({ groupOp: 'OR', rules: rules })
                        }
                    });

                    if ($.jgrid.isFunction(settings.reload)) {
                        settings.reload(grid);
                    } else {
                        grid.trigger('reloadGrid', [{ page: 1, current: true }]);
                    }
                }, 500);
            });

        return this;
    },

    clearGlobalSearch: function () {
        var gridId = $(this).jqGrid('getGridParam', 'id');
        $('#' + $.jgrid.jqID(gridId) + '_searchText').val('');

        return this;
    }
});
