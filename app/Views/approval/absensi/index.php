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
                <div class="col-md-3">
                    <div class="form-group filter-input-group">
                        <label class="filter-label">Proses Data</label>
                        <select name="prosesdata" id="prosesdata" class="form-control select2">
                            <option value="0">Approved</option>
                            <option value="1">Un Approved</option>
                        </select>
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
    var apiUrl = "<?= base_url('approvalabsensi/ajax_list') ?>" + window.location.search;
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

    function tanggalTerpilih() {
        var d = $('#datepicker').val().split('-');
        return d.length === 3 ? d[2] + '-' + d[1] + '-' + d[0] : '';
    }

    function pesanHasilApproval(hasil) {
        if (!hasil || typeof hasil !== 'object') return 'Proses selesai.';

        var baris = [hasil.msg || 'Proses selesai.'];
        var dilewati = hasil.dilewati || [];
        var gagal = hasil.gagal || [];

        if (dilewati.length) {
            baris.push('');
            // Kuncinya "FKGdg|FKSupir" -- ditampilkan apa adanya supaya user
            // bisa mencocokkannya kembali ke baris di grid.
            baris.push('Dilewati ' + dilewati.length + ' data (sudah diproses orang lain, sudah tercatat di absensi, atau bukan dari tanggal ini):');
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

    function pesanGagalAjax(xhr) {
        if (xhr.status === 401) return 'Sesi Anda telah berakhir. Halaman akan dimuat ulang untuk login.';
        if (xhr.responseJSON && xhr.responseJSON.error) return xhr.responseJSON.error;
        return 'Terjadi Kesalahan Coba Lagi';
    }

    // Nilai #prosesdata menentukan dua hal sekaligus: baris mana yg dimuat grid
    // (dikirim sbg `bit`) DAN aksi mana yg masuk akal untuk baris itu. Jadi
    // pasangannya dimatikan supaya user tidak mengirim kunci yg pasti ditolak
    // penyaring "layak" di server dan balik lagi sbg laporan "dilewati".
    var AKSI_APPROVE = {
        prosesdata: '0',
        label: 'APPROVE',
        url: "<?= base_url('approvalabsensi/approved') ?>",
        tanya: function(jumlah) {
            return 'Approve absensi untuk ' + jumlah + ' data terpilih?';
        }
    };

    var AKSI_UNAPPROVE = {
        prosesdata: '1',
        label: 'BATAL APPROVE',
        url: "<?= base_url('approvalabsensi/unapproved') ?>",
        // Sengaja lebih tegas drpd approve: membatalkan di modul ini berarti
        // MENGHAPUS baris penanda dari TrApprovalAbsensiJam9, bukan sekadar
        // mengubah kolom status -- jejak siapa & kapan meng-approve ikut hilang.
        tanya: function(jumlah) {
            return 'Batalkan approve untuk ' + jumlah + ' data terpilih? '
                 + 'Baris penandanya akan DIHAPUS, termasuk catatan siapa dan kapan meng-approve.';
        }
    };

    function keHtmlAman(teks) {
        return $('<div>').text(teks).html().replace(/\n/g, '<br>');
    }

    function tampilkanLoading() {
        $('.modal-loader').removeClass('d-none');
    }

    function sembunyikanLoading() {
        $('.modal-loader').addClass('d-none');
    }

    var approvalSedangJalan = false;

    function prosesApproval(aksi) {
        if (approvalSedangJalan) {
            return;
        }

        if (selectedRows.length === 0) {
            showDialog('Silakan pilih minimal satu baris.');
            return;
        }

        showConfirm('Konfirmasi ' + aksi.label, aksi.tanya(selectedRows.length))
            .then(function() {
                kirimApproval(aksi);
            });
    }

    function kirimApproval(aksi) {
        $.ajax({
            type: "POST",
            url: aksi.url,
            beforeSend: function() {
                approvalSedangJalan = true;
                tampilkanLoading();
            },
            data: {
                ids: JSON.stringify(selectedRows),
                tgl: tanggalTerpilih(),
                '<?= csrf_token() ?>': '<?= csrf_hash() ?>'
            },
            success: function(result) {
                clearSelectedRows();
                reloadApprovalGrid();
                showDialog({
                    statuspesan: 'success',
                    message: keHtmlAman(pesanHasilApproval(result))
                });
            },
            error: function(xhr) {
                var pesan = keHtmlAman(pesanGagalAjax(xhr));

                if (xhr.status === 401) {
                    showDialog(pesan, null, "600px", function() { window.location.reload(); });
                    return;
                }

                showDialog(pesan);
            },
            complete: function() {
                approvalSedangJalan = false;
                sembunyikanLoading();
            }
        });
    }

    var itemApprove = {
        id: 'btnApprove',
        group: 'approval',
        color: 'btn-success',
        text: 'Approve',
        onClick: function() { prosesApproval(AKSI_APPROVE); }
    };

    var itemUnApprove = {
        id: 'btnUnApprove',
        group: 'approval',
        color: 'btn-danger',
        text: 'Batal Approve',
        onClick: function() { prosesApproval(AKSI_UNAPPROVE); }
    };

    var approvalMenuItems = [itemApprove, itemUnApprove];

    function syncApprovalMenuState() {
        var prosesdata = $('#prosesdata').val();
        itemApprove.hidden = (prosesdata !== AKSI_APPROVE.prosesdata);
        itemUnApprove.hidden = (prosesdata !== AKSI_UNAPPROVE.prosesdata);
    }

    function syncLoadedRowSelection() {
        $('.checkbox-jqgrid').each(function() {
            var on = selectedRows.indexOf(String($(this).val())) !== -1;
            $(this).prop('checked', on).parents('tr').toggleClass('row-selected', on);
        });
    }

    function selectAllRows() {
        var $selectAll = $('.checkbox-selectall');
        var post = $grid.jqGrid('getGridParam', 'postData') || {};

        $.ajax({
            url: '<?= base_url('approvalabsensi/select_all_ids') ?>',
            type: 'GET',
            dataType: 'json',
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

    function reloadApprovalGrid(callback) {
        if (typeof loadGridData === 'function') {
            if (typeof lazyStates !== 'undefined' && lazyStates['jqGrid']) lazyStates['jqGrid'].cachedData = {};
            loadGridData("#jqGrid", apiUrl, $grid.jqGrid('getGridParam', 'postData'), 1, $grid.jqGrid('getGridParam', 'rowNum'), 'jump', 'reload', callback);
        } else {
            $grid.setGridParam({datatype: 'json', page: 1}).trigger("reloadGrid");
            if (typeof callback === 'function') callback();
        }
    }

    function resetSelectionAndReload() {
        clearSelectedRows();
        reloadApprovalGrid();
    }

    var lastFocusedSearchInputId = null;

    function reloadApprovalGridKeepFocus() {
        var activeEl = document.activeElement;
        var isToolbarInput = activeEl && activeEl.id && (
            activeEl.id.indexOf('gs_') === 0 || activeEl.id.indexOf('_searchText') !== -1
        );
        var activeId = isToolbarInput ? activeEl.id : null;
        var caretPos = (isToolbarInput && typeof activeEl.selectionStart === 'number') ? activeEl.selectionStart : null;

        lastFocusedSearchInputId = activeId;

        reloadApprovalGrid(function() {
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

        reloadApprovalGridKeepFocus();
    }

    function syncColumnClearButtons() {
        $('#gview_jqGrid tr.ui-search-toolbar .ui-search-input input:not(.checkbox-selectall)').each(function() {
            $(this).closest('tr').find('.clearsearchclass')
                   .toggleClass('is-visible', $(this).val().length > 0);
        });
    }

    $(document).ready(function() {
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
        
        if($('.select2').length > 0) {
            $('.select2').select2({ theme: 'bootstrap4' });
        }
        
        const isDesktop = (window.innerWidth > 768);

        $grid.jqGrid({
            styleUI: 'Bootstrap4',
            iconSet: 'fontAwesome',
            url: apiUrl,
            mtype: "POST",
            datatype: "local",
            postData: {
                tgl: tanggalTerpilih,
                bit: function() { return $('#prosesdata').val(); }
            },
            colModel: [
                {
                    label: 'NO.',
                    name: 'check',
                    width: 60,
                    align: 'left',
                    // Kolom ini TIDAK ikut dilebarkan shrinkToFit. Grid ini
                    // autowidth:true + shrinkToFit:true, jadi lebar container
                    // dibagi proporsional ke semua kolom -- dgn hanya 4 kolom
                    // data, kolom checkbox ikut membengkak (60px -> ~96px pd
                    // layar 1222px, makin lebar layarnya makin melar). `fixed`
                    // adalah cara jqGrid sendiri mengecualikan kolom dr
                    // pembagian itu (dipakainya utk kolom rownumbers,
                    // multiselect, & subgrid -- lihat grid.base.js 4196/4217).
                    fixed: true,
                    labelClasses: 'col-check',
                    classes: 'col-check',
                    sortable: false,
                    clear: false,
                    stype: 'input',
                    searchable: false,
                    searchoptions: {
                        type: 'checkbox',
                        clearSearch: false,
                        dataInit: function(element) {
                            $(element).removeClass('form-control');
                            $(element).addClass('checkbox-selectall');
                            $(element).attr('title', 'Pilih semua baris');

                            $(element).wrap('<div class="selectall-wrapper"></div>');
                            $(element).after(
                                '<button type="button" class="btn-clear-filter" title="Bersihkan semua filter pencarian">' +
                                    '<i class="fas fa-times"></i>' +
                                '</button>'
                            );

                            $(element).parent().on('click', '.btn-clear-filter', function(e) {
                                e.preventDefault();
                                e.stopPropagation();
                                var grid = $grid[0];
                                if (grid && typeof grid.clearToolbar === 'function') {
                                    grid.clearToolbar(false);
                                    syncColumnClearButtons();
                                }
                                $grid.jqGrid('clearGlobalSearch');
                                reloadApprovalGrid();
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
                        // Modul ini tidak punya kolom id tunggal -- identitas
                        // barisnya kunci gabungan FKGdg|FKSupir yang dirakit
                        // server (ApprovalAbsensiService::mapRows).
                        var idVal = rowData.IdTarget;
                        var isChecked = (selectedRows.indexOf(String(idVal)) !== -1) ? 'checked' : '';
                        return `<div class="rn-cell">
                            <input type="checkbox" class="checkbox-jqgrid" value="${idVal}" onchange="checkboxHandler(this)" ${isChecked}>
                            <span class="rn-number"></span>
                        </div>`;
                    },
                },
                { label: 'Target', name: 'IdTarget', hidden: true, key: true },
                { label: 'Tanggal', name: 'FTgl', width: 120, align: 'center' },
                { label: 'No Polisi', name: 'FKGdg', width: 220 },
                { label: 'Supir', name: 'FKSupir', width: 300 }
            ],
            autowidth: true,
            shrinkToFit: isDesktop,
            height: 360,
            rowNum: 50,
            toolbar: [true, "top"],
            sortname: "FKGdg",
            sortorder: "asc",
            rownumbers: false,
            multiselect: false,
            onSortCol: function(index, iCol, sortorder) {
                this.p.sortorder = sortorder;
                this.p.sortname = index;
                var pd = $(this).jqGrid('getGridParam', 'postData');
                if (pd) {
                    pd.sidx = index;
                    pd.sord = sortorder;
                }

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
            loadComplete: function() {
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
                        // jqID WAJIB: id baris di sini gabungan No Polisi & Supir
                        // ("B 9136 S|HARI SUYETNO", lihat ApprovalAbsensiService::kunci),
                        // dan spasi maupun "|" bikin jQuery menolak selector 'tr#...'
                        // dgn "Syntax error, unrecognized expression".
                        $grid.find('tr#' + $.jgrid.jqID(id) + ' .rn-number').text(no);
                    });
                }

                $grid.find('input.checkbox-jqgrid:checked').closest('tr').addClass('row-selected');

                if (state && ids.length && state.minPageLoaded === 1 && !$grid.jqGrid('getGridParam', 'selrow')) {
                    if (lastFocusedSearchInputId) {
                        $grid.resetSelection().setSelection(ids[0]);
                    } else {
                        setTimeout(function() {
                            selectGridRow($grid, ids[0]);
                        }, 50);
                    }
                }

                if (typeof setupLazyLoadScrollHandler === 'function') {
                    setupLazyLoadScrollHandler("#jqGrid", apiUrl, $grid.jqGrid('getGridParam', 'postData'));
                }
            }
        });

        setCustomBindKeys($grid, {
            getAbsoluteIndex: function(grid, rowid) {
                var num = parseInt(grid.find('tr#' + $.jgrid.jqID(rowid) + ' .rn-number').text(), 10);
                return isNaN(num) ? 0 : num - 1;
            },
            onSpace: function(rowid) {
                if (!rowid) return;
                $grid.find('tr#' + $.jgrid.jqID(rowid) + ' input.checkbox-jqgrid').trigger('click');
            },
            onEnter: function(rowid) {
                if (!rowid) return;
                $grid.find('tr#' + $.jgrid.jqID(rowid) + ' input.checkbox-jqgrid').trigger('click');
            }
        });

        $grid.jqGrid('filterToolbar', {
            stringResult: true,
            searchOnEnter: false,
            autosearch: false,
            defaultSearch: "cn",
            icon: false
        });

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

        // Kotak pencarian global + tombol pager (APPROVAL/UN), sama persis
        // dengan approval/top. customPager() inilah yang merender tombolnya;
        // sebelumnya di sini dipanggil registerCustomPager()/renderCustomPager()
        // yang tidak ada di pager.js -- karena penjaga `typeof … === 'function'`,
        // pemanggilannya diam saja dan tombol Approve/Un Approve tidak pernah
        // muncul. globalSearch() juga wajib dipasang: clearGlobalSearch() sudah
        // dipanggil di triggerColumnFilterSearch() & tombol bersihkan filter.
        $grid.globalSearch({
            beforeSearch: function() {
                $grid[0].clearToolbar(false);
                syncColumnClearButtons();
            },
            reload: reloadApprovalGridKeepFocus
        }).customPager({
            lazyLoading: true,
            modalBtnList: [{
                id: 'approve',
                title: 'Approve',
                caption: 'Approve',
                innerHTML: '<i class="fa fa-check"></i> APPROVAL/UN',
                class: 'btn btn-purple btn-sm mr-1 ',
                item: approvalMenuItems
            }]
        });

        $('#btnReload').click(function() {
            resetSelectionAndReload();
        });

        // Isi modal dibangun ulang pager.js tiap kali tombol pager ditekan, jadi
        // cukup memperbarui flag `hidden` tiap item -- tidak ada render manual.
        $('#prosesdata').change(function() {
            syncApprovalMenuState();
            resetSelectionAndReload();
        });

        // Samakan status tombol modal dgn filter yg aktif saat halaman dibuka --
        // tanpa ini `hidden` masih undefined dan KEDUA tombol aktif sampai user
        // menyentuh filternya sekali.
        syncApprovalMenuState();

        // datatype:'local' tidak memuat data otomatis, jadi trigger load halaman 1 di sini.
        reloadApprovalGrid();
    });
</script>
