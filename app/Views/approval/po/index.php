<style>
    .filter-input-group {
        margin-bottom: 0;
    }
    .filter-label {
        font-weight: 500;
        margin-bottom: 0.2rem;
        font-size: 0.9rem;
    }
    .btn-block {
        height: 38px;
    }
    /* Gaya kolom checkbox + nomor baris ada di tas-lib/css/styles.css
       (bagian "KOLOM CHECKBOX + NOMOR BARIS JQGRID") */

    /* Datepicker Colors */
    .holiday-date a.ui-state-default {
        color: #dc3545 !important;
        font-weight: bold !important;
    }
    .saturday-date a.ui-state-default {
        color: #28a745 !important;
        font-weight: bold !important;
    }
</style>

<div class="container-fluid">
    <!-- Filter Card -->
    <div class="card card-primary card-outline">
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group filter-input-group">
                        <label class="filter-label">Pilih Tanggal</label>
                        <div class="input-group date">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
                            </div>
                            <input type="text" class="form-control datepicker" id="datepicker" autocomplete="off" value="<?= date('d-m-Y') ?>">
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group filter-input-group">
                        <label class="filter-label">&nbsp;</label>
                        <button type="button" id="btnReload" class="btn btn-default form-control">
                            <i class="fas fa-sync-alt"></i> Reload
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Grid Card -->
    <table id="jqGrid"></table>
    <div id="jqGridPager"></div>
</div>

<script>
    var apiUrl = "<?= base_url('approvalpo/ajax_list') ?>" + window.location.search;
    var $grid = $("#jqGrid");

    var selectedRows = [];

    function checkboxHandler(element) {
        let value = $(element).val();
        if (element.checked) {
            selectedRows.push(value);
            $(element).parents('tr').addClass('row-selected');
        } else {
            $(element).parents('tr').removeClass('row-selected');
            for (var i = 0; i < selectedRows.length; i++) {
                if (selectedRows[i] == value) {
                    selectedRows.splice(i, 1);
                }
            }
        }
    }

    function clearSelectedRows() {
        selectedRows = [];
        $('.checkbox-jqgrid').prop('checked', false).parents('tr').removeClass('row-selected');
        $('.checkbox-selectall').prop('checked', false).attr('disabled', false);
    }

    // 'dd-mm-yyyy' (tampilan datepicker) -> 'yyyy-mm-dd' (yg diminta SP). Dipakai
    // bersama oleh postData grid & pengiriman approve, supaya keduanya dijamin
    // merujuk ke tanggal yang sama.
    function tanggalTerpilih() {
        var d = $('#datepicker').val().split('-');
        return d.length === 3 ? d[2] + '-' + d[1] + '-' + d[0] : '';
    }

    // Rangkum balasan server jadi satu pesan yg menyebut baris mana yg tidak jadi
    // diproses. usp_UpdTPermintaanApprovalOrder tidak melaporkan berapa baris yg
    // terpengaruh, jadi rincian dari server ini satu-satunya umpan balik nyata.
    function pesanHasilApproval(hasil) {
        if (!hasil || typeof hasil !== 'object') return 'Proses selesai.';

        var baris = [hasil.msg || 'Proses selesai.'];
        var dilewati = hasil.dilewati || [];
        var gagal = hasil.gagal || [];

        if (dilewati.length) {
            baris.push('');
            // Dua sebab, & keduanya lumrah di halaman ini: grid menampilkan baris
            // ber-status apa pun, jadi user bisa saja ikut mencentang baris yang
            // tidak cocok dengan tombol yang ia tekan.
            baris.push('Dilewati ' + dilewati.length + ' data (statusnya sudah sesuai, atau sudah tidak ada di tanggal ini):');
            baris.push(ringkasDaftar(dilewati, 10));
        }

        if (gagal.length) {
            baris.push('');
            baris.push('Gagal ' + gagal.length + ' data:');
            gagal.slice(0, 5).forEach(function(g) {
                baris.push('- ' + g.id + ' : ' + g.pesan);
            });
            if (gagal.length > 5) {
                baris.push('- ... dan ' + (gagal.length - 5) + ' lainnya');
            }
        }

        return baris.join('\n');
    }

    function ringkasDaftar(daftar, maks) {
        return daftar.length > maks
            ? daftar.slice(0, maks).join(', ') + ', ... (+' + (daftar.length - maks) + ' lagi)'
            : daftar.join(', ');
    }

    // 401 = sesi habis, 403 = tidak punya hak akses menu. Keduanya dibalas JSON
    // oleh server (AuthFilter & ApprovalPo::denyIfNoAccess), bukan redirect,
    // supaya tidak terbaca sebagai proses yang berhasil.
    function pesanGagalAjax(xhr) {
        if (xhr.status === 401) return 'Sesi Anda telah berakhir. Halaman akan dimuat ulang untuk login.';
        if (xhr.responseJSON && xhr.responseJSON.error) return xhr.responseJSON.error;
        return 'Terjadi Kesalahan Coba Lagi';
    }

    // showDialog() menyisipkan pesannya sbg HTML, sedangkan pesan kita teks polos
    // multi-baris yg memuat kiriman server (No Bukti & pesan error SQL Server).
    // Escape dulu lewat .text(), baru newline-nya diubah jadi <br> supaya tetap
    // terbaca sbg baris terpisah.
    function keHtmlAman(teks) {
        return $('<div>').text(teks).html().replace(/\n/g, '<br>');
    }

    // Overlay .modal-loader (markup di partials/header.php) adalah loader standar
    // tas-lib -- pager.js memakainya utk tombol add/edit/delete/view, dan handler
    // close showDialog() juga menyembunyikannya.
    function tampilkanLoading() {
        $('.modal-loader').removeClass('d-none');
    }

    function sembunyikanLoading() {
        $('.modal-loader').addClass('d-none');
    }

    // Penjaga klik ganda. Overlay loader ber-z-index 1035, sedangkan modal
    // Bootstrap 1050 -- jadi loader saja TIDAK memblokir: user masih bisa membuka
    // modal pager lalu menekan tombolnya lagi selagi request pertama berjalan.
    var approvalSedangJalan = false;

    // --- Menu APPROVE / UN APPROVE (modal customPager) -----------------------
    // Tiap aksi punya endpoint sendiri, sama seperti approval/top: jenis proses
    // ditentukan server dari route yg dipanggil (ApprovalPo::approved /
    // ::unapproved), TIDAK dikirim sbg field POST yg bisa ditukar isi form.
    //
    // Beda dgn approval/top, KEDUA tombol di sini selalu aktif: grid modul ini
    // menampilkan baris ber-status apa pun sekaligus (usp_GetListPermintaanApproval-
    // order tdk punya parameter `bit`), jadi kedua aksi sama-sama masuk akal utk
    // baris yg sedang tampil. Baris yg statusnya tdk cocok dgn tombol yg ditekan
    // akan dilewati server & dilaporkan balik lewat pesanHasilApproval().
    var AKSI_APPROVE = {
        label: 'APPROVE',
        url: "<?= base_url('approvalpo/approved') ?>",
        tanya: function(jumlah) {
            return 'Approve ' + jumlah + ' data terpilih? Baris yang sudah di-approve akan dilewati.';
        }
    };

    var AKSI_UNAPPROVE = {
        label: 'UN APPROVE',
        url: "<?= base_url('approvalpo/unapproved') ?>",
        tanya: function(jumlah) {
            return 'Un-approve ' + jumlah + ' data terpilih? Baris yang belum di-approve akan dilewati.';
        }
    };

    function prosesApproval(aksi) {
        if (approvalSedangJalan) {
            return;
        }

        if (selectedRows.length === 0) {
            showDialog('Silakan pilih minimal satu baris.');
            return;
        }

        // showConfirm mengembalikan promise: Ok -> resolve, Cancel -> reject.
        // Batal cukup tidak melakukan apa pun, jadi handler reject tidak perlu.
        showConfirm('Konfirmasi ' + aksi.label, aksi.tanya(selectedRows.length))
            .then(function() {
                kirimApproval(aksi);
            });
    }

    function kirimApproval(aksi) {
        // Dijaga lagi di sini, bukan cuma di prosesApproval(): pager.js mendaftarkan
        // handler klik item modal SETIAP KALI tombol pager ditekan (lihat
        // element.item.forEach di pager.js), jadi onClick bisa terpanggil berkali-kali
        // dalam satu klik.
        if (approvalSedangJalan) {
            return;
        }

        $.ajax({
            type: "POST",
            url: aksi.url,
            // Loading dimunculkan stlh konfirmasi di-Ok, bukan saat tombol modal
            // ditekan: selama dialog konfirmasi terbuka belum ada proses apa pun.
            beforeSend: function() {
                approvalSedangJalan = true;
                tampilkanLoading();
            },
            // Dikirim sbg SATU variabel JSON, bukan array id[]:
            // PHP max_input_vars (1000 di server ini) membuang
            // kelebihan variabel tanpa error, shg "pilih semua"
            // pd data besar cuma terproses sebagian.
            data: {
                ids: JSON.stringify(selectedRows),
                // tgl ikut dikirim supaya server bisa memastikan No Bukti yg
                // dipilih MASIH ada pd tanggal ini. SP-nya sendiri tidak
                // melaporkan baris terpengaruh, jadi tanpa ini id basi akan
                // meng-update 0 baris tapi tetap terhitung "berhasil".
                tgl: tanggalTerpilih(),
                '<?= csrf_token() ?>': '<?= csrf_hash() ?>'
            },
            success: function(result) {
                // Seleksi dibuang lebih dulu: status baris yg baru diproses sudah
                // berubah, jadi centangnya sudah tidak mewakili apa pun yg berguna
                // utk klik berikutnya.
                clearSelectedRows();
                reloadApprovalPoGrid();
                showDialog({
                    statuspesan: 'success',
                    message: keHtmlAman(pesanHasilApproval(result))
                });
            },
            error: function(xhr) {
                var pesan = keHtmlAman(pesanGagalAjax(xhr));

                // showDialog TIDAK memblokir spt alert(), jadi reload sesi-habis
                // harus menunggu dialognya ditutup -- kalau langsung dipanggil,
                // pesannya hilang sebelum sempat terbaca.
                if (xhr.status === 401) {
                    showDialog(pesan, null, "600px", function() { window.location.reload(); });
                    return;
                }

                showDialog(pesan);
            },
            // Dibersihkan di complete, bukan di awal success/error: jadi loader
            // tetap hilang & tombol tetap bisa dipakai lagi walau salah satu
            // handler di atas sempat melempar error.
            complete: function() {
                approvalSedangJalan = false;
                sembunyikanLoading();
            }
        });
    }

    // Centang & warnai baris yg sedang dimuat, mengikuti isi selectedRows.
    function syncLoadedRowSelection() {
        $('.checkbox-jqgrid').each(function() {
            var on = selectedRows.indexOf(String($(this).val())) !== -1;
            $(this).prop('checked', on).parents('tr').toggleClass('row-selected', on);
        });
    }

    // "Pilih semua" harus mencakup SELURUH data hasil filter, bukan cuma baris
    // yg kebetulan sudah dimuat: dgn lazy loading, getDataIDs() hanya tahu baris
    // yg ada di memori grid (mis. 50 dari 1000). Kuncinya diminta ke server
    // lewat pipeline filter yg sama dgn ajax_list, jadi isinya persis sama dgn
    // yg tampil pd kombinasi tanggal/filter saat ini.
    function selectAllRows() {
        var $selectAll = $('.checkbox-selectall');
        var post = $grid.jqGrid('getGridParam', 'postData') || {};

        $.ajax({
            url: '<?= base_url('approvalpo/select_all_ids') ?>',
            type: 'GET',
            dataType: 'json',
            // jQuery mengevaluasi nilai fungsi di postData (tgl) saat serialisasi
            data: $.extend({}, post, {
                filters: post.filters || '',
                _search: $grid.jqGrid('getGridParam', 'search') ? true : false
            })
        }).done(function(ids) {
            selectedRows = Array.isArray(ids) ? ids.map(String) : [];
            syncLoadedRowSelection();
        }).fail(function() {
            selectedRows = [];
            $selectAll.prop('checked', false);
            showDialog('Gagal mengambil seluruh data untuk dipilih. Silakan coba lagi.');
        }).always(function() {
            $selectAll.attr('disabled', false);
        });
    }

    // Muat ulang grid dari halaman 1 (dipakai stlh ganti tanggal, reload manual,
    // sort, filter toolbar, pencarian global, & sesudah approve/un-approve).
    // Lazy loading pakai datatype:'local', jadi reload HARUS lewat loadGridData()
    // (server) -- trigger('reloadGrid') bawaan jqGrid cuma render ulang data lokal.
    function reloadApprovalPoGrid(callback) {
        if (typeof loadGridData === 'function') {
            if (typeof lazyStates !== 'undefined' && lazyStates['jqGrid']) lazyStates['jqGrid'].cachedData = {};
            loadGridData("#jqGrid", apiUrl, $grid.jqGrid('getGridParam', 'postData'), 1, $grid.jqGrid('getGridParam', 'rowNum'), 'jump', 'reload', callback);
        } else {
            $grid.setGridParam({datatype: 'json', page: 1}).trigger("reloadGrid");
            if (typeof callback === 'function') callback();
        }
    }

    // Ganti tanggal mengubah HIMPUNAN baris yg boleh diproses, jadi seleksi lama
    // WAJIB dibuang. Kalau tidak, No Bukti sisa dari tanggal sebelumnya ikut
    // terkirim saat approve -- server memang menolaknya lewat penyaring "layak",
    // tapi user akan bingung melihat laporan "dilewati" utk baris yg tidak pernah
    // ia maksud pilih. Sort & filter kolom sengaja TIDAK membuang seleksi:
    // himpunan datanya sama, user cuma mengubah cara melihatnya.
    function resetSelectionAndReload() {
        clearSelectedRows();
        reloadApprovalPoGrid();
    }

    // Dibaca loadComplete: kalau reload sedang berlangsung akibat toolbar/pencarian
    // global (bukan sebab lain spt initial load/sort/ganti tanggal), JANGAN jalankan
    // auto-select baris pertama di sana -- itu memindahkan fokus keyboard sungguhan
    // ke grid ~50ms setelah render, yg akan langsung merebut balik fokus yg baru saja
    // dikembalikan reloadApprovalPoGridKeepFocus() ke input yg sedang diketik.
    var lastFocusedSearchInputId = null;

    // Reload me-render ulang baris grid & memindahkan fokus browser keluar dari
    // input toolbar filter/pencarian global. Simpan elemen yg lg fokus + posisi
    // kursornya sblm reload, lalu kembalikan setelah reload selesai (async),
    // supaya user bisa lanjut ngetik tanpa perlu klik ulang ke inputnya.
    function reloadApprovalPoGridKeepFocus() {
        var activeEl = document.activeElement;
        var isToolbarInput = activeEl && activeEl.id && (
            activeEl.id.indexOf('gs_') === 0 || activeEl.id.indexOf('_searchText') !== -1
        );
        var activeId = isToolbarInput ? activeEl.id : null;
        var caretPos = (isToolbarInput && typeof activeEl.selectionStart === 'number') ? activeEl.selectionStart : null;

        lastFocusedSearchInputId = activeId;

        reloadApprovalPoGrid(function() {
            lastFocusedSearchInputId = null;
            if (!activeId) return;
            var el = document.getElementById(activeId);
            if (!el) return;
            el.focus();
            if (caretPos !== null && el.setSelectionRange) {
                el.setSelectionRange(caretPos, caretPos);
            }
        });
    }

    // Baca nilai toolbar filter per-kolom secara manual & jalankan pencarian.
    // Tidak mengandalkan mekanisme internal filterToolbar (autosearch/searchOnEnter)
    // krn di halaman ini keypress Enter-nya tdk pernah memicu triggerToolbar bawaan
    // jqGrid (blm ditemukan sebabnya) -- jadi kita bangun sendiri `filters`-nya
    // dgn format yg sama persis dipakai jqgrid-search.js punya globalSearch.
    function triggerColumnFilterSearch() {
        var colModel = $grid.jqGrid('getGridParam', 'colModel');
        var rules = [];

        colModel.forEach(function(cm) {
            if (cm.search === false || !(cm.stype === undefined || cm.stype === 'text' || cm.stype === 'select')) {
                return;
            }
            var val = $('#gs_' + cm.name).val();
            if (val !== undefined && val !== null && val !== '') {
                rules.push({ field: cm.name, op: 'cn', data: val });
            }
        });

        $grid.jqGrid('clearGlobalSearch');
        $grid.jqGrid('setGridParam', {
            search: rules.length > 0,
            postData: {
                filters: rules.length > 0 ? JSON.stringify({ groupOp: 'AND', rules: rules }) : ''
            }
        });

        reloadApprovalPoGridKeepFocus();
    }

    // Selaraskan tampil/sembunyinya tombol "X" per-kolom dgn isi inputnya.
    // Perlu dipanggil manual setelah pengosongan programatis (clearToolbar),
    // krn cara itu tdk memicu event 'input' yg dipakai handler show/hide.
    function syncColumnClearButtons() {
        $('#gview_jqGrid tr.ui-search-toolbar .ui-search-input input:not(.checkbox-selectall)').each(function() {
            $(this).closest('tr').find('.clearsearchclass')
                   .toggleClass('is-visible', $(this).val().length > 0);
        });
    }

    $(document).ready(function() {
        // Ambil data hari libur
        var holidays = [];
        var currentYear = new Date().getFullYear();
        $.get('<?= base_url('harilibur') ?>?year=' + currentYear, function(data) {
            if (data && data.length > 0) {
                holidays = data.map(function(item) {
                    return item.holiday_date;
                });
            }
        });

        if($.fn.datepicker) {
            $('.datepicker').datepicker({
                dateFormat: 'dd-mm-yy',
                changeMonth: true,
                changeYear: true,
                beforeShowDay: function(date) {
                    var day = date.getDay();
                    var d = date.getFullYear() + '-' + ('0' + (date.getMonth()+1)).slice(-2) + '-' + ('0' + date.getDate()).slice(-2);
                    if (day === 0 || holidays.indexOf(d) !== -1) {
                        return [true, 'holiday-date'];
                    } else if (day === 6) {
                        return [true, 'saturday-date'];
                    }
                    return [true, ''];
                },
                onSelect: function(dateText) {
                    $(this).val(dateText);
                    resetSelectionAndReload();
                }
            });
        }

        // Kolom uang SENGAJA tanpa formatter di sini -- pemisah ribuannya sudah
        // dipasang server (ApprovalPoService::formatUang). Pencarian & sorting
        // berjalan di server, jadi begitu klien ikut memformat, teks yang dilihat
        // user tidak lagi sama dengan teks yang dicocokkan server: mengetik "2,7"
        // pada sel yang tampil "2,736,213,726.00" tidak akan menemukan apa pun,
        // karena di server nilainya masih "2736213726.00".
        $grid.jqGrid({
            styleUI: 'Bootstrap4',
            iconSet: 'fontAwesome',
            url: apiUrl,
            mtype: "POST",
            datatype: "local",
            postData: {
                tgl: tanggalTerpilih
            },
            colModel: [
                {
                    label: 'NO.',
                    name: 'check',
                    width: 60,
                    align: 'left',
                    // Dipasang jqGrid ke <th> header sekaligus <th> baris filter.
                    // Perataan header diatur lewat kelas ini, bukan lewat `align`
                    // (align hanya berlaku utk sel data).
                    labelClasses: 'col-check',
                    // Kelas yg sama utk <td> sel data (labelClasses tdk sampai ke
                    // sana). Dipakai styles.css utk mengembalikan padding kiri yg
                    // dihapus aturan global `tr > td:first-of-type` -- tanpa ini
                    // checkbox baris data tdk sejajar dgn checkbox "pilih semua"
                    // di baris filter, krn di grid ini kolom check = kolom pertama.
                    classes: 'col-check',
                    sortable: false,
                    clear: false,
                    // stype 'input' (bukan 'text') sekaligus mengeluarkan kolom ini
                    // dari daftar rule pencarian global -- jqgrid-search.js hanya
                    // menyertakan stype undefined/'text'/'select'.
                    stype: 'input',
                    searchable: false,
                    searchoptions: {
                        type: 'checkbox',
                        clearSearch: false,
                        dataInit: function(element) {
                            $(element).removeClass('form-control');
                            $(element).addClass('checkbox-selectall');
                            $(element).attr('title', 'Pilih semua baris');

                            // Bungkus checkbox + tombol clear dalam satu baris
                            $(element).wrap('<div class="selectall-wrapper"></div>');
                            $(element).after(
                                '<button type="button" class="btn-clear-filter" title="Bersihkan semua filter pencarian">' +
                                    '<i class="fas fa-times"></i>' +
                                '</button>'
                            );

                            // Kosongkan seluruh input pencarian di filter toolbar.
                            // clearToolbar(false): argumen `false` WAJIB supaya jqGrid
                            // TIDAK memicu reloadGrid bawaannya sendiri. Grid ini
                            // datatype:'local', jadi reloadGrid bawaan cuma me-repaginate
                            // t.p.data (baris yg kebetulan sudah dimuat di memori) pakai
                            // page/rowNum bawaan jqGrid sendiri -- sama sekali lepas dari
                            // pembukuan lazy-load kita (lazyStates.cachedData/minPageLoaded
                            // dst), sehingga nomor baris jadi rancu. Reload yg benar HARUS
                            // lewat reloadApprovalPoGrid() (loadGridData), persis spt yg
                            // sudah dilakukan globalSearch.beforeSearch() di bawah.
                            $(element).parent().on('click', '.btn-clear-filter', function(e) {
                                e.preventDefault();
                                e.stopPropagation();
                                var grid = $grid[0];
                                if (grid && typeof grid.clearToolbar === 'function') {
                                    grid.clearToolbar(false);
                                    syncColumnClearButtons();
                                }
                                $grid.jqGrid('clearGlobalSearch');
                                reloadApprovalPoGrid();
                            });

                            $(element).on('click', function() {
                                $(element).attr('disabled', true);
                                if ($(this).is(':checked')) {
                                    selectAllRows();
                                } else {
                                    clearSelectedRows();
                                }
                            });
                        }
                    },
                    formatter: (value, rowOptions, rowData) => {
                        var idVal = rowData.FNTrans;
                        // String(): selectedRows selalu berisi string, sedangkan
                        // nilai dr data lokal bisa saja bertipe lain
                        var isChecked = (selectedRows.indexOf(String(idVal)) !== -1) ? 'checked' : '';
                        return `<div class="rn-cell">
                            <input type="checkbox" class="checkbox-jqgrid" value="${idVal}" onchange="checkboxHandler(this)" ${isChecked}>
                            <span class="rn-number"></span>
                        </div>`;
                    },
                },
                { label: 'Target', name: 'IdTarget', hidden: true, key: true },
                { label: 'No Bukti', name: 'FNTrans', width: 150 },
                { label: 'Tanggal', name: 'FTgl', width: 100, align: 'center' },
                { label: 'Shipper', name: 'FNShipper', width: 250 },
                { label: 'Saldo Piutang', name: 'FSaldoPiutang', width: 130, align: 'right' },
                { label: 'Sisa Piutang', name: 'FSisaPiutang', width: 130, align: 'right' },
                { label: 'Kelebihan Piutang', name: 'FKelebihanPiutang', width: 140, align: 'right' },
                { label: 'Jumlah Order', name: 'FJumlahOrder', width: 130, align: 'right' },
                {
                    label: 'App',
                    name: 'FIsApp',
                    width: 60,
                    align: 'center',
                    // Dikeluarkan dari pencarian: nilainya 0/1 sedangkan yg dilihat
                    // user cuma centang, jadi rule 'cn' atasnya cuma bikin hasil
                    // pencarian global membengkak tanpa arti bagi user.
                    search: false,
                    // Penanda status, BUKAN checkbox seleksi -- kelasnya sengaja beda
                    // dari .checkbox-jqgrid supaya tidak ikut tersapu clearSelectedRows().
                    formatter: function(value) {
                        return '<input type="checkbox" class="checkbox-status" disabled ' +
                               (String(value) === '1' ? 'checked' : '') + '>';
                    }
                },
                { label: 'Tgl App', name: 'FDateApp', width: 150, align: 'center' },
                { label: 'User Approval', name: 'FUserApp', width: 120 },
                { label: 'Tgl Input', name: 'FTglInput', width: 150, align: 'center' },
                { label: 'User Input', name: 'FUserId', width: 120 }
            ],
            // 12 kolom: dipaskan ke lebar container (autowidth) tapi TANPA
            // shrinkToFit, jadi kolom uang & tanggal tetap selebar yg dibutuhkan
            // dan sisanya digeser scroll horizontal -- bukan diperas sampai
            // angkanya terpotong.
            autowidth: true,
            shrinkToFit: false,
            height: 360,
            rowNum: 50,
            toolbar: [true, "top"],
            sortname: "FNTrans",
            sortorder: "asc",
            rownumbers: false,
            multiselect: false,
            onSortCol: function(index, iCol, sortorder) {
                // Lazy loading pakai datatype:'local', jadi sort HARUS lewat server
                // (bukan sort lokal jqgrid yg cuma mengurutkan window baris yg sedang tampil).
                this.p.sortorder = sortorder;
                this.p.sortname = index;
                var pd = $(this).jqGrid('getGridParam', 'postData');
                if (pd) {
                    pd.sidx = index;
                    pd.sord = sortorder;
                }

                // Update ikon sort di header secara manual krn kita return 'stop'
                var previousSelectedTh = this.grid.headers[this.p.lastsort].el;
                var newSelectedTh = this.grid.headers[iCol].el;
                $("span.s-ico", previousSelectedTh).hide();
                $("span.s-ico", newSelectedTh).show();
                var disabledClass = "ui-state-disabled";
                $("span.ui-icon-asc, span.ui-icon-desc", newSelectedTh).addClass(disabledClass);
                $("span.ui-icon-" + sortorder, newSelectedTh).removeClass(disabledClass);
                this.p.lastsort = iCol;

                if (typeof lazyStates !== 'undefined' && lazyStates['jqGrid']) lazyStates['jqGrid'].cachedData = {};
                if (typeof loadGridData === 'function') {
                    loadGridData("#jqGrid", apiUrl, $grid.jqGrid('getGridParam', 'postData'), 1, $grid.jqGrid('getGridParam', 'rowNum'), 'jump', 'reload');
                }
                return 'stop';
            },
            // altRows sengaja dimatikan (default jqGrid): selain altclass,
            // altRows:true jg menambah kelas table-striped ke <table> (grid.js 5180)
            loadComplete: function() {
                // Catatan penting (bug di modul lain yg pakai lazyLoadingGridMonolith.js):
                // JANGAN hitung nomor baris dari `data.page`/`data.rows.length` punya respons
                // AJAX yg baru saja datang. Saat render berasal dari cache (scroll balik ke
                // halaman yg sudah pernah dimuat), fungsi renderFromCache() memanggil
                // loadComplete TANPA data respons asli, jadi `data.records` selalu undefined
                // dan nomor baris jadi tidak pernah ter-update / salah. Sebagai gantinya,
                // hitung posisi absolut tiap baris dari cache lazy-load (state.cachedData),
                // sama seperti refreshRowNumbers() di lazyLoadingGridMonolith.js, tapi
                // menyasar span.rn-number (bukan kolom 'rn' krn kolom NO. di grid ini
                // menggabungkan checkbox + nomor dalam satu kolom 'check').
                var state = (typeof getGridState === 'function') ? getGridState($grid) : null;
                var ids = $grid.jqGrid('getDataIDs');

                if (state && ids.length) {
                    var limit = parseInt($grid.jqGrid('getGridParam', 'rowNum'), 10) || 50;
                    var idToNumber = {};
                    for (var pg in state.cachedData) {
                        var realPage = parseInt(pg, 10);
                        state.cachedData[pg].forEach(function(row, idx) {
                            idToNumber[String(row.id)] = (realPage - 1) * limit + idx + 1;
                        });
                    }
                    ids.forEach(function(id, i) {
                        var no = idToNumber[id] || ((state.minPageLoaded - 1) * limit + i + 1);
                        if (state.totalRecord > 0 && no > state.totalRecord) no = state.totalRecord;
                        $grid.find('tr#' + $.jgrid.jqID(id) + ' .rn-number').text(no);
                    });
                }

                // Kembalikan highlight utk baris yg sudah tercentang (formatter hanya set checked)
                $grid.find('input.checkbox-jqgrid:checked').closest('tr').addClass('row-selected');

                // Default-select baris pertama begitu halaman 1 selesai dimuat (reload
                // awal, ganti tanggal, sort, atau search) supaya panah/PageUp/PageDown/
                // Space langsung ada titik awal tanpa user klik manual dulu.
                // Dijaga dgn `minPageLoaded === 1` (bukan cuma "belum ada selrow") krn
                // selrow otomatis null lagi stlh baris yg dipilih ter-trim dari DOM
                // ketika scroll jauh ke bawah (lihat delRowData di grid.base.js) --
                // pada titik itu minPageLoaded sudah > 1 jadi tidak direbut ulang.
                if (state && ids.length && state.minPageLoaded === 1 && !$grid.jqGrid('getGridParam', 'selrow')) {
                    if (lastFocusedSearchInputId) {
                        // Reload dipicu toolbar/pencarian global: tetap tandai baris
                        // pertama sbg terpilih (selrow) spy ada titik awal navigasi
                        // panah nanti, TAPI tanpa selectGridRow() krn itu jg memindah
                        // fokus keyboard sungguhan -- akan merebut fokus dari input yg
                        // sedang diketik (lihat reloadApprovalPoGridKeepFocus()).
                        $grid.resetSelection().setSelection(ids[0]);
                    } else {
                        setTimeout(function() {
                            // selectGridRow (mains.js): setSelection() + fokus keyboard
                            // sungguhan sekaligus -- lihat catatannya di mains.js.
                            selectGridRow($grid, ids[0]);
                        }, 50);
                    }
                }

                if (typeof setupLazyLoadScrollHandler === 'function') {
                    setupLazyLoadScrollHandler("#jqGrid", apiUrl, $grid.jqGrid('getGridParam', 'postData'));
                }
            }
        });

        // Navigasi keyboard (Up/Down/PageUp/PageDown/Home/End/Enter) + fix fokus-
        // ikut-seleksi dipusatkan di setCustomBindKeys() (mains.js) supaya dipakai
        // bersama modul lain, bukan ditulis ulang di tiap halaman. Cukup di-panggil
        // sekali di sini (bukan di gridComplete/loadComplete) krn ia bind sekali ke
        // document, bukan ke tiap baris grid -- lihat catatan di mains.js.
        // Space & cara baca nomor baris absolut dioverride di sini krn grid ini
        // TIDAK pakai rownumbers:true bawaan jqGrid, tapi kolom checkbox custom yg
        // menggabungkan checkbox + nomor (span.rn-number) dalam 1 kolom.
        // Left/Right sengaja tidak dipasang: grid ini tanpa subgrid.
        setCustomBindKeys($grid, {
            getAbsoluteIndex: function(grid, rowid) {
                var num = parseInt(grid.find('tr#' + $.jgrid.jqID(rowid) + ' .rn-number').text(), 10);
                return isNaN(num) ? 0 : num - 1;
            },
            onSpace: function(rowid) {
                if (!rowid) return;
                // Grid ini pakai checkbox custom (multiselect:false), bukan
                // selarrrow/setSelection bawaan jqGrid -- jadi toggle checkbox-nya
                // langsung supaya konsisten dgn klik mouse (checkboxHandler()).
                $grid.find('tr#' + $.jgrid.jqID(rowid) + ' input.checkbox-jqgrid').trigger('click');
            },
            onEnter: function(rowid) {
                if (!rowid) return;
                $grid.find('tr#' + $.jgrid.jqID(rowid) + ' input.checkbox-jqgrid').trigger('click');
            }
        });

        // Toolbar filter per-kolom: filterToolbar cuma dipakai utk render kotak
        // input per-kolom (autosearch/searchOnEnter dimatikan krn triggerToolbar
        // bawaannya tdk pernah menyala di halaman ini). Pemicunya sendiri (tombol
        // Enter) ditangani manual lewat triggerColumnFilterSearch() di bawah.
        // Pencarian global (jqgrid-search.js) memakai `filters` berformat sama;
        // keduanya diproses di GridPipeline::filterRows().
        $grid.jqGrid('filterToolbar', {
            stringResult: true,
            searchOnEnter: false,
            autosearch: false,
            defaultSearch: "cn",
            icon: false
        });

        // Delegasi ke document (bukan $grid): baris toolbar filter ada di
        // .ui-jqgrid-hdiv, bukan turunan DOM dari <table id="jqGrid"> aslinya.
        // Filter jalan otomatis sambil ngetik (debounce, sama spt pencarian
        // global), Enter tetap didukung utk memicu seketika tanpa nunggu jeda.
        var columnFilterTimer = null;
        $(document).on('input', '#gview_jqGrid tr.ui-search-toolbar input:not(.checkbox-selectall)', function() {
            clearTimeout(columnFilterTimer);
            columnFilterTimer = setTimeout(triggerColumnFilterSearch, 500);
        });
        $(document).on('keydown', '#gview_jqGrid tr.ui-search-toolbar input:not(.checkbox-selectall), #gview_jqGrid tr.ui-search-toolbar select', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                clearTimeout(columnFilterTimer);
                triggerColumnFilterSearch();
            }
        });

        // Tombol "X" merah bawaan jqGrid per-kolom (.clearsearchclass) sudah
        // dirender filterToolbar & sudah ada CSS-nya (styles.css), tapi tersembunyi
        // sampai JS menandainya .is-visible saat input kolom ybs terisi.
        // Dipakai kelas, bukan .css('display'), krn gaya inline biasa kalah dari
        // deklarasi !important di stylesheet.
        $(document).on('input', '.ui-search-input input:not(.checkbox-selectall)', function() {
            $(this).closest('tr').find('.clearsearchclass')
                   .toggleClass('is-visible', $(this).val().length > 0);
        });
        // Klik "X" bawaan jqGrid cuma mengosongkan input kolom itu (autosearch
        // dimatikan di sini), jadi susulkan re-search & sembunyikan tombolnya sendiri.
        $(document).on('click', '.clearsearchclass', function() {
            $(this).removeClass('is-visible');
            triggerColumnFilterSearch();
        });

        $grid.globalSearch({
            beforeSearch: function() {
                $grid[0].clearToolbar(false);
                syncColumnClearButtons();
            },
            reload: reloadApprovalPoGridKeepFocus
        }).customPager({
            lazyLoading: true,
            modalBtnList: [{
                id: 'approve',
                title: 'Approve',
                caption: 'Approve',
                innerHTML: '<i class="fa fa-check"></i> APPROVAL/UN',
                class: 'btn btn-purple btn-sm mr-1 ',
                // `group` yg sama membuat pager.js merender keduanya berdampingan
                // (col-sm-6). Tanpa `group`, tiap item jadi tombol full-width
                // bertumpuk -- lihat cabang groupedItems di pager.js.
                item: [
                    {
                        id: 'btnApprove',
                        group: 'approval',
                        color: 'btn-success',
                        text: 'Approve',
                        onClick: function() { prosesApproval(AKSI_APPROVE); }
                    },
                    {
                        id: 'btnUnApprove',
                        group: 'approval',
                        color: 'btn-danger',
                        text: 'Un Approve',
                        onClick: function() { prosesApproval(AKSI_UNAPPROVE); }
                    }
                ]
            }]
        });

        $('#datepicker').on('change', function () {
            resetSelectionAndReload();
        });

        $("#btnReload").on('click', function(){
            reloadApprovalPoGrid();
        });

        // datatype:'local' tidak memuat data otomatis, jadi trigger load halaman 1 di sini.
        reloadApprovalPoGrid();
    });
</script>
