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
    .myAltRowClass { background-color: #f8f9fa; }
    
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
                <div class="col-md-2">
                    <div class="form-group filter-input-group">
                        <label class="filter-label">&nbsp;</label>
                        <button type="button" id="btnReload" class="btn btn-default form-control">
                            <i class="fas fa-sync-alt"></i> Reload
                        </button>
                    </div>
                </div>
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
            </div>
        </div>
    </div>

    <!-- Grid Card -->
    <div class="card card-default">
        <div class="card-header">
            <h3 class="card-title">List Approved/Unapproved PO</h3>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="jqGrid"></table>
                <div id="jqGridPager"></div>
            </div>
        </div>
        <div class="card-footer bg-white">
            <button type="button" id="btnApprovedExec" class="btn btn-primary">
                <i class="fas fa-check"></i> Approved/Unapproved
            </button>
        </div>
    </div>
</div>

<script>
    var apiUrl = "<?= base_url('approvalpo/ajax_list') ?>" + window.location.search;
    var $grid = $("#jqGrid");
    
    $(document).ready(function() {
        
        // Ambil data hari libur dari endpoint internal Harilibur
        var holidays = [];
        var currentYear = new Date().getFullYear();
        $.get('<?= base_url('harilibur') ?>?year=' + currentYear, function(data) {
            if (data && data.length > 0) {
                holidays = data.map(function(item) {
                    return item.holiday_date;
                });
            }
        });

        // Init Datepicker
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
                    $grid.setGridParam({datatype: 'json', page: 1}).trigger("reloadGrid");
                }
            });
        }

        const isDesktop = (window.innerWidth > 768);

        $grid.jqGrid({
            styleUI: 'Bootstrap4',
            iconSet: 'fontAwesome',
            url: apiUrl,
            mtype: "GET",
            datatype: "json",
            postData: {
                tgl: function() { 
                    var d = $('#datepicker').val().split('-');
                    return d.length === 3 ? d[2] + '-' + d[1] + '-' + d[0] : '';
                }
            },
            jsonReader: {
                root: "rows",
                page: "page",
                total: "total",
                records: "records",
                repeatitems: false,
                id: "IdTarget" 
            },
            colModel: [
                { label: 'Target', name: 'IdTarget', hidden: true, key: true },
                { label: 'Tanggal', name: 'FTgl', width: 90, align: 'center' },
                { label: 'Shipper', name: 'FNShipper', width: 250 },
                { label: 'Saldo Piutang', name: 'FSaldoPiutang', width: 120, align: 'right' },
                { label: 'Sisa Piutang', name: 'FSisaPiutang', width: 120, align: 'right' },
                { label: 'Lebih Piutang', name: 'FKelebihanPiutang', width: 120, align: 'right' },
                { label: 'Jumlah Order', name: 'FJumlahOrder', width: 120, align: 'right' },
                { label: 'App', name: 'FIsApp', width: 50, align: 'center', sortable: false },
                { label: 'Tgl App', name: 'FDateApp', width: 130, align: 'center' },
                { label: 'User App', name: 'FUserApp', width: 100 },
                { label: 'Tgl Input', name: 'FTglInput', width: 130, align: 'center' },
                { label: 'User Input', name: 'FUserId', width: 100 }
            ],
            autowidth: true,
            shrinkToFit: false,
            height: 400,
            rowNum: 50,
            rowList: [10, 20, 50, 100],
            pager: '#jqGridPager',
            viewrecords: true,
            scroll: 1,
            rownumbers: true,
            multiselect: true, 
            altRows: true,
            altclass: 'myAltRowClass',
            loadComplete: function() {
            },
            gridComplete: function() {
                var $grid = $(this);
                $grid.jqGrid('bindKeys', {
                    onSpace: function(rowid) {
                        var isSelected = $grid.jqGrid('getGridParam', 'selarrrow').indexOf(rowid) !== -1;
                        if(isSelected) {
                            $grid.jqGrid('setSelection', rowid, false); 
                        } else {
                            $grid.jqGrid('setSelection', rowid, true); 
                        }
                    },
                    onEnter: function(rowid) {
                        var isSelected = $grid.jqGrid('getGridParam', 'selarrrow').indexOf(rowid) !== -1;
                        if(isSelected) {
                            $grid.jqGrid('setSelection', rowid, false); 
                        } else {
                            $grid.jqGrid('setSelection', rowid, true); 
                        }
                    }
                });
            }
        });

        // Aktifkan Filter Toolbar jqGrid native Bootstrap 4 ala belajarci4
        $grid.jqGrid('filterToolbar', {
            stringResult: true,
            searchOnEnter: true,
            defaultSearch: "cn",
            icon: false
        });

        $('#datepicker').on('change', function () {
            $grid.setGridParam({datatype: 'json', page: 1}).trigger("reloadGrid");
        });

        $("#btnReload").on('click', function(){
            $grid.setGridParam({datatype: 'json', page: 1}).trigger("reloadGrid");
        });

        $("#btnApprovedExec").on('click', function(){
            var selRowIds = $grid.jqGrid('getGridParam', 'selarrrow');
            if(selRowIds.length === 0) {
                alert("Harus pilih minimal satu baris!");
                return;
            }

            var firstSelectedId = selRowIds[0];
            
            $("#btnApprovedExec").attr('disabled', 'disabled').html('<i class="fas fa-spinner fa-spin"></i> Loading...');
            
            if(confirm("Yakin akan melanjutkan proses ?")) {
                $.ajax({
                    type: "POST",
                    url: "<?= base_url('approvalpo/approved') ?>",
                    data: {
                        fntrans: firstSelectedId,
                        '<?= csrf_token() ?>': '<?= csrf_hash() ?>' 
                    },
                    success: function(result) {
                        $("#btnApprovedExec").removeAttr('disabled').html('<i class="fas fa-check"></i> Approved/Unapproved');
                        
                        if(result.error && result.error !== "") {
                            alert(result.error);
                        } else if (result.msg) {
                            alert(result.msg);
                            $grid.setGridParam({datatype: 'json', page: 1}).trigger("reloadGrid");
                        }
                    },
                    error: function(err) {
                        $("#btnApprovedExec").removeAttr('disabled').html('<i class="fas fa-check"></i> Approved/Unapproved');
                        alert('Terjadi Kesalahan Coba Lagi');
                    }
                });
            } else {
                $("#btnApprovedExec").removeAttr('disabled').html('<i class="fas fa-check"></i> Approved/Unapproved');
            }
        });
    });
</script>
