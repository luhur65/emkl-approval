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
    <div class="card card-default">
        <div class="card-header">
            <h3 class="card-title">List Approved/Unapproved</h3>
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
    var apiUrl = "<?= base_url('approvaltop/ajax_list') ?>" + window.location.search;
    var $grid = $("#jqGrid");
    
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
                    $grid.setGridParam({datatype: 'json', page: 1}).trigger("reloadGrid");
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
            mtype: "GET",
            datatype: "json",
            loadonce: false,
            postData: {
                tgl: function() { 
                    var d = $('#datepicker').val().split('-');
                    return d.length === 3 ? d[2] + '-' + d[1] + '-' + d[0] : '';
                },
                bit: function() { return $('#prosesdata').val(); }
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
                { label: 'No Jurnal', name: 'FJurnal', width: 150 },
                { label: 'Shipper', name: 'FNShipper', width: 250 },
                { label: 'Tanggal', name: 'FTgl', width: 120, align: 'center' },
                { label: 'Marketing', name: 'FNMarketing', width: 200 },
                { label: 'Jumlah Invoice', name: 'FJumlahInvoice', width: 120, align: 'right' },
                { label: 'Jumlah Job', name: 'FJumlahjob', width: 120, align: 'right' }
            ],
            autowidth: true,
            shrinkToFit: isDesktop,
            height: 400,
            rowNum: 50,
            rowList: [10, 20, 50, 100],
            pager: '#jqGridPager',
            viewrecords: true,
            scroll: 1,
            sortname: "FJurnal",
            sortorder: "asc", 
            rownumbers: true,
            multiselect: true, 
            subGrid: true, 
            subGridRowExpanded: function(subgrid_id, row_id) {
                var rowData = $(this).jqGrid('getRowData', row_id);
                var noJurnal = rowData['FJurnal'];
                
                var subgrid_table_id = subgrid_id + "_t";
                $("#" + subgrid_id).html("<div class='p-3'><table id='" + subgrid_table_id + "' class='table table-sm table-bordered'></table></div>");
                
                if (window.subGridRequests === undefined) window.subGridRequests = {};
                if (window.subGridRequests[row_id]) {
                    window.subGridRequests[row_id].abort();
                }

                window.subGridRequests[row_id] = $.ajax({
                    type: "GET",
                    url: "<?= site_url('approvaltop/get_detail') ?>/" + noJurnal,
                    dataType: "json",
                    success: function(result) {
                        var html = '<thead class="thead-light"><tr><th>No Invoice</th><th>No Piutang</th><th>Tgl Invoice</th><th>Nominal Invoice</th><th>Jumlah Hari</th><th>TOP</th></tr></thead><tbody>';
                        for(var i=0; i<result.length; i++){
                            html += '<tr><td>' + result[i].FNInvoice+'</td><td>'+result[i].FNPiutg+'</td><td>' + result[i].FTglInvoice +'</td><td>'+result[i].FNominalInvoice +'</td><td>' + result[i].FJumlahHari + '</td><td>' +  result[i].FTop + '</td></tr>';
                        }
                        html += '</tbody>';
                        $("#" + subgrid_table_id).html(html);
                    },
                    complete: function() {
                        delete window.subGridRequests[row_id];
                    }
                });
            },
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
                }
            },
            gridComplete: function() {
                var $grid = $(this);
                $grid.jqGrid('bindKeys', {
                    onRightKey: function(rowid) {
                        $grid.jqGrid('expandSubGridRow', rowid);
                    },
                    onLeftKey: function(rowid) {
                        $grid.jqGrid('collapseSubGridRow', rowid);
                    },
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

        // Hapus filterToolbar ala Trucking dan kembalikan jqgrid murni (seperti project belajarci4)
        // (Biasanya belajarci4 menggunakan filter toolbar native tanpa CSS diubah, kita aktifkan saja filter default jqGrid)
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
                        var selRowIds = $grid.jqGrid('getGridParam', 'selarrrow');
                        if(selRowIds.length === 0) {
                            alert("Silakan pilih minimal satu baris!");
                            return;
                        }

                        var idsToSend = [];
                        for(var i=0; i<selRowIds.length; i++) {
                            var rowData = $grid.jqGrid('getRowData', selRowIds[i]);
                            idsToSend.push(rowData['FJurnal']);
                        }

                        var prosesdata = $('#prosesdata').val();
                        if(confirm("Yakin akan melanjutkan proses ?")) {
                            $.ajax({
                                type: "POST",
                                url: "<?= base_url('approvaltop/approved') ?>",
                                data: {
                                    id: idsToSend,
                                    prosesdata: prosesdata,
                                    '<?= csrf_token() ?>': '<?= csrf_hash() ?>' 
                                },
                                success: function(result) {
                                    $grid.setGridParam({datatype: 'json', page: 1}).trigger("reloadGrid");
                                    if(result.msg) {
                                        alert(result.msg);
                                    } else if(result[0] && result[0].message) {
                                        alert(result[0].message);
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

        $("#prosesdata").on('change', function(){
            $grid.setGridParam({datatype: 'json', page: 1}).trigger("reloadGrid");
        });

        $("#btnReload").on('click', function(){
            $grid.setGridParam({datatype: 'json', page: 1}).trigger("reloadGrid");
        });

    });
</script>