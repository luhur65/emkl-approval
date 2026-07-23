/* =======================================================================
 * Variabel Global
 * ======================================================================= */
var page = 1; // current page
var totalPages = 1; // total pages
var totalRecord = 0; // total records
var loading = false; // loading status
var loadingQueue = []; // TAMBAHAN: Queue untuk request pending
var selectedPage = null; // selected page
var selectedRowIndex = null; // selected row index
var cachedData = {}; // cached data
var minPageLoaded = 1; // min page loaded
var maxPageLoaded = 1; // max page loaded
var lastScrollTop = 0; // last scroll top
var currentFilters = null; // TAMBAHAN: Track filter changes

var WINDOW_PAGES = 3; // jumlah page yg tampil
var rowsPerPage = 50; // rows per page
var gapPage = 30; // gap page

// var SCROLL_BUFFER = 1;
var currentViewPage = 1;
var prefetchedServerPages = new Set();

// Fungsi Throttling dengan queue support
function throttle(func, limit) {
    let inThrottle;
    let lastArgs;

    return function () {
        const args = arguments;
        const context = this;

        if (!inThrottle) {
            func.apply(context, args);
            inThrottle = true;

            setTimeout(() => {
                inThrottle = false;
                // Execute queued call if exists
                if (lastArgs) {
                    func.apply(context, lastArgs);
                    lastArgs = null;
                }
            }, limit);
        } else {
            // Queue the last call
            lastArgs = args;
        }
    }
}

/* =======================================================================
 * TAMBAHAN: Fungsi untuk Deteksi Perubahan Filter
 * ======================================================================= */
function hasFilterChanged(grid) {
    var rawNewFilters = grid.jqGrid('getGridParam', 'postData').filters;

    // 1. NORMALISASI: Anggap undefined, null, dan "" adalah SAMA
    var newFilters = (rawNewFilters === undefined || rawNewFilters === null) ? "" : rawNewFilters;
    var oldFilters = (currentFilters === undefined || currentFilters === null) ? "" : currentFilters;

    // FIX BUG: Jika pertama kali (currentFilters null), set tanpa return true
    if (currentFilters === null) {
        currentFilters = newFilters;
        return false;
    }

    // Bandingkan nilai yang sudah dinormalisasi
    if (oldFilters !== newFilters) {
        // Debugging untuk memastikan penyebab reset
        // console.warn(`Filter Change Detected: '${oldFilters}' vs '${newFilters}'`);

        currentFilters = newFilters;
        return true;
    }

    return false;
}

/* =======================================================================
 * TAMBAHAN: Fungsi untuk Reset State (saat filter berubah)
 * ======================================================================= */
function resetGridState(grid, resetFilters = false) {
    cachedData = {};
    minPageLoaded = 1;
    maxPageLoaded = 1;
    lastScrollTop = 0;
    loading = false;
    loadingQueue = [];

    // FIX BUG: Hanya reset currentFilters jika diminta
    if (resetFilters) {
        currentFilters = null;
    }

    grid.jqGrid('clearGridData');
}

/* =======================================================================
 * TAMBAHAN: Fungsi untuk Process Loading Queue
 * ======================================================================= */
function processLoadingQueue(gridId, api, accessToken, postData, rowsCount) {
    if (loadingQueue.length && !loading) {
        var serverPage = loadingQueue.shift();

        var pagesPerFetch = rowsCount === rowsPerPage ? 1 : 3; // atau simpan global
        var virtualPage = (serverPage - 1) * pagesPerFetch + 1;

        console.log(`Processing queued SERVER page ${serverPage} → virtual ${virtualPage}`);

        loadGridData(
            gridId,
            api,
            accessToken,
            postData,
            virtualPage,
            rowsCount,
            'down',
            'page',
            null,
            true
        );
    }
}


/* =======================================================================
 * Fungsi untuk memuat data dari API (Generik) - DIPERBAIKI
 * ======================================================================= */
// function loadGridData(
//     gridId,
//     api,
//     accessToken,
//     postData,
//     pageNumber,
//     rowsCount,
//     direction = 'down',
//     proses = 'page',
//     callback
// ) {
//     var grid = $(gridId);

//     // FIX BUG: Jangan cek filter saat proses reload/jump atau saat first load
//     if (proses !== 'reload' && proses !== 'jump' && minPageLoaded > 0 && hasFilterChanged(grid)) {
//         console.log('Filter changed, resetting grid state');
//         resetGridState(grid);
//         pageNumber = 1;
//         direction = 'down';
//         proses = 'reload';
//     }

//     // FIX 2: ANTI DUPLICATE REQUEST - Gunakan Queue
//     if (loading) {
//         if (!loadingQueue.includes(pageNumber)) {
//             loadingQueue.push(pageNumber);
//             console.log(`Page ${pageNumber} added to queue`);
//         }
//         return;
//     }

//     // RELOAD / JUMP
//     if (proses === 'reload' || proses === 'jump') {
//         resetGridState(grid);
//         minPageLoaded = pageNumber;
//         maxPageLoaded = pageNumber;

//         // FIX BUG: Set currentFilters saat reload
//         currentFilters = grid.jqGrid('getGridParam', 'postData').filters;
//     }

//     // FIX 3: CEK DUPLICATE - Jangan load page yang sudah ada di grid
//     var existingIds = grid.jqGrid('getDataIDs');

//     // Jika data page ini sudah ada di grid, skip
//     if (existingIds.length > 0 && cachedData[pageNumber] && proses !== 'reload') {
//         var firstIdInCache = cachedData[pageNumber][0]?.id;
//         if (firstIdInCache && existingIds.includes(firstIdInCache.toString())) {
//             console.log(`Page ${pageNumber} already loaded, skipping`);
//             if (callback) callback();
//             return;
//         }
//     }

//     // CACHE HIT (hanya jika belum ada di grid)
//     if (cachedData[pageNumber] && proses !== 'reload') {

//         console.log(`Loading page ${pageNumber} from cache`);
//         renderFromCache(grid, cachedData[pageNumber], direction, rowsCount, pageNumber);

//         // APPLY HIGHLIGHT untuk data baru
//         setTimeout(() => {
//             setHighlight(grid);
//         }, 50);

//         if (callback) callback();
//         return;
//     }

//     loading = true;

//     // TAMPILKAN LOADING INDICATOR
//     // if (proses === 'reload' || proses === 'jump') {
//     // }
//     $('.loaderGrid').removeClass('d-none');

//     var fullPostData = $.extend({}, postData, {
//         page: pageNumber,
//         limit: rowsCount,
//         sortIndex: grid.jqGrid('getGridParam', 'sortname'),
//         sortOrder: grid.jqGrid('getGridParam', 'sortorder'),
//         filters: grid.jqGrid('getGridParam', 'postData').filters
//     });

//     console.log(`Loading page ${pageNumber} from API (direction: ${direction})`);

//     $.ajax({
//         url: api,
//         type: "GET",
//         headers: {
//             'Authorization': `Bearer ${accessToken}`
//         },
//         data: fullPostData,
//         success: function (res) {
//             $('.loaderGrid').addClass('d-none');
//             totalPages = res.attributes.totalPages;
//             totalRecord = res.attributes.totalRows;

//             if (!res.data || !res.data.length) {
//                 loading = false;
//                 return;
//             }

//             // Simpan ke cache
//             cachedData[pageNumber] = res.data;

//             renderFromCache(grid, res.data, direction, rowsCount, pageNumber);

//             grid.jqGrid('setGridParam', { records: totalRecord });

//             if (callback) callback();

//             // APPLY HIGHLIGHT untuk data baru
//             setTimeout(() => {
//                 setHighlight(grid);
//             }, 50);
//         },
//         error: function (jqXHR, textStatus, errorThrown) {
//             console.error('Error loading grid data:', textStatus);
//         },
//         complete: function () {
//             loading = false;

//             // Sembunyikan LOADING INDICATOR
//             $('.loaderGrid').addClass('d-none');

//             // FIX 4: PROCESS QUEUE setelah selesai
//             processLoadingQueue(gridId, api, accessToken, postData, rowsCount);
//         }
//     });
// }

function loadGridData(
    gridId,
    api,
    accessToken,
    postData,
    pageNumber,
    rowsCount,
    direction = 'down',
    proses = 'page',
    callback,
    onlyCache = false // <--- PARAMETER BARU
) {
    var grid = $(gridId);


    // ... (Logic Reset State & Filter Change TETAP SAMA seperti sebelumnya) ...
    if (proses !== 'page' && !onlyCache && proses !== 'reload' && proses !== 'jump' && minPageLoaded > 0 && hasFilterChanged(grid)) {

        console.warn("RESET TRIGGERED BY FILTER CHANGE!", {
            old: currentFilters,
            new: grid.jqGrid('getGridParam', 'postData').filters
        });

        resetGridState(grid);
        pageNumber = 1;
        direction = 'down';
        proses = 'reload';
    }

    if (proses === 'reload') {
        resetGridState(grid); // Hapus cachedData, reset min/maxPage
        minPageLoaded = pageNumber;
        maxPageLoaded = pageNumber;
        currentFilters = grid.jqGrid('getGridParam', 'postData').filters;
        loading = false;
    }

    // KASUS 2: JUMP (Tombol End/Home/PageJump) -> JANGAN HAPUS CACHE
    if (proses === 'jump') {
        loading = true;

        // Bersihkan tampilan grid visual saja (barisnya), tapi memori tetap disimpan
        grid.jqGrid('clearGridData');

        // Kita HANYA reset pointer halaman, TAPI TIDAK MENGHAPUS cachedData
        currentFilters = grid.jqGrid('getGridParam', 'postData').filters;
        minPageLoaded = pageNumber;
        maxPageLoaded = pageNumber;

        // Karena grid kosong, posisi scroll pasti 0. Kita reset lastScrollTop.
        if (typeof lastScrollTop !== 'undefined') lastScrollTop = 0;

        // Matikan loading flag agar request jalan
        loading = false;
    }

    // Untuk Approval Status berubah di grid
    // if (proses === 'reload' || proses === 'jump') {
    //     resetGridState(grid); // Ini akan set loading = false dan cachedData = {}

    //     minPageLoaded = pageNumber;
    //     maxPageLoaded = pageNumber;
    //     currentFilters = grid.jqGrid('getGridParam', 'postData').filters;

    //     // Pastikan loading dimatikan paksa agar lolos pengecekan di bawah
    //     loading = false;
    // }

    // Cek Cache
    if (cachedData[pageNumber] && proses === 'page') {
        if (!onlyCache) { // Render hanya jika bukan mode pre-fetch
            renderFromCache(grid, cachedData[pageNumber], direction, rowsCount, pageNumber);
            // setTimeout(() => { setHighlight(grid); }, 50);
        }
        if (callback) callback();
        return;
    }

    // Logic Batching (Sama seperti sebelumnya)
    let pagesPerFetch = (pageNumber === 1) ? 3 : 1;
    let serverPage = Math.ceil(pageNumber / pagesPerFetch);
    let limitToSend = rowsCount * pagesPerFetch;

    // ... (Logic Duplicate Request Check TETAP SAMA) ...
    if (loading && !onlyCache) {
        if (!loadingQueue.includes(serverPage)) loadingQueue.push(serverPage);
        return;
    }

    if (onlyCache) {
        if (prefetchedServerPages.has(serverPage)) return;
        prefetchedServerPages.add(serverPage);
    }

    loading = true;
    if (!onlyCache) $('.loaderGrid').removeClass('d-none'); // Loader muncul cuma kalau user yg minta

    var fullPostData = $.extend({}, postData, {
        page: serverPage,
        limit: limitToSend,
        sortIndex: grid.jqGrid('getGridParam', 'sortname'),
        sortOrder: grid.jqGrid('getGridParam', 'sortorder'),
        filters: grid.jqGrid('getGridParam', 'postData').filters
    });

    console.log(`Fetching Batch (Silent: ${onlyCache}): ServerPage ${serverPage}`);

    $.ajax({
        url: api,
        type: "GET",
        headers: { 'Authorization': `Bearer ${accessToken}` },
        data: fullPostData,
        success: function (res) {
            $('.loaderGrid').addClass('d-none');
            
            // Support both API schema (attributes.totalRows) and Monolith schema (records)
            totalRecord = (res.attributes && res.attributes.totalRows !== undefined) ? res.attributes.totalRows : (res.records !== undefined ? res.records : 0);
            totalPages = Math.ceil(totalRecord / rowsCount);

            let dataToCache = res.data || res.rows;
            if (!dataToCache || !dataToCache.length) {
                loading = false;
                return;
            }

            // --- SLICING & CACHING ---
            let startClientPage = (serverPage - 1) * pagesPerFetch + 1;

            if (res.data) {
                // API Schema - potentially contains multiple pages
                for (let i = 0; i < pagesPerFetch; i++) {
                    let currentVirtualPage = startClientPage + i;
                    let startIdx = i * rowsCount;
                    let endIdx = startIdx + parseInt(rowsCount);
                    let chunkData = res.data.slice(startIdx, endIdx);

                    if (chunkData.length > 0) {
                        cachedData[currentVirtualPage] = chunkData;
                    }
                }
            } else {
                // Monolith Schema - usually contains only one page
                cachedData[pageNumber] = res.rows;
            }

            // --- RENDER LOGIC ---
            // Jika ini request biasa (user scroll), render page yg diminta.
            // Jika ini pre-fetch (onlyCache), JANGAN render apapun.
            if (!onlyCache) {
                if (cachedData[pageNumber]) {
                    renderFromCache(grid, cachedData[pageNumber], direction, rowsCount, pageNumber);
                    // setTimeout(() => { setHighlight(grid); }, 50);
                }
                // setHighlight(grid);
            } else {
                console.log("Pre-fetch complete. Data stored in cache.");
            }

            grid.jqGrid('setGridParam', { records: totalRecord });
            if (callback) callback();
        },
        complete: function () {
            loading = false;
            $('.loaderGrid').addClass('d-none');
            var freshNextPostData = grid.jqGrid('getGridParam', 'postData');
            processLoadingQueue(gridId, api, accessToken, freshNextPostData, rowsCount);
        }
    });
}


/* =======================================================================
 * Fungsi Generik untuk Menyiapkan Event Scroll Lazy Load - DIPERBAIKI
 * ======================================================================= */
// function setupLazyLoadScrollHandler(gridId, apiUrl, accessToken, postData) {

//     var grid = $(gridId);

//     var throttledScroll = throttle(function () {
//         var bDiv = $(this);
//         var scrollTop = bDiv.scrollTop();
//         var viewHeight = bDiv.height();
//         var tableHeight = bDiv.find("table").height();
//         var rowNum = grid.jqGrid('getGridParam', 'rowNum');
//         var rowHeight = grid.find('tr[id]').height() || 30;

//         // Jangan load jika sedang loading
//         if (loading) {
//             return;
//         }

//         // SCROLL DOWN
//         if (
//             scrollTop > lastScrollTop &&
//             scrollTop + viewHeight >= tableHeight - (gapPage * rowHeight) &&
//             maxPageLoaded < totalPages
//         ) {
//             var nextPage = maxPageLoaded + 1;

//             // FIX 5: Anti duplicate - cek apakah page ini sudah ada di grid
//             var existingIds = grid.jqGrid('getDataIDs');
//             if (cachedData[nextPage]) {
//                 var firstIdInNext = cachedData[nextPage][0]?.id;
//                 if (firstIdInNext && existingIds.includes(firstIdInNext.toString())) {
//                     console.log(`Page ${nextPage} already in grid, skipping scroll down`);
//                     lastScrollTop = scrollTop;
//                     return;
//                 }
//             }

//             loadGridData(
//                 gridId,
//                 apiUrl,
//                 accessToken,
//                 postData,
//                 nextPage,
//                 rowNum,
//                 'down',
//                 'page',
//                 function () {
//                     if (typeof executePostLoadTasks === 'function') {
//                         executePostLoadTasks(gridId);
//                     }
//                 }
//             );
//         }

//         // SCROLL UP
//         if (
//             scrollTop < lastScrollTop &&
//             scrollTop <= (gapPage * rowHeight) &&
//             minPageLoaded > 1
//         ) {
//             var prevPage = minPageLoaded - 1;

//             // FIX 6: Anti duplicate - cek apakah page ini sudah ada di grid
//             var existingIds = grid.jqGrid('getDataIDs');
//             if (cachedData[prevPage]) {
//                 var firstIdInPrev = cachedData[prevPage][0]?.id;
//                 if (firstIdInPrev && existingIds.includes(firstIdInPrev.toString())) {
//                     console.log(`Page ${prevPage} already in grid, skipping scroll up`);
//                     lastScrollTop = scrollTop;
//                     return;
//                 }
//             }

//             loadGridData(
//                 gridId,
//                 apiUrl,
//                 accessToken,
//                 postData,
//                 prevPage,
//                 rowNum,
//                 'up',
//                 'page',
//                 function () {
//                     if (typeof executePostLoadTasks === 'function') {
//                         executePostLoadTasks(gridId);
//                     }
//                 }
//             );
//         }

//         lastScrollTop = scrollTop <= 0 ? 0 : scrollTop;

//     }, 150); // FIX 7: Kurangi throttle dari 200ms ke 150ms

//     grid.parents(".ui-jqgrid-bdiv")
//         .off("scroll.virtual")
//         .on("scroll.virtual", throttledScroll);
// }

function setupLazyLoadScrollHandler(gridId, apiUrl, accessToken, postData) {
    var grid = $(gridId);

    var throttledScroll = throttle(function () {
        var bDiv = $(this);
        var scrollTop = bDiv.scrollTop();
        var viewHeight = bDiv.height();
        var tableHeight = bDiv.find("table").height();
        var rowNum = grid.jqGrid('getGridParam', 'rowNum');
        var rowHeight = grid.find('tr[id]').height() || 30;
        var currentPostData = grid.jqGrid('getGridParam', 'postData');
        var detectedPage = detectCurrentViewPage(grid);

        if (detectedPage !== currentViewPage) {
            currentViewPage = detectedPage;
            updateGridInfoFast(grid);
        }


        // Safety: kalau tabel lebih pendek dari viewport
        // if (tableHeight <= viewHeight) {
        //     lastScrollTop = scrollTop;
        //     return;
        // }

        // // Cegah mentok atas
        // if (scrollTop <= 0) {
        //     bDiv.scrollTop(SCROLL_BUFFER);
        //     lastScrollTop = SCROLL_BUFFER;
        //     return;
        // }

        // // Cegah mentok bawah
        // var maxScrollTop = tableHeight - viewHeight;
        // // ===== Cegah scroll "mati" di bawah TANPA motong baris terakhir =====
        // if (scrollTop >= maxScrollTop) {
        //     // Biarkan user MELIHAT baris terakhir
        //     // tapi geser sedikit ke atas agar scroll event tetap hidup
        //     var safeBottom = maxScrollTop - Math.min(SCROLL_BUFFER, rowHeight - 1);

        //     bDiv.scrollTop(safeBottom);
        //     lastScrollTop = safeBottom;
        //     return;
        // }

        // // Ini mencegah logika "Scroll Up" tereksekusi secara tidak sengaja.
        // if (grid.getGridParam("reccount") === 0) {
        //     lastScrollTop = 0;
        //     return;
        // }

        // Jika lastScrollTop masih 0 tapi posisi sekarang sudah jauh di bawah (akibat tombol End),
        // Update tracker dan return, supaya tidak salah deteksi sebagai 'Scroll Down'.
        if (lastScrollTop === 0 && scrollTop > rowHeight * 10) {
            lastScrollTop = scrollTop;
            return;
        }

        // Jangan load jika sedang loading
        // if (loading) return;

        // SCROLL DOWN
        if (scrollTop > lastScrollTop && scrollTop + viewHeight >= tableHeight - (gapPage * rowHeight)) {

            // Halaman yang seharusnya tampil sekarang/sebentar lagi
            var nextPage = maxPageLoaded + 1;

            if (nextPage > totalPages) return;

            // 1. Cek apakah halaman selanjutnya sudah ada di cache?
            if (cachedData[nextPage]) {
                // DATA ADA: Render langsung dari cache (Instan!)
                console.log(`Rendering Page ${nextPage} from Cache`);
                renderFromCache(grid, cachedData[nextPage], 'down', rowNum, nextPage);

                // --- PRE-FETCH TRIGGER ---
                // Kita sudah punya Page X. Cek apakah kita punya Page X+1?
                // Jika tidak, ambil batch berikutnya secara diam-diam.
                var pagePlusOne = nextPage + 1;
                var pagePlusTwo = nextPage + 2;
                var targetPrefetch = null;

                if (!cachedData[pagePlusOne]) {
                    targetPrefetch = pagePlusOne; // Prioritas 1: Isi tetangga terdekat (Page 5)
                } else if (!cachedData[pagePlusTwo]) {
                    targetPrefetch = pagePlusTwo; // Prioritas 2: Isi langkah berikutnya (Page 6)
                }

                // Syarat Pre-fetch:
                // 1. Tidak sedang loading
                // 2. Pre-fetch page masih dalam range totalPages
                // 3. Data pre-fetch page BELUM ada di cache
                if (!loading && targetPrefetch && targetPrefetch <= totalPages && !cachedData[targetPrefetch]) {
                    console.log(`Triggering Pre-fetch for Page ${targetPrefetch}...`);

                    loadGridData(
                        gridId,
                        apiUrl,
                        accessToken,
                        currentPostData,
                        targetPrefetch, // Minta halaman depan
                        rowNum,
                        'down',
                        'page',
                        null,
                        true // <--- ONLY CACHE = TRUE
                    );
                }

            } else {
                // DATA TIDAK ADA: Terpaksa loading biasa (user scroll terlalu cepat melampaui pre-fetch)
                loadGridData(gridId, apiUrl, accessToken, currentPostData, nextPage, rowNum, 'down', 'page');
            }
        }

        // SCROLL UP
        // Hitung batas trigger. Default: gapPage (900px).
        var triggerThreshold = gapPage * rowHeight;

        // Jika kita dalam "Single Page Mode" (habis Jump End), kita perluas trigger
        // agar loading terjadi segera setelah user mulai scroll ke atas.
        if (maxPageLoaded === minPageLoaded) {
            triggerThreshold = tableHeight * 0.8; // Trigger di 80% ketinggian tabel
        }

        // SCROLL UP (Logika sama, render dari cache jika ada)
        if (scrollTop < lastScrollTop && scrollTop <= triggerThreshold && minPageLoaded > 1) {
            var prevPage = minPageLoaded - 1;
            if (cachedData[prevPage]) {
                renderFromCache(grid, cachedData[prevPage], 'up', rowNum, prevPage);
            } else {
                loadGridData(gridId, apiUrl, accessToken, currentPostData, prevPage, rowNum, 'up', 'page');
            }
        }

        lastScrollTop = scrollTop <= 0 ? 0 : scrollTop;

        updateGridInfoFast(grid);

    }, 150);

    grid.parents(".ui-jqgrid-bdiv").off("scroll.virtual").on("scroll.virtual", throttledScroll);
}


/* =======================================================================
 * Fungsi Render dari Cache - DIPERBAIKI
 * ======================================================================= */
function renderFromCache(grid, data, direction, rowsPerPage, currentPage) {

    // FIX 8: CEK DUPLICATE SEBELUM RENDER
    var existingIds = grid.jqGrid('getDataIDs');

    if (
        direction === 'up' &&
        currentPage >= minPageLoaded &&
        currentPage <= maxPageLoaded
    ) {
        console.warn('Skip render: page already in window', currentPage);
        return;
    }

    if (direction === 'down') {
        var addedCount = 0;

        // FIX 9: Cek setiap row sebelum add
        data.forEach(row => {
            if (!existingIds.includes(row.id.toString())) {
                grid.jqGrid('addRowData', row.id, row, 'last');
                addedCount++;
            }
        });

        console.log(`Added ${addedCount} rows (direction: down)`);

        // PERBAIKAN UTAMA: Gunakan currentPage yang dikirim dari loadGridData
        // Jika currentPage undefined (misal dari kasus tertentu), fallback ke maxPageLoaded
        let validPage = currentPage || maxPageLoaded;
        maxPageLoaded = Math.max(maxPageLoaded, validPage);
        currentViewPage = currentPage;
        trimGridRows(grid, 'down', rowsPerPage);
    }

    if (direction === 'up') {
        var scrollDiv = grid.parents('.ui-jqgrid-bdiv');
        var prevScroll = scrollDiv.scrollTop();
        var addedCount = 0;

        // FIX 10: Cek setiap row sebelum add (reverse order)
        for (let i = data.length - 1; i >= 0; i--) {
            if (!existingIds.includes(data[i].id.toString())) {
                grid.jqGrid('addRowData', data[i].id, data[i], 'first');
                addedCount++;
            }
        }

        console.log(`Added ${addedCount} rows (direction: up)`);

        minPageLoaded--;
        currentViewPage = currentPage;
        trimGridRows(grid, 'up', rowsPerPage);

        // FIX 11: Adjust scroll hanya jika ada row yang ditambahkan
        if (addedCount > 0) {
            var rowHeight = grid.find('tr[id]').height() || 30;
            scrollDiv.scrollTop(prevScroll + (addedCount * rowHeight));
        }
    }

    if (direction === 'jump' || direction === 'reload') {
        grid.jqGrid('clearGridData');

        data.forEach(row => {
            grid.jqGrid('addRowData', row.id, row, 'last');
        });

        // Pastikan currentPage terupdate saat jump
        if (currentPage) {
            currentViewPage = parseInt(currentPage);
            minPageLoaded = parseInt(currentPage);
            maxPageLoaded = parseInt(currentPage);
            grid.jqGrid('setGridParam', { page: parseInt(currentPage) });
            grid.jqGrid('setGridParam', { records: totalRecord });
        } else {
            // Fallback jika entah kenapa currentPage kosong
            currentViewPage = minPageLoaded || 1;
            minPageLoaded = maxPageLoaded;
            grid.jqGrid('setGridParam', { records: totalRecord });
            grid.jqGrid('setGridParam', { page: minPageLoaded });
        }
    }

    $('.loaderGrid').addClass('d-none');
    ensureValidSelection(grid);
    // setHighlight(grid);
    syncCheckboxes(grid);
    refreshRowNumbers(grid, minPageLoaded, rowsPerPage);
    updateGridInfoFast(grid);
}


/* =======================================================================
 * Fungsi Trim Grid Rows - DIPERBAIKI
 * ======================================================================= */
// function trimGridRows(grid, direction, rowsPerPage) {
//     var ids = grid.jqGrid('getDataIDs');
//     var maxRows = WINDOW_PAGES * rowsPerPage;

//     if (ids.length <= maxRows) return;

//     var excess = ids.length - maxRows;

//     if (direction === 'down') {
//         // Buang dari atas
//         for (let i = 0; i < excess; i++) {
//             grid.jqGrid('delRowData', ids[i]);
//         }
//         minPageLoaded++;

//         // FIX 12: Clear cache untuk page yang dihapus
//         // delete cachedData[minPageLoaded - 1];
//     }

//     if (direction === 'up') {
//         // Buang dari bawah
//         for (let i = ids.length - 1; i >= ids.length - excess; i--) {
//             grid.jqGrid('delRowData', ids[i]);
//         }
//         maxPageLoaded--;

//         // FIX 13: Clear cache untuk page yang dihapus
//         // delete cachedData[maxPageLoaded + 1];
//     }

//     refreshRowNumbers(grid, minPageLoaded, rowsPerPage);
//     ensureValidSelection(grid)
// }
/* =======================================================================
 * Fungsi Trim Grid Rows - DIPERBAIKI (DYNAMIC PAGE COUNT)
 * ======================================================================= */
function trimGridRows(grid, direction, rowsPerPage) {
    var ids = grid.jqGrid('getDataIDs');
    var maxRows = WINDOW_PAGES * rowsPerPage;

    if (ids.length <= maxRows) return;

    var excess = ids.length - maxRows;

    // Hitung berapa halaman yang sebenarnya akan dihapus
    // Jika excess 50 -> 1 halaman. Jika excess 100 -> 2 halaman.
    var pagesRemoved = Math.floor(excess / rowsPerPage);
    if (pagesRemoved < 1) pagesRemoved = 1; // Safety minimal 1

    if (direction === 'down') {
        // Buang dari atas
        for (let i = 0; i < excess; i++) {
            grid.jqGrid('delRowData', ids[i]);
        }

        // FIX: Update minPageLoaded sesuai jumlah halaman yang terbuang
        minPageLoaded += pagesRemoved;
    }

    if (direction === 'up') {
        // Buang dari bawah
        for (let i = ids.length - 1; i >= ids.length - excess; i--) {
            grid.jqGrid('delRowData', ids[i]);
        }

        // FIX: Update maxPageLoaded sesuai jumlah halaman yang terbuang
        maxPageLoaded -= pagesRemoved;
    }

    // Panggil refresh dengan nilai minPageLoaded yang SUDAH BENAR
    refreshRowNumbers(grid, minPageLoaded, rowsPerPage);
    ensureValidSelection(grid)
}


/* =======================================================================
 * FUNGSI UPDATE NOMOR BARIS (IDENTITY BASED - ANTI LOMPAT)
 * ======================================================================= */
function refreshRowNumbers(grid, pageNumber, rowLimit) {
    var ids = grid.jqGrid('getDataIDs');
    if (ids.length === 0) return;

    var limit = parseInt(rowLimit) || 50;
    var lastValidNumber = 0; // Penampung nomor terakhir yang valid

    // --- STRATEGI: LOOP PER BARIS (JANGAN PAKAI RUMUS GLOBAL) ---
    // Kita hitung nomor untuk SETIAP BARIS secara independen berdasarkan ID-nya.

    for (var i = 0; i < ids.length; i++) {
        var currentId = ids[i];
        var foundIdentity = false;
        var calculatedNumber = 0;

        // 1. Cek Identitas ID ini di Cache
        // (Kita cari dia ada di page mana)
        for (var pageKey in cachedData) {
            if (cachedData.hasOwnProperty(pageKey)) {
                var pageData = cachedData[pageKey];

                // Cari index ID ini di dalam halaman aslinya
                var indexInPage = pageData.findIndex(function (row) {
                    return row.id.toString() === currentId.toString();
                });

                if (indexInPage !== -1) {
                    // KETEMU! 
                    // Rumus Absolut: (Page - 1) * Limit + Index + 1
                    var realPage = parseInt(pageKey);
                    calculatedNumber = (realPage - 1) * limit + indexInPage + 1;

                    foundIdentity = true;
                    lastValidNumber = calculatedNumber; // Simpan untuk referensi baris berikutnya
                    break;
                }
            }
        }

        // 2. Fallback untuk Baris "Yatim Piatu" (Orphan Rows)
        // Jika scroll sangat cepat, kadang ada baris sisa yang ID-nya sudah tidak ada di cache 
        // (karena page-nya sudah dihapus dari cache tapi barisnya belum hilang dari layar).
        if (!foundIdentity) {
            // Gunakan logika: Nomor sebelumnya + 1
            if (lastValidNumber > 0) {
                calculatedNumber = lastValidNumber + 1;
            } else {
                // Jika baris paling atas sendiri error, terpaksa tebak pakai minPageLoaded
                var fallbackPage = (typeof minPageLoaded !== 'undefined') ? minPageLoaded : 1;
                calculatedNumber = (fallbackPage - 1) * limit + (i + 1);
            }
            // Update lastValidNumber agar baris bawahnya nyambung
            lastValidNumber = calculatedNumber;
        }

        // 3. Set Nomor ke Grid
        // Safety: Cegah nomor melebihi totalRecord
        if (typeof totalRecord !== 'undefined' && totalRecord > 0) {
            if (calculatedNumber > totalRecord) calculatedNumber = totalRecord;
        }

        grid.jqGrid('setCell', currentId, 'rn', calculatedNumber);
    }

    updateGridInfoFast(grid);
}

/* =======================================================================
 * TAMBAHAN: Fungsi untuk Handle Filter Change
 * Panggil fungsi ini saat filter berubah
 * ======================================================================= */
function handleFilterChange(gridId, apiUrl, accessToken, postData) {
    var grid = $(gridId);

    console.log('Filter changed, reloading grid');

    // Reset state dan reload (dengan reset filter flag)
    resetGridState(grid, true);

    // Update currentFilters dengan filter baru
    currentFilters = grid.jqGrid('getGridParam', 'postData').filters;

    // Load page 1 dengan filter baru
    loadGridData(
        gridId,
        apiUrl,
        accessToken,
        postData,
        1,
        rowsPerPage,
        'down',
        'reload',
        function () {
            // Scroll ke atas
            grid.parents('.ui-jqgrid-bdiv').scrollTop(0);
            lastScrollTop = 0;

            // Select first row
            var firstId = grid.getDataIDs()[0];
            if (firstId) {
                $(`${gridId} [id="${firstId}"]`).click();
            }
        }
    );
}


/* ============================================================
 * FINAL HELPER – ENSURE VALID SELECTION
 * ============================================================ */
function ensureValidSelection(grid) {
    var ids = grid.jqGrid('getDataIDs');
    var sel = grid.jqGrid('getGridParam', 'selrow');

    if (!sel || !ids.includes(sel.toString())) {
        if (ids.length) {
            grid.jqGrid('setSelection', ids[0]);
        }
    }
}

/* =======================================================================
 * TAMBAHAN: Fungsi untuk Sync Checkboxes
 * ======================================================================= */
function syncCheckboxes(gridId) {
    // Ambil daftar ID yang sedang tampil di DOM Grid saat ini
    var gridIds = gridId.getDataIDs();

    // Loop setiap baris yang tampil
    $.each(gridIds, function (index, rowId) {
        // Cek apakah ID ini ada di dalam array selectedRows global
        // Pastikan konversi tipe data (string vs number) aman dengan ==
        if (selectedRows.some(selected => selected == rowId)) {

            // Cari elemen checkbox di baris tersebut
            var tr = gridId.find(`tr#${rowId}`);
            var checkbox = tr.find('td input.checkbox-jqgrid');

            // CENTANG & WARNAI
            checkbox.prop('checked', true);
            tr.addClass('bg-light-blue');

            // Jika perlu, disable checkbox sesuai logic approval Anda (opsional)
            // if (tr.find(`td[aria-describedby="jqGrid_statusapproval_id"]`).attr('title') !== selectedStatus) ...
        }
    });
}

// function updateGridInfoFast(grid) {
//     var ids = grid.jqGrid('getDataIDs');
//     var limit = parseInt(grid.jqGrid('getGridParam', 'postData').limit) || 50;

//     // Jika grid kosong
//     if (!ids.length) {
//         $('#jqGridInfoHandler').text(`View 0 - 0 of ${totalRecord}`);
//         return;
//     }

//     // --- HITUNG SECARA MATEMATIKA (JANGAN BACA DARI DOM) ---
//     // Start selalu berdasarkan minPageLoaded
//     var startRecord = (minPageLoaded - 1) * limit + 1;

//     // End adalah Start + jumlah baris yang ada di layar - 1
//     var endRecord = startRecord + ids.length - 1;

//     // Safety check: Jangan sampai endRecord melebihi totalRecord
//     if (totalRecord > 0 && endRecord > totalRecord) {
//         endRecord = totalRecord;
//     }

//     $('#jqGridInfoHandler').text(
//         `View ${startRecord} - ${endRecord} of ${totalRecord}`
//     );
// }

// function updateGridInfoFast(grid) {
//     var limit = rowsPerPage || 50;

//     var startRecord = (currentViewPage - 1) * limit + 1;
//     var endRecord = startRecord + limit - 1;

//     if (totalRecord > 0 && endRecord > totalRecord) {
//         endRecord = totalRecord;
//     }

//     $('#jqGridInfoHandler').text(
//         `View ${startRecord} - ${endRecord} of ${totalRecord}`
//     );
// }

function updateGridInfoFast(grid) {
    var limit = rowsPerPage;

    var startRecord = (currentViewPage - 1) * limit + 1;

    // JUMLAH ROW SEBENARNYA di page ini
    var actualRowsInPage = 0;
    if (cachedData[currentViewPage]) {
        actualRowsInPage = cachedData[currentViewPage].length;
    } else {
        actualRowsInPage = limit;
    }

    var endRecord = startRecord + actualRowsInPage - 1;

    // Safety clamp
    if (totalRecord > 0 && endRecord > totalRecord) {
        endRecord = totalRecord;
    }

    $('#jqGridInfoHandler').text(
        `View ${startRecord} - ${endRecord} of ${totalRecord}`
    );
}

function detectCurrentViewPage(grid) {
    var scrollDiv = grid.parents('.ui-jqgrid-bdiv');
    var scrollTop = scrollDiv.scrollTop();
    var rowHeight = grid.find('tr[id]').height() || 30;

    // Index baris paling atas yang terlihat
    var visibleRowIndex = Math.floor(scrollTop / rowHeight);

    // Record number absolut
    var recordNumber = (minPageLoaded - 1) * rowsPerPage + visibleRowIndex + 1;

    // Hitung page logis
    var page = Math.ceil(recordNumber / rowsPerPage);

    // Clamp safety
    if (page < 1) page = 1;
    if (page > totalPages) page = totalPages;

    return page;
}

