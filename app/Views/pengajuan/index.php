<?php
/**
 * Pengajuan Supir Serap -- sisi ENTRI (mandor). Port dari CI3
 * application/views/approval/pengajuan/index.php.
 *
 * Perbedaan yang disengaja dari CI3:
 *  - Grid memakai jqGrid + lazy loading seperti modul CI4 lain, bukan
 *    DataTables yang menarik seluruh baris sekaligus.
 *  - Kolom "Supir" murni tampilan; nilainya TIDAK ikut dikirim. Server
 *    membacanya sendiri dari master kendaraan (lihat
 *    PengajuanSupirSerapService::simpan) karena input readonly di CI3 tetap
 *    bisa dipalsukan lewat request buatan sendiri.
 *  - Tombol Hapus tidak dirender untuk baris yang sudah di-approve.
 *  - Hapus dikirim POST (CI3 memakai URL yang juga menerima GET).
 *
 * $kendaraan: Gdg aktif (FKGdg, FNopolSTNK, FMilikSupir)
 * $supir    : MSupir non-karyawan aktif (FKSupir)
 */
?>
<style>
    .filter-input-group { margin-bottom: 0; }
    .filter-label { font-weight: 500; margin-bottom: 0.2rem; font-size: 0.9rem; }
    .btn-block { height: 38px; }

    .holiday-date a.ui-state-default { color: #dc3545 !important; font-weight: bold !important; }
    .saturday-date a.ui-state-default { color: #28a745 !important; font-weight: bold !important; }

    .status-approved { color: #28a745; font-weight: 600; }
    .status-belum { color: #dc3545; font-weight: 600; }
</style>

<div class="container-fluid">
    <!-- Filter: menentukan isi grid saja -->
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
            <h3 class="card-title" style="font-size: 1rem;">Buat Pengajuan Supir Serap</h3>
        </div>
        <div class="card-body">
            <form id="frm-pengajuan" autocomplete="off">
                <div class="row">
                    <div class="col-md-2">
                        <div class="form-group filter-input-group">
                            <label class="filter-label" for="tgl_save">Tanggal</label>
                            <input type="date" id="tgl_save" class="form-control"
                                   value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>">
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-group filter-input-group">
                            <label class="filter-label" for="gdg">No Polisi</label>
                            <select class="form-control select2" id="gdg">
                                <option value="">Pilih</option>
                                <?php foreach ($kendaraan as $item): ?>
                                    <option value="<?= esc($item['FKGdg'], 'attr') ?>"
                                            data-supir="<?= esc($item['FMilikSupir'], 'attr') ?>">
                                        <?= esc($item['FNopolSTNK']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-2">
                        <div class="form-group filter-input-group">
                            <label class="filter-label" for="supir">Supir</label>
                            <!-- Hanya tampilan: server membaca supir tetapnya
                                 sendiri dari master berdasarkan No Polisi. -->
                            <input type="text" id="supir" class="form-control" readonly placeholder="(ikut No Polisi)">
                        </div>
                    </div>

                    <div class="col-md-2">
                        <div class="form-group filter-input-group">
                            <label class="filter-label" for="supir_serap">Supir Serap</label>
                            <select class="form-control select2" id="supir_serap">
                                <option value="">Pilih</option>
                                <?php foreach ($supir as $item): ?>
                                    <option value="<?= esc($item['FKSupir'], 'attr') ?>"><?= esc($item['FKSupir']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-2">
                        <div class="form-group filter-input-group">
                            <label class="filter-label" for="keterangan">Keterangan</label>
                            <input type="text" id="keterangan" class="form-control" maxlength="500">
                        </div>
                    </div>

                    <div class="col-md-1">
                        <div class="form-group filter-input-group">
                            <label class="filter-label">&nbsp;</label>
                            <button type="submit" id="btnSimpan" class="btn btn-primary form-control">
                                <i class="fas fa-save"></i>
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
    var apiUrl = "<?= base_url('pengajuan/ajax_list') ?>" + window.location.search;
    var $grid = $("#jqGrid");

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
        showConfirm('Konfirmasi Hapus', 'Hapus pengajuan supir serap ini?').then(function() {
            kirimAksi({ url: '<?= base_url('pengajuan/hapus') ?>/' + encodeURIComponent(fid) });
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

        // Memilih kendaraan mengisi kolom Supir dari master yang sudah ikut
        // dirender sebagai data-supir. Nilai ini tidak dikirim ke server --
        // hanya supaya mandor bisa memastikan kendaraannya benar.
        $('#gdg').on('change', function() {
            $('#supir').val($(this).find('option:selected').data('supir') || '');
        });

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
                { label: 'Tanggal', name: 'FTgl', width: 110, align: 'center' },
                { label: 'No Polisi', name: 'FKGdg', width: 150 },
                { label: 'Supir', name: 'FKSupir', width: 200 },
                { label: 'Supir Serap', name: 'FKSupir_Serap', width: 200 },
                {
                    label: 'Approved',
                    name: 'FStatus',
                    width: 150,
                    align: 'center',
                    formatter: function(value) {
                        var kelas = (value === 'APPROVED') ? 'status-approved' : 'status-belum';
                        return '<span class="' + kelas + '">' + value + '</span>';
                    }
                },
                { label: 'Keterangan', name: 'FKeterangan', width: 320 },
                { label: 'Tgl Input', name: 'FTglInput', width: 160, align: 'center' },
                { label: 'Tgl App', name: 'FTglApp', width: 160, align: 'center' },
                {
                    label: 'Aksi',
                    name: 'aksi',
                    width: 90,
                    align: 'center',
                    fixed: true,
                    sortable: false,
                    search: false,
                    formatter: function(value, rowOptions, rowData) {
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

        $(document).on('click', '#jqGrid .btn-hapus', function() {
            hapusPengajuan($(this).data('fid'));
        });

        $('#btnReload').click(function() { reloadGridPengajuan(); });

        $('#btnReset').click(function() { resetFilterGrid(); });

        $('#frm-pengajuan').on('submit', function(e) {
            e.preventDefault();

            var gdg = $('#gdg').val();
            var supirSerap = $('#supir_serap').val();
            var keterangan = $.trim($('#keterangan').val());

            if (!gdg) { showDialog('No Polisi belum dipilih.'); return; }
            if (!supirSerap) { showDialog('Supir serap belum dipilih.'); return; }
            if (!keterangan) { showDialog('Keterangan wajib diisi.'); return; }

            showConfirm('Konfirmasi Simpan', 'Simpan pengajuan supir serap untuk ' + gdg + '?')
                .then(function() {
                    kirimAksi({
                        url: '<?= base_url('pengajuan/simpan') ?>',
                        data: {
                            tgl: $('#tgl_save').val(),
                            gdg: gdg,
                            supir_serap: supirSerap,
                            keterangan: keterangan
                        },
                        onSukses: function() {
                            $('#keterangan').val('');
                            $('#gdg').val('').trigger('change.select2');
                            $('#supir_serap').val('').trigger('change.select2');
                            $('#supir').val('');
                        }
                    });
                });
        });

        reloadGridPengajuan();
    });
</script>
