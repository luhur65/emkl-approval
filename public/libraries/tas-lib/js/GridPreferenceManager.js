/**
 * GridPreferenceManager
 * Fix double request: gunakan $.ajax biasa (bukan ajaxWithRefresh).
 * 
 * KENAPA bukan ajaxWithRefresh:
 *   $.ajaxPrefilter sudah intercept 401 dan handle refresh untuk SEMUA $.ajax.
 *   ajaxWithRefresh juga intercept 401 di .catch dan handle refresh lagi.
 *   Akibatnya satu request 401 ditangani DUA kali → 2x retry setelah refresh.
 *
 * Solusinya: pakai $.ajax biasa. $.ajaxPrefilter otomatis menangani 401 + refresh.
 * ajaxWithRefresh hanya perlu dipakai kalau kamu butuh slow threshold warning.
 */

const GridPreferenceManager = (function () {

  const CONFIG = {
    localPrefix: 'grid_pref_',
    serverUrlLoad: `${typeof appUrl !== 'undefined' ? appUrl.replace(/\/$/, '') : ''}/gridpreference/load`,
    serverUrlSave: `${typeof appUrl !== 'undefined' ? appUrl.replace(/\/$/, '') : ''}/gridpreference/save`,
    serverUrlDelete: `${typeof appUrl !== 'undefined' ? appUrl.replace(/\/$/, '') : ''}/gridpreference/delete`,
    debounceMs: 800,
    mode: 'server', // Aktifkan sinkronisasi server via file JSON
  };

  function debounce(fn, delay) {
    let timer;
    return function (...args) {
      clearTimeout(timer);
      timer = setTimeout(() => fn.apply(this, args), delay);
    };
  }

  // ─── localStorage ─────────────────────────────────────────────
  function localKey(gridName) {
    // Ambil userid dari localStorage lockscreen yang sudah kita buat sebelumnya.
    // Key diberi prefix 'emklapproval_' -- HARUS SAMA dengan APP_NS di
    // lockscreen.js -- karena localStorage di-scope per-origin browser, bukan
    // per-folder/path, dan proyek CI4 lain (mis. sys-modern) bisa saja diakses
    // dari origin yang sama.
    const userId = localStorage.getItem('emklapproval_lockscreen_userid') || 'guest';
    return CONFIG.localPrefix + userId + '_' + gridName;
  }

  function saveLocal(gridName, prefs) {
    try {
      localStorage.setItem(localKey(gridName), JSON.stringify(prefs));
    } catch (e) {
      console.warn('[GridPref] Gagal simpan localStorage:', e);
    }
  }

  function loadLocal(gridName) {
    try {
      const raw = localStorage.getItem(localKey(gridName));
      return raw ? JSON.parse(raw) : null;
    } catch (e) {
      console.warn('[GridPref] Gagal baca localStorage:', e);
      return null;
    }
  }

  function clearLocal(gridName) {
    localStorage.removeItem(localKey(gridName));
  }

  // ─── Server — pakai $.ajax biasa ─────────────────────────────
  // $.ajaxSetup → Authorization header otomatis disertakan
  // $.ajaxPrefilter → 401 → refresh token → retry  (sudah cukup)
  // JANGAN pakai ajaxWithRefresh — akan double handle 401

  function saveServer(gridName, prefs) {
    // Return promise agar bisa di-await
    return $.ajax({
      url: CONFIG.serverUrlSave,
      type: 'POST',
      contentType: 'application/json',
      dataType: 'json',
      data: JSON.stringify({ grid_name: gridName, preferences: prefs }),
    }).fail(function (jqXHR) {
      if (jqXHR.status !== 401) {
        console.warn('[GridPref] Gagal simpan ke server:', jqXHR.status, jqXHR.statusText);
      }
    });
  }

  function loadServer(gridName) {
    return $.ajax({
      url: `${CONFIG.serverUrlLoad}?grid_name=${encodeURIComponent(gridName)}`,
      type: 'GET',
      dataType: 'json',
    })
      .then(function (res) {
        return res?.preferences ?? null;
      })
      .catch(function (jqXHR) {
        if (jqXHR.status !== 401) {
          console.warn('[GridPref] Gagal baca dari server:', jqXHR.status);
        }
        return null;
      });
  }

  function deleteServer(gridName) {
    return $.ajax({
      url: `${CONFIG.serverUrlDelete}?grid_name=${encodeURIComponent(gridName)}`,
      type: 'GET',
      dataType: 'json',
    }).fail(function (jqXHR) {
      if (jqXHR.status !== 401) {
        console.warn('[GridPref] Gagal delete dari server:', jqXHR.status, jqXHR.statusText);
      }
    });
  }

  // ─── Ekstrak dari grid ────────────────────────────────────────
  function extractFromGrid(gridSelector) {
    const colModel = $(gridSelector).jqGrid('getGridParam', 'colModel');
    return colModel.map((col, index) => ({
      name: col.name,
      width: col.width,
      hidden: col.hidden || false,
      frozen: col.frozen || false,
      order: index,
    }));
  }

  function extractFromDom(gridSelector) {
    const colModel = $(gridSelector).jqGrid('getGridParam', 'colModel');

    // Buat map name → col data
    const colMap = {};
    colModel.forEach(col => { colMap[col.name] = col; });

    // Baca urutan kolom dari DOM header
    const orderedNames = [];
    $(gridSelector).closest('.ui-jqgrid-view')
      .find('thead tr.ui-jqgrid-labels th')
      .each(function () {
        // jqGrid biasanya set id="jqGrid_colName" di setiap th
        const thId = $(this).attr('id') || '';
        const colName = thId.replace(/^.*_/, '');
        if (colName && colMap[colName]) {
          orderedNames.push(colName);
        }
      });

    return orderedNames.map((name, index) => ({
      name: name,
      width: colMap[name].width,
      hidden: colMap[name].hidden || false,
      order: index,
    }));
  }

  // ─── Merge ke colModel ────────────────────────────────────────
  function applyToColModel(baseColModel, savedPrefs) {
    if (!savedPrefs || !savedPrefs.length) return baseColModel;

    const prefMap = {};
    savedPrefs.forEach(p => { prefMap[p.name] = p; });

    const updated = baseColModel.map(col => {
      const pref = prefMap[col.name];
      if (!pref) return col;
      return {
        ...col,
        width: pref.width !== undefined ? pref.width : col.width,
        hidden: pref.hidden !== undefined ? pref.hidden : (col.hidden || false),
        frozen: pref.frozen !== undefined ? pref.frozen : (col.frozen || false),
      };
    });

    updated.sort((a, b) => {
      const oA = prefMap[a.name] ? prefMap[a.name].order : 9999;
      const oB = prefMap[b.name] ? prefMap[b.name].order : 9999;
      return oA - oB;
    });

    return updated;
  }

  // ─── Listener resizeStop ──────────────────────────────────────
  function attachListeners(gridSelector, gridName, onSaved) {
    const grid = $(gridSelector);

    const debouncedSave = debounce(function () {
      const prefs = extractFromGrid(gridSelector);
      saveLocal(gridName, prefs);
      if (CONFIG.mode === 'server') {
        saveServer(gridName, prefs).then(function () {
          if (typeof onSaved === 'function') onSaved(prefs);
        });
      }
    }, CONFIG.debounceMs);

    const originalResizeStop = grid.jqGrid('getGridParam', 'resizeStop');
    grid.jqGrid('setGridParam', {
      resizeStop: function (newWidth, index) {
        if (typeof originalResizeStop === 'function') {
          originalResizeStop.call(this, newWidth, index);
        }
        debouncedSave();
      }
    });
  }

  // ─── Reset ────────────────────────────────────────────────────
  function reset(gridName) {
    clearLocal(gridName);
    if (CONFIG.mode === 'server') {
      deleteServer(gridName);
    }
  }

  // ─── API Publik ───────────────────────────────────────────────
  return {
    configure(options) {
      Object.assign(CONFIG, options);
    },

    // load() tetap async karena loadServer perlu await
    async load(gridName) {
      const local = loadLocal(gridName);
      if (local) return local;

      if (CONFIG.mode === 'server') {
        const serverPrefs = await loadServer(gridName);
        if (serverPrefs) saveLocal(gridName, serverPrefs);
        return serverPrefs ?? null;   // ← pastikan selalu return nilai
      }
      return null;
    },

    loadSync(gridName) {
      return loadLocal(gridName);
    },

    save(gridName, prefs) {
      saveLocal(gridName, prefs);
      if (CONFIG.mode === 'server') return saveServer(gridName, prefs);
    },

    apply: applyToColModel,
    extract: extractFromGrid,
    extractFromDom,
    attach: attachListeners,
    reset,
    _localKey: localKey,
  };

})();