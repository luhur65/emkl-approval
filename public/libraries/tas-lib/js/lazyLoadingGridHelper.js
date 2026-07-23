/**
 * lazyGridHelper.js
 * Helper functions untuk operasi grid dengan lazy loading
 * Dipakai di semua modul
 */

/**
 * Refresh grid dengan reset penuh lazy loading state
 * @param {object} $grid - jQuery object grid
 * @param {string} apiUrl - URL endpoint grid
 */
function refreshLazyGrid($grid, apiUrl) {
    cachedData = {};
    loading = false;
    loadingQueue = [];
    minPageLoaded = 1;
    maxPageLoaded = 1;
    currentFilters = null;

    $grid.jqGrid('clearGridData');
    let freshPostData = $grid.jqGrid('getGridParam', 'postData');
    loadGridData(
        '#' + $grid.attr('id'),
        apiUrl,
        freshPostData,
        1,
        $grid.jqGrid('getGridParam', 'rowNum'),
        'down',
        'reload'
    );
}

/**
 * Hapus row dari grid tanpa reload penuh, lalu pindah selection
 * @param {object} $grid - jQuery object grid
 * @param {string|number} selectedId - ID row yang dihapus
 */
function deleteLazyRow($grid, selectedId) {
    let allIds = $grid.jqGrid('getDataIDs');
    let currentIndex = allIds.indexOf(String(selectedId));

    // Tentukan baris yang akan diselect setelah delete
    let nextId = null;
    if (currentIndex < allIds.length - 1) {
        nextId = allIds[currentIndex + 1];
    } else if (currentIndex > 0) {
        nextId = allIds[currentIndex - 1];
    }

    // Hapus dari grid
    $grid.jqGrid('delRowData', selectedId);

    // Hapus dari cache lazy loading
    if (typeof cachedData !== 'undefined') {
        for (let pg in cachedData) {
            cachedData[pg] = cachedData[pg].filter(row => String(row.id) !== String(selectedId));
        }
    }

    // Pindahkan selection
    if (nextId) {
        $grid.jqGrid('setSelection', nextId);
    }
}