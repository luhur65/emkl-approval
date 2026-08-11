<?php
/**
 * Pengajuan Trip -- sisi ENTRI (mandor). Port dari CI3
 * application/views/approval/trip/index.php.
 *
 * Perbedaan yang disengaja dari CI3:
 *  - Grid memakai jqGrid + lazy loading, sama seperti seluruh modul CI4 lain,
 *    bukan DataTables yang menarik seluruh baris sekaligus.
 *  - Tombol Hapus tidak dirender untuk baris yang sudah di-approve; CI3
 *    merendernya di semua baris lalu menolaknya di server setelah diklik.
 *  - Hapus dikirim POST (CI3 memakai URL yang juga menerima GET).
 *
 * $mandor: daftar mandor aktif dari PengajuanTripService::daftarMandor().
 */
?>
<style>
    .filter-input-group { margin-bottom: 0; }
    .filter-label { font-weight: 500; margin-bottom: 0.2rem; font-size: 0.9rem; }
    .btn-block { height: 38px; }

    /* Datepicker Colors */
    .holiday-date a.ui-state-default { color: #dc3545 !important; font-weight: bold !important; }
    .saturday-date a.ui-state-default { color: #28a745 !important; font-weight: bold !important; }

    .status-approved { color: #28a745; font-weight: 600; }
    .status-belum { color: #dc3545; font-weight: 600; }
</style>

<div class="container-fluid">
    <!-- Filter: menentukan isi grid saja, bukan tanggal yang disimpan -->
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
                        <label class="filter-label">&nbsp;</label>
                        <div class="d-flex">
                            <button type="button" id="btnReload" class="btn btn-primary flex-fill mr-2">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                            <button type="button" id="btnReset" class="btn btn-secondary flex-fill">
                                <i class="fas fa-undo"></i> Reset
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Form entri -->
    <div class="card card-primary card-outline">
        <div class="card-header py-2">
            <h3 class="card-title" style="font-size: 1rem;">Buat Pengajuan Trip</h3>
        </div>
        <div class="card-body">
            <form id="frm-pengajuan" autocomplete="off">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group filter-input-group">
                            <label class="filter-label" for="mandor">Mandor</label>
                            <select class="form-control select2" id="mandor" name="mandor">
                                <?php if (empty($mandor)): ?>
                                    <option value="">(daftar mandor tidak dapat dimuat)</option>
                                <?php else: ?>
                                    <?php foreach ($mandor as $item): ?>
                                        <option value="<?= esc($item['FKMandor'], 'attr') ?>">
                                            <?= esc($item['FKMandor']) ?> (<?= esc($item['FNMandor']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-2">
                        <div class="form-group filter-input-group">
                            <label class="filter-label" for="tgl_save">Tanggal</label>
                            <!-- Mengikuti CI3: pengajuan selalu untuk HARI INI,
                                 tidak mengikuti filter tanggal di atas. Dibuat
                                 readonly supaya bedanya terlihat jelas. -->
                            <input type="date" id="tgl_save" class="form-control" value="<?= date('Y-m-d') ?>" readonly>
                        </div>
                    </div>

                    <div class="col-md-2">
                        <div class="form-group filter-input-group">
                            <label class="filter-label" for="jlh_trip">Jumlah Trip</label>
                            <input type="number" id="jlh_trip" class="form-control" min="1" max="100" step="1" placeholder="1 - 100">
                        </div>
                    </div>

                    <div class="col-md-2">
                        <div class="form-group filter-input-group">
                            <label class="filter-label">&nbsp;</label>
                            <button type="submit" id="btnSimpan" class="btn btn-primary form-control">
                                <i class="fas fa-save"></i> Simpan
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Grid -->
    <table id="jqGrid"></table>
    <div id="jqGridPager"></div>
</div>

<script>
    var apiUrl = "<?= base_url('approvaltrip/ajax_list_pengajuan') ?>" + window.location.search;
    var $grid = $("#jqGrid");

    // Datepicker menampilkan d-m-Y; server & query memakai Y-m-d.
    function tanggalTerpilih() {
        var d = $('#datepicker').val().split('-');
        return d.length === 3 ? d[2] + '-' + d[1] + '-' + d[0] : '';
    }

    function keHtmlAman(teks) {
        return $('<div>').text(teks).html().replace(/\n/g, '<br>');
    }

    function pesanGagalAjax(xhr) {
        if (xhr.status === 401) return 'Sesi Anda telah berakhir. Halaman akan dimuat ulang untuk login.';
        if (xhr.responseJSON && xhr.responseJSON.error) return xhr.responseJSON.error;
        return 'Terjadi Kesalahan Coba Lagi';
    }

    function tampilkanLoading() { $('.modal-loader').removeClass('d-none'); }
    function sembunyikanLoading() { $('.modal-loader').addClass('d-none'); }

    function reloadGridPengajuan(callback) {
        if (typeof loadGridData === 'function') {
            if (typeof lazyStates !== 'undefined' && lazyStates['jqGrid']) lazyStates['jqGrid'].cachedData = {};
            loadGridData("#jqGrid", apiUrl, $grid.jqGrid('getGridParam', 'postData'), 1, $grid.jqGrid('getGridParam', 'rowNum'), 'jump', 'reload', callback);
        } else {
            $grid.setGridParam({ datatype: 'json', page: 1 }).trigger("reloadGrid");
            if (typeof callback === 'function') callback();
        }
    }

    // Satu penjaga untuk kedua aksi tulis: tanpa ini, klik ganda pada Simpan
    // membuat dua pengajuan yang isinya sama persis.
    var prosesSedangJalan = false;

    function kirimAksi(opsi) {
        if (prosesSedangJalan) return;

        $.ajax({
            type: 'POST',
            url: opsi.url,
            data: $.extend({ '<?= csrf_token() ?>': '<?= csrf_hash() ?>' }, opsi.data || {}),
            beforeSend: function() {
                prosesSedangJalan = true;
                tampilkanLoading();
            },
            success: function(result) {
                if (result && result.error) {
                    showDialog(keHtmlAman(result.error));
                    return;
                }

                reloadGridPengajuan();

                if (typeof opsi.onSukses === 'function') opsi.onSukses();

                showDialog({
                    statuspesan: 'success',
                    message: keHtmlAman((result && result.msg) || 'Proses berhasil.')
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
                prosesSedangJalan = false;
                sembunyikanLoading();
            }
        });
    }

    function hapusPengajuan(fid) {
        showConfirm('Konfirmasi Hapus', 'Hapus pengajuan trip ini?').then(function() {
            kirimAksi({ url: '<?= base_url('approvaltrip/hapus') ?>/' + encodeURIComponent(fid) });
        });
    }

    function triggerColumnFilterSearch() {
        var colModel = $grid.jqGrid('getGridParam', 'colModel');
        var rules = [];

        colModel.forEach(function(cm) {
            if (cm.search === false) return;
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

        reloadGridPengajuan();
    }

    // Kembalikan filter grid ke kondisi awal halaman (tanggal hari ini) lalu
    // buang pencarian global + filter per-kolom. Form entri di bawah tidak
    // disentuh -- kartu ini memang cuma menentukan isi grid.
    function resetFilterGrid() {
        $('#datepicker').val('<?= date('d-m-Y') ?>');

        $grid[0].clearToolbar(false);
        $grid.jqGrid('clearGlobalSearch');
        $grid.jqGrid('setGridParam', { search: false, postData: { filters: '' } });

        reloadGridPengajuan();
    }

    $(document).ready(function() {
        var holidays = [];
        $.get('<?= base_url('harilibur') ?>?year=' + new Date().getFullYear(), function(data) {
            if (data && data.length > 0) {
                holidays = data.map(function(item) { return item.holiday_date; });
            }
        });

        if ($.fn.datepicker) {
            $('.datepicker').datepicker({
                dateFormat: 'dd-mm-yy',
                changeMonth: true,
                changeYear: true,
                beforeShowDay: function(date) {
                    var day = date.getDay();
                    var d = date.getFullYear() + '-' + ('0' + (date.getMonth() + 1)).slice(-2) + '-' + ('0' + date.getDate()).slice(-2);
                    if (day === 0 || holidays.indexOf(d) !== -1) return [true, 'holiday-date'];
                    if (day === 6) return [true, 'saturday-date'];
                    return [true, ''];
                },
                onSelect: function(dateText) {
                    $(this).val(dateText);
                    reloadGridPengajuan();
                }
            });
        }

        if ($('.select2').length > 0) {
            $('.select2').select2({ theme: 'bootstrap4' });
        }

        const isDesktop = (window.innerWidth > 768);

        $grid.jqGrid({
            styleUI: 'Bootstrap4',
            iconSet: 'fontAwesome',
            url: apiUrl,
            mtype: "POST",
            datatype: "local",
            postData: { tgl: tanggalTerpilih },
            colModel: [
                {
                    label: 'NO.',
                    name: 'nomor',
                    width: 60,
                    align: 'center',
                    fixed: true,
                    sortable: false,
                    search: false,
                    formatter: function() { return '<span class="rn-number"></span>'; }
                },
                { label: 'Target', name: 'IdTarget', hidden: true, key: true },
                { label: 'Boleh Hapus', name: 'BolehHapus', hidden: true, search: false },
                // Urutan kolom mengikuti <thead> view CI3.
                { label: 'Tanggal', name: 'FTgl', width: 110, align: 'center' },
                { label: 'Jumlah Trip', name: 'FJlhTrip', width: 110, align: 'right' },
                { label: 'Mandor', name: 'FMandor', width: 220 },
                {
                    label: 'Status',
                    name: 'FStatus',
                    width: 150,
                    align: 'center',
                    formatter: function(value) {
                        var kelas = (value === 'APPROVED') ? 'status-approved' : 'status-belum';
                        return '<span class="' + kelas + '">' + value + '</span>';
                    }
                },
                { label: 'User', name: 'FUserID', width: 140 },
                { label: 'Tgl Input', name: 'FTglInput', width: 160, align: 'center' },
                {
                    label: 'Aksi',
                    name: 'aksi',
                    width: 90,
                    align: 'center',
                    fixed: true,
                    sortable: false,
                    search: false,
                    formatter: function(value, rowOptions, rowData) {
                        // Baris yang sudah di-approve tidak diberi tombol sama
                        // sekali -- server tetap menolaknya, ini supaya user
                        // tidak menekan sesuatu yang pasti gagal.
                        if (String(rowData.BolehHapus) !== '1') return '';
                        return '<button type="button" class="btn btn-sm btn-danger btn-hapus" ' +
                               'data-fid="' + rowData.IdTarget + '" title="Hapus pengajuan">' +
                               '<i class="fas fa-trash"></i></button>';
                    }
                }
            ],
            autowidth: true,
            shrinkToFit: isDesktop,
            height: 320,
            rowNum: 50,
            toolbar: [true, "top"],
            sortname: "FTglInput",
            sortorder: "desc",
            rownumbers: false,
            multiselect: false,
            onSortCol: function(index, iCol, sortorder) {
                this.p.sortorder = sortorder;
                this.p.sortname = index;
                var pd = $(this).jqGrid('getGridParam', 'postData');
                if (pd) { pd.sidx = index; pd.sord = sortorder; }

                var previousSelectedTh = this.grid.headers[this.p.lastsort].el;
                var newSelectedTh = this.grid.headers[iCol].el;
                $("span.s-ico", previousSelectedTh).hide();
                $("span.s-ico", newSelectedTh).show();
                $("span.ui-icon-asc, span.ui-icon-desc", newSelectedTh).addClass("ui-state-disabled");
                $("span.ui-icon-" + sortorder, newSelectedTh).removeClass("ui-state-disabled");
                this.p.lastsort = iCol;

                if (typeof lazyStates !== 'undefined' && lazyStates['jqGrid']) lazyStates['jqGrid'].cachedData = {};
                if (typeof loadGridData === 'function') {
                    loadGridData("#jqGrid", apiUrl, $grid.jqGrid('getGridParam', 'postData'), 1, $grid.jqGrid('getGridParam', 'rowNum'), 'jump', 'reload');
                }
                return 'stop';
            },
            loadComplete: function() {
                // Penomoran baris mengikuti modul approval lain: nomornya
                // dihitung dari halaman yang sudah dimuat lazy loading, bukan
                // dari urutan baris yang kebetulan tampil.
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

                if (typeof setupLazyLoadScrollHandler === 'function') {
                    setupLazyLoadScrollHandler("#jqGrid", apiUrl, $grid.jqGrid('getGridParam', 'postData'));
                }
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
        $(document).on('input', '#gview_jqGrid tr.ui-search-toolbar input', function() {
            clearTimeout(columnFilterTimer);
            columnFilterTimer = setTimeout(triggerColumnFilterSearch, 500);
        });
        $(document).on('keydown', '#gview_jqGrid tr.ui-search-toolbar input', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                clearTimeout(columnFilterTimer);
                triggerColumnFilterSearch();
            }
        });

        $grid.globalSearch({ reload: reloadGridPengajuan }).customPager({ lazyLoading: true });

        // Tombol Hapus dirender ulang tiap grid dimuat, jadi handler-nya
        // didelegasikan ke wadah grid -- bukan dipasang ke tiap tombol.
        $(document).on('click', '#jqGrid .btn-hapus', function() {
            hapusPengajuan($(this).data('fid'));
        });

        $('#btnReload').click(function() { reloadGridPengajuan(); });

        $('#btnReset').click(function() { resetFilterGrid(); });

        $('#frm-pengajuan').on('submit', function(e) {
            e.preventDefault();

            var jlh = $('#jlh_trip').val();
            var mandor = $('#mandor').val();

            if (!mandor) {
                showDialog('Mandor belum dipilih.');
                return;
            }
            if (!jlh) {
                showDialog('Jumlah trip belum diisi.');
                return;
            }

            showConfirm('Konfirmasi Simpan', 'Simpan pengajuan ' + jlh + ' trip untuk mandor ' + mandor + '?')
                .then(function() {
                    kirimAksi({
                        url: '<?= base_url('approvaltrip/simpan') ?>',
                        data: { tgl: $('#tgl_save').val(), jlh: jlh, mandor: mandor },
                        onSukses: function() { $('#jlh_trip').val(''); }
                    });
                });
        });

        // datatype:'local' tidak memuat data otomatis, jadi trigger halaman 1.
        reloadGridPengajuan();
    });
</script>
