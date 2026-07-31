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
    </div>
</div>

<script>
    var apiUrl = "<?= base_url('approvalpo/ajax_list') ?>" + window.location.search;
    var $grid = $("#jqGrid");
    
    var selectedRows = [];

    function checkboxHandler(element) {
        let value = $(element).val();
        if (element.checked) {
            selectedRows.push(value);
            $(element).parents('tr').addClass('bg-light-blue');
        } else {
            $(element).parents('tr').removeClass('bg-light-blue');
            for (var i = 0; i < selectedRows.length; i++) {
                if (selectedRows[i] == value) {
                    selectedRows.splice(i, 1);
                }
            }
        }
    }

    function clearSelectedRows() {
        selectedRows = [];
        $('.checkbox-selectall').prop('checked', false);
        $grid.trigger('reloadGrid');
    }

    function selectAllRows() {
        var ids = $grid.jqGrid('getDataIDs');
        for(var i=0; i<ids.length; i++) {
            var rowData = $grid.jqGrid('getRowData', ids[i]);
            var idTarget = rowData['IdTarget'] || ids[i];
            if(selectedRows.indexOf(idTarget) === -1) {
                selectedRows.push(idTarget);
            }
        }
        $('.checkbox-jqgrid').prop('checked', true).parents('tr').addClass('bg-light-blue');
        $('.checkbox-selectall').attr('disabled', false);
    }

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
                {
                    label: '',
                    name: 'check',
                    width: 70,
                    align: 'left',
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
                            $(element).css('cssText', 'margin: 0 !important; vertical-align: middle; display: inline-block;');
                            $(element).after("<span style='font-weight: bold; vertical-align: middle; margin-left: 10px;'>NO.</span>");

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
                        var idVal = rowData.IdTarget;
                        var isChecked = (selectedRows.indexOf(idVal) !== -1) ? 'checked' : '';
                        return `<div class="d-flex align-items-center justify-content-start">
                            <input type="checkbox" class="checkbox-jqgrid" value="${idVal}" onchange="checkboxHandler(this)" ${isChecked}>
                            <span class="rn-number ml-2"></span>
                        </div>`;
                    },
                },
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
            rownumbers: false,
            multiselect: false, 
            altRows: true,
            altclass: 'myAltRowClass',
            loadComplete: function(data) {
                if(data && data.records !== undefined) {
                    var page = parseInt(data.page, 10) || 1;
                    var totalRecords = parseInt(data.records, 10) || 0;
                    var rows = parseInt($grid.jqGrid('getGridParam', 'rowNum'), 10) || 50;
                    
                    var start = ((page - 1) * rows) + 1;
                    var end = start + data.rows.length - 1;
                    if(totalRecords === 0) { start = 0; end = 0; }
                    
                    // Override the default pager text manually since scroll: 1 accumulates rows in jqgrid
                    $('.ui-paging-info').html('View ' + start + ' - ' + end + ' of ' + totalRecords);

                    // Inject row numbers
                    var ids = $grid.jqGrid('getDataIDs');
                    for (var i = 0; i < ids.length; i++) {
                        var no = start + i;
                        $grid.find('tr#' + ids[i] + ' .rn-number').text(no);
                    }
                }
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
        }).customPager({
            lazyLoading: false,
            modalBtnList: [{
                id: 'approve',
                title: 'Approve',
                caption: 'Approve',
                innerHTML: '<i class="fa fa-check"></i> APPROVAL/UN',
                class: 'btn btn-purple btn-sm mr-1 ',
                item: [{
                    id: 'approvalStatus',
                    text: ' APPROVAL/UN',
                    onClick: () => {
                        if(selectedRows.length === 0) {
                            alert("Harus pilih minimal satu baris!");
                            return;
                        }

                        var firstSelectedId = selectedRows[0];
                        
                        if(confirm("Yakin akan melanjutkan proses ?")) {
                            $.ajax({
                                type: "POST",
                                url: "<?= base_url('approvalpo/approved') ?>",
                                data: {
                                    fntrans: firstSelectedId,
                                    '<?= csrf_token() ?>': '<?= csrf_hash() ?>' 
                                },
                                success: function(result) {
                                    if(result.error && result.error !== "") {
                                        alert(result.error);
                                    } else if (result.msg) {
                                        alert(result.msg);
                                        $grid.setGridParam({datatype: 'json', page: 1}).trigger("reloadGrid");
                                    }
                                },
                                error: function(err) {
                                    alert('Terjadi Kesalahan Coba Lagi');
                                }
                            });
                        }
                    }
                }]
            }]
        });

        $('#datepicker').on('change', function () {
            $grid.setGridParam({datatype: 'json', page: 1}).trigger("reloadGrid");
        });

        $("#btnReload").on('click', function(){
            $grid.setGridParam({datatype: 'json', page: 1}).trigger("reloadGrid");
        });

    });
</script>
