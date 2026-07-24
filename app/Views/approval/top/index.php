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
    
    /* Warna merah untuk tanggal libur & hari minggu */
    .holiday-date a.ui-state-default {
        color: #dc3545 !important;
        font-weight: bold !important;
    }
    /* Warna hijau untuk hari sabtu (mengoverride .ui-datepicker-week-end jika ada) */
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
                        <button type="button" id="btnApprovedExec" class="btn btn-primary form-control">
                            <i class="fas fa-check"></i> Approved
                        </button>
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
    var apiUrl = "<?= base_url('approvaltop/ajax_list') ?>";
    var $grid = $("#jqGrid");
    
    $(document).ready(function() {
        
        // Ambil data hari libur dari endpoint internal Harilibur
        var holidays = [];
        var currentYear = new Date().getFullYear();
        $.get('<?= base_url('harilibur') ?>?year=' + currentYear, function(data) {
            if (data && data.length > 0) {
                holidays = data.map(function(item) {
                    return item.holiday_date; // ex: '2026-08-17'
                });
            }
        });

        // Init plugins
        if($.fn.datepicker) {
            $('.datepicker').datepicker({
                dateFormat: 'dd-mm-yy',
                changeMonth: true,
                changeYear: true,
                beforeShowDay: function(date) {
                    var day = date.getDay();
                    var d = date.getFullYear() + '-' + ('0' + (date.getMonth()+1)).slice(-2) + '-' + ('0' + date.getDate()).slice(-2);
                    
                    // Hari minggu (0) atau hari libur dari API
                    if (day === 0 || holidays.indexOf(d) !== -1) {
                        return [true, 'holiday-date']; 
                    } else if (day === 6) { // Hari Sabtu
                        return [true, 'saturday-date'];
                    }
                    return [true, ''];
                },
                onSelect: function(dateText) {
                    $(this).val(dateText);
                    $grid.trigger("reloadGrid", [{page: 1}]);
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
            postData: {
                tgl: function() { 
                    // Convert dd-mm-yyyy to yyyy-mm-dd
                    var d = $('#datepicker').val().split('-');
                    return d.length === 3 ? d[2] + '-' + d[1] + '-' + d[0] : '';
                },
                bit: function() { return $('#prosesdata').val(); }
            },
            jsonReader: {
                root: "data",
                repeatitems: false
            },
            colModel: [
                // Kolom ke-0: Checkbox Target (di-handle oleh multiselect: true)
                // Kolom ke-1 dst diambil dari output ajax_list [FJurnal, FNShipper, dll]
                // Di jqGrid, mapping jsonReader ke array bisa diset dengan name sesuai index (0,1,2..) 
                // atau mapping ulang controller. Untuk minimal-diff, kita set nama berurut:
                { label: 'Jurnal', name: '0', hidden: true },
                { label: 'No Jurnal', name: '1', width: 150 },
                { label: 'Shipper', name: '2', width: 250 },
                { label: 'Tanggal', name: '3', width: 120, align: 'center' },
                { label: 'Marketing', name: '4', width: 200 },
                { label: 'Jumlah Invoice', name: '5', width: 120, align: 'right' },
                { label: 'Jumlah Job', name: '6', width: 120, align: 'right' }
            ],
            autowidth: true,
            shrinkToFit: isDesktop,
            height: 400,
            rowNum: 50,
            rowList: [10, 20, 50, 100],
            pager: '#jqGridPager',
            viewrecords: true,
            rownumbers: true,
            multiselect: true, // Pengganti checkboxes Datatables
            subGrid: true, // Expandable details
            subGridRowExpanded: function(subgrid_id, row_id) {
                // row_id adalah nomor baris. Kita butuh No Jurnal
                var rowData = $(this).jqGrid('getRowData', row_id);
                var noJurnal = rowData['1'];
                
                var subgrid_table_id = subgrid_id + "_t";
                $("#" + subgrid_id).html("<div class='p-3'><table id='" + subgrid_table_id + "' class='table table-sm table-bordered'></table></div>");
                
                $.ajax({
                    type: "GET",
                    url: "<?= base_url('approvaltop/get_detail') ?>/" + noJurnal,
                    dataType: "json",
                    success: function(result) {
                        var html = '<thead class="thead-light"><tr><th>No Invoice</th><th>No Piutang</th><th>Tgl Invoice</th><th>Nominal Invoice</th><th>Jumlah Hari</th><th>TOP</th></tr></thead><tbody>';
                        for(var i=0; i<result.length; i++){
                            html += '<tr><td>' + result[i].FNInvoice+'</td><td>'+result[i].FNPiutg+'</td><td>' + result[i].FTglInvoice +'</td><td>'+result[i].FNominalInvoice +'</td><td>' + result[i].FJumlahHari + '</td><td>' +  result[i].FTop + '</td></tr>';
                        }
                        html += '</tbody>';
                        $("#" + subgrid_table_id).html(html);
                    }
                });
            },
            altRows: true,
            altclass: 'myAltRowClass',
            loadComplete: function() {
                // Styling adjustment
            }
        });

        // Event listener filter tidak perlu 'changeDate' karena sudah ditangani onSelect JQuery UI Datepicker
        $('#datepicker').on('change', function () {
            $grid.trigger("reloadGrid", [{page: 1}]);
        });

        $("#prosesdata").on('change', function(){
            $grid.trigger("reloadGrid", [{page: 1}]);
        });

        $("#btnReload").on('click', function(){
            $grid.trigger("reloadGrid", [{page: 1}]);
        });

        $("#btnApprovedExec").on('click', function(){
            var selRowIds = $grid.jqGrid('getGridParam', 'selarrrow');
            if(selRowIds.length === 0) {
                alert("Silakan pilih minimal satu baris!");
                return;
            }

            // Ambil No Jurnal dari masing-masing baris yang dipilih
            var idsToSend = [];
            for(var i=0; i<selRowIds.length; i++) {
                var rowData = $grid.jqGrid('getRowData', selRowIds[i]);
                idsToSend.push(rowData['1']);
            }

            var prosesdata = $('#prosesdata').val();
            if(confirm("Yakin akan melanjutkan proses ?")) {
                $.ajax({
                    type: "POST",
                    url: "<?= base_url('approvaltop/approved') ?>",
                    data: {
                        id: idsToSend,
                        prosesdata: prosesdata,
                        '<?= csrf_token() ?>': '<?= csrf_hash() ?>' // Jika CSRF aktif
                    },
                    success: function(result) {
                        $grid.trigger("reloadGrid", [{page: 1}]);
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
        });
    });
</script>