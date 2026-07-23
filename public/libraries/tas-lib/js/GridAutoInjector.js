(function($) {
    if (!$.fn.jqGrid) return;
    
    var originalJqGrid = $.fn.jqGrid;
    
    $.fn.jqGrid = function() {
        if (arguments.length > 0 && typeof arguments[0] === 'object') {
            var gridOptions = arguments[0];
            var $grid = this;
            var gridId = $grid.attr('id');
            
            if (gridId && gridOptions.colModel && gridOptions.gridPreference === true) {
                // Buat unique key berdasarkan URL path + Grid ID
                var pathKey = window.location.pathname.replace(/[^a-zA-Z0-9]/g, '_');
                var prefKey = pathKey + '_' + gridId;
                
                // 1. Simpan salinan murni dari colModel aslinya sebelum diganggu
                var baseColModel = JSON.parse(JSON.stringify(gridOptions.colModel));
                var savedPrefs = null;
                
                // 2. Baca preferensi grid dari cache secara sinkron
                if (typeof GridPreferenceManager !== 'undefined') {
                    savedPrefs = GridPreferenceManager.loadSync(prefKey);
                    if (savedPrefs && savedPrefs.length > 0) {
                        // Terapkan modifikasi urutan, lebar, dan hide/show langsung ke colModel
                        gridOptions.colModel = GridPreferenceManager.apply(gridOptions.colModel, savedPrefs);
                    }
                }
                
                // 3. Panggil jqGrid original dengan opsi yang sudah dimodifikasi 
                var res = originalJqGrid.apply(this, arguments);
                
                // 4. Setelah grid selesai dirender (menggunakan timeout tipis)
                setTimeout(function() {
                    // Background sync dari server khusus untuk device baru (akan tercache untuk refresh berikutnya)
                    if (typeof GridPreferenceManager !== 'undefined') {
                        GridPreferenceManager.load(prefKey);
                    }
                    
                    // Inject panel pengaturan kolom di toolbar
                    if (typeof ColumnSettingsManager !== 'undefined') {
                        ColumnSettingsManager.init('#' + gridId, prefKey, baseColModel);
                    }
                    // Inject detektor event resizeStop agar width tersimpan otomatis
                    if (typeof GridPreferenceManager !== 'undefined') {
                        GridPreferenceManager.attach('#' + gridId, prefKey);
                    }
                }, 300);
                
                return res;
            }
        }
        
        // Pemanggilan method biasa (bukan inisialisasi)
        return originalJqGrid.apply(this, arguments);
    };
    
    // Copy semua properti static milik jqGrid asli (seperti jqGrid.extend dll)
    Object.assign($.fn.jqGrid, originalJqGrid);
})(jQuery);
