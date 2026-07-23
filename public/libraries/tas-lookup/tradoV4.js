parsePostData = dataParsed


var idLookup = 1;
var idTop 

selector = $(`#${parsePostData.lookupName}`)
var isToolbarSearch = false;

var singleColumn = parsePostData.singleColumn
var filterToolbar = parsePostData.filterToolbar

label = parsePostData.labelColumn

width = ''
//  use this witdh if single column lookup

if (detectDeviceType() == "desktop" && label == false) {
    width = '1500px'
} else {
    width = selector.parents('.input-group').outerWidth() + 'px'
}
console.log(detectDeviceType(),label,detectDeviceType() == "desktop" ,label == false);


column = [
    {
        label: "ID",
        name: "id",
        width: "50px",
        hidden: true,
        search: false,
    },
    {
        label: 'NO POLISI',
        name: 'kodetrado',
        width:'150px'
    },
    {
        label: 'status',
        name: 'statusaktif',
        width:'70px',

        formatter: (value, options, rowData) => {
            let statusAktif = JSON.parse(value)

            let formattedValue = $(`
            <div class="badge" style="background-color: ${statusAktif.WARNA}; color: ${statusAktif.WARNATULISAN};">
                <span>${statusAktif.SINGKATAN}</span>
            </div>
            `)
            return formattedValue[0].outerHTML

        },
        cellattr: (rowId, value, rowObject) => {
            let statusAktif = JSON.parse(rowObject.statusaktif)

            return ` title="${statusAktif.MEMO}"`
        }
    },
    {
        label: 'KM GANTI OLI AKHIR',
        name: 'kmakhirgantioli',
        align: 'right',
        search: false,
        hidden: true,
        formatter: currencyFormat,
    },
    {
        label: 'MEREK',
        name: 'merek',
        search: false,
        hidden: true,
    },
    {
        label: 'NO RANGKA',
        name: 'norangka',
        search: false,
        hidden: true,
    },
    {
        label: 'NO MESIN',
        name: 'nomesin',
        search: false,
        hidden: true,
    },
    {
        label: 'NO STNK',
        name: 'nostnk',
        search: false,
        hidden: true,
    },
    {
        label: 'KETERANGAN',
        name: 'keterangan',
        hidden: false,
        search: true,
    },
    {
        label: 'supir',
        name: 'supir_id',
        search: false,
        hidden: true
    },
    {
        label: 'supirid',
        name: 'supirid',
        search: false,
        hidden: true
    },
    
]

if (typeof tradoLookupType !== 'undefined' && typeof tradoLookupData !== 'undefined') {
    typeData = tradoLookupType;
    filtersData = tradoLookupData;

    if (parsePostData.trado_id && parsePostData.trado_id != '') {
        result = filtersData.filter((word) => word.id == parsePostData.trado_id);
    
    } else if (parsePostData.penerimaanstok_id != '' && filtersData.length > 0 &&  parsePostData.penerimaanstok_id == filtersData[0].penerimaanStokPgForLookup &&  parsePostData.tradodarike != '') {
        if (parsePostData.tradodarike == 'dari' && parsePostData.tradoke_id != '') {
            result = filtersData.filter((word) => word.id != parsePostData.tradoke_id);
            
        } else if (parsePostData.tradodarike == 'ke' && parsePostData.tradodari_id != '') {
            result = filtersData.filter((word) => word.id != parsePostData.tradodari_id);
        } else {
            result = filtersData
        }
    } else {
        result = filtersData
    }

} else {
    typeData = 'JSON'
}

filterPostData = {
    aktif: parsePostData.Aktif || '',
    trado_id: parsePostData.trado_id || '' ,
    cabang: parsePostData.cabang || '' ,
    penerimaanstok_id: parsePostData.penerimaanstok_id || '' ,
    supirserap: parsePostData.supirserap || '' ,
    tglabsensi: parsePostData.tglabsensi || '' ,
    tradodarike: parsePostData.tradodarike || '' ,
    tradodari_id: parsePostData.tradodari_id || '' ,
    tradoke_id: parsePostData.tradoke_id || '' ,
    isLookup: parsePostData.isLookup || true,
    tipeData: typeData
};

urlRequestGrid =  `${apiUrl}trado`;
elementInput.attr('data-lookup-url',urlRequestGrid);
elementInput.data('typeDataLookup', typeData);

if (typeData == 'LOCAL') {
    selector.jqGrid({
        mtype: 'GET',
        styleUI: "Bootstrap4",
        iconSet: "fontAwesome",
        datatype: typeData,
        postData: filterPostData,
        idPrefix: "",
        colModel: column,
        // data: filtersData,
        data: result,
        height: 350,
        fixed: true,
        rownumbers: false,
        rownumWidth: 0,
        rowNum: 10000,
        rowList: [10, 20, 50, 0],
        sortable: true,
        // sortname: "id",
        // sortorder: "asc",
        sortname: "kodetrado",
        sortorder: "asc",
        page: 1,
        toolbar: [true, "top"],
        viewrecords: true,
        prmNames: {
            sort: "sortIndex",
            order: "sortOrder",
            rows: "limit",
        },
        jsonReader: {
            root: "data",
            total: "attributes.totalPages",
            records: 20,
        },
        autowidth: true,
        // scrollOffset: 1,
        // scrollrows: false,
        shrinkToFit: false,
        // scrollLeftOffset: "25%",
        // scroll: true,
        height: 350,
        page: 1,
        selectedIndex: 0,
        triggerClick: false,
        search: true,
        gridComplete: function () {
            $(".loadingMessage").show();
            idTop = selector.attr("id");

            $(`#load_${idTop}`).remove();

            if (detectDeviceType() == "mobile") {
                $(".lookup-grid tr:not(.jqgfirstrow) td").css(
                    "padding",
                    "12px"
                );
                $(".lookup-grid tr:not(.jqgfirstrow) td").css(
                    "font-size",
                    "1rem"
                );

                $(`#gview_${idTop} .ui-th-column `).css("font-size", "1rem");

                var title = parsePostData.title;
                var label = $("<label>")
                    .attr("for", "searchText")
                    .css({
                        "font-weight": "normal",
                        "padding-left": "10px",
                        "padding-top": "5px",
                    })
                    .text(title);

                $(`#gbox_${idTop}`).find(".ui-userdata-top").css({
                    height: "1px",
                });
            } else {
                var title = parsePostData.title;
                var label = $("<label>")
                    .attr("for", "searchText")
                    .css({
                        "font-weight": "normal",
                        "padding-left": "10px",
                        "padding-top": "1px",
                    })
                    .text(title);

                $(`#gbox_${idTop}`).find(".ui-jqgrid").css({
                    "min-height": "24px!important",
                });

                $(`#gbox_${idTop}`).find(".ui-userdata-top").css({
                    height: "1px",
                    "min-height": "25px",
                });
            }

            // Mengecek apakah label belum ada sebelumnya
            if ($(`#t_${idTop} label[for='searchText']`).length === 0) {
                $(`#t_${idTop}`).append(label);
            }

            var labelColumn = parsePostData.labelColumn;

            if (!labelColumn) {
                $(`#gbox_${idTop}`).find(".ui-jqgrid-hdiv").hide();
            }

            $(".ui-scroll-popup").addClass("d-none");
            $(".modal-loader-content").addClass("d-none");
        },
        onSelectRow: function (id) {
            // console.log(id);
            
            activeGrid = this;

            let limit = $(this).jqGrid("getGridParam", "postData").limit;
            let page = $(this).jqGrid("getGridParam", "page");
            let selectedIndex = $(this).jqGrid("getCell", id, "rn") - 1;

            if (selectedIndex >= limit)
                selectedIndex = selectedIndex - limit * (page - 1);

            $(this).jqGrid("setGridParam", {
                selectedIndex,
            });
        },
        loadComplete: function (data) {
            // console.log(data);
            
            $(".loadingMessage").hide();
            idTop = selector.attr("id");

            var colModel = selector.jqGrid("getGridParam", "colModel");
            var firstColumnName = colModel[1].name;

            if (detectDeviceType() == "mobile") {
                $(".lookup-grid tr:not(.jqgfirstrow) td").css(
                    "padding",
                    "12px"
                );
                $(".lookup-grid tr:not(.jqgfirstrow) td").css(
                    "font-size",
                    "1rem"
                );
                $(`#gview_${idTop} .ui-th-column `).css("font-size", "1rem");
            }

            let modal = $("#crudModal");
            let form = modal.find("form");

            changeJqGridRowListText();

            if (data.length === 0) {
                $("#parameterGrid").each((index, element) => {
                    abortGridLastRequest($(element));
                    clearGridHeader($(element));
                });
            } else {
                $(form).find(".is-invalid").removeClass("is-invalid");
                $(form).find(".invalid-feedback").remove();
            }

            if (detectDeviceType() == "desktop") {
                console.log("desktop");

                // $(document).unbind('keydown')

                initResize($(this));

                let selectedIndex =
                    $(this).jqGrid("getGridParam").selectedIndex;

                if (selectedIndex > $(this).getDataIDs().length - 1) {
                    selectedIndex = $(this).getDataIDs().length - 1;
                }

                if ($(this).jqGrid("getGridParam").triggerClick) {
                    $(this)
                        .find(`tr[id="${$(this).getDataIDs()[selectedIndex]}"]`)
                        .click();

                    $(this).jqGrid("setGridParam", {
                        triggerClick: false,
                    });
                }
            }

            $(".clearsearchclass").click(function () {
                clearColumnSearch($(this));
            });

            $(this).setGridWidth($("#lookupCabang").prev().width());
            setHighlight($(this));
            // $(this).jqGrid('setSelection', 1);
        },
    })
} else {
    selector.jqGrid({
        url: urlRequestGrid,
        mtype: "GET",
        styleUI: 'Bootstrap4',
        iconSet: 'fontAwesome',
        datatype: "json",
        postData: filterPostData,
        idPrefix: '',
        colModel: column,
        height: 350,
        fixed: true,
        rownumbers: false,
        rownumWidth: 0,
        rowNum: parsePostData.limit || 20,
        rowList: [10, 20, 50, 0],
        sortable: true,
        sortname: 'kodetrado',
        sortorder: 'asc',
        page: 1,
        toolbar: [true, "top"],
        viewrecords: true,
        prmNames: {
            sort: 'sortIndex',
            order: 'sortOrder',
            rows: 'limit'
        },
        jsonReader: {
            root: 'data',
            total: 'attributes.totalPages',
            records: 20,
        },
        autowidth: true,
        scrollOffset: 1,
        scrollrows: false,
        shrinkToFit: false,
        scrollLeftOffset: "25%",
        scroll: true,
        height: 350,
        page: 1,
        selectedIndex: 0,
        triggerClick: false,
        search: true,
        serializeGridData: function(postData) {
            searching = parsePostData.searching || ''
            searchText = `.`+parsePostData.searchText

            // console.log('searching',searching);
            

            var colModel = $(this).jqGrid("getGridParam", "colModel"),
                rules = [],
                searchValue = $(searchText).val(),
                i,
                cm;
            l = colModel.length

            aksi = parsePostData.aksi || ''


            postData.sort_indexes = [postData.sort_index];
            postData.sort_orders = [postData.sort_order];


            input = $(searchText).data('input')

            if (isToolbarSearch) {
                colModel.forEach(function(cm) {
                    // var searchField = $("#gs_" + cm.name).val();
                    let searchField
                    
                    if ($('#crudModal').length > 0) {
                        searchField = $('#crudModal').find("#gs_" + cm.name).val()
                    } else {
                        searchField = $("#gs_" + cm.name).val();
                    } 

                    if (searchField && cm.search !== false && (cm.stype === undefined || cm
                            .stype === "text")) {
                        isToolbarSearch = true;
                        rules.push({
                            field: cm.name,
                            op: "cn", // Contains operation
                            data: searchField.toUpperCase()
                        });
                    }
                });

                // Logic for toolbar search with AND
                postData.filters = JSON.stringify({
                    "groupOp": "AND",
                    "rules": rules
                });
                postData.filter_group = "AND";

            } else {
                if (input) {
                    if (searching.length == 0) {
                        for (i = 0; i < l; i++) {
                            cm = colModel[i];

                            if (cm.search !== false && (cm.stype === undefined || cm.stype === "text")) {
                                rules.push({
                                    field: cm.name,
                                    op: "cn",
                                    data: searchValue.toUpperCase(),
                                });
                            }
                        }

                        postData.filters = JSON.stringify({
                            groupOp: "OR",
                            rules: rules,
                        });

                        postData.searching = searching;
                        postData.searchText = searchText;
                    } else if (searching.length >= 1) {                    
                        for (i = 0; i < l; i++) {
                            cm = colModel[i];
                            // Check if the column name is in the 'searching' array
                            if (searching.includes(cm.name)) {
                                // Check for valid search options
                                if (
                                    cm.search !== false &&
                                    (cm.stype === undefined || cm.stype === "text")
                                ) {
                                    rules.push({
                                        field: cm.name,
                                        op: "cn", // Contains operation
                                        data: searchValue.toUpperCase(),
                                    });
                                }
                            }
                        }
                        // postData.filter_group = "OR";

                        // postData.filters = JSON.stringify({
                        //     groupOp: "OR",
                        //     rules: rules,
                        // });

                        postData.searching = searching;
                        postData.searchText = searchText;
                    }
                }
            }

            return postData;
        },
        loadBeforeSend: function(jqXHR) {
            $('.loadingMessage').show();
            idTop = selector.attr('id')


            $(`#load_${idTop}`).remove()

            if (detectDeviceType() == 'mobile') {

                $('.lookup-grid tr:not(.jqgfirstrow) td').css('padding', '12px')
                $('.lookup-grid tr:not(.jqgfirstrow) td').css('font-size', '1rem')

                $(`#gview_${idTop} .ui-th-column `).css('font-size', '1rem')

                var title = parsePostData.title
                var label = $("<label>").attr("for", "searchText")
                    .css({
                        "font-weight": "normal",
                        "padding-left": "10px",
                        "padding-top": "5px"
                    })
                    .text(title);

                $(`#gbox_${idTop}`).find('.ui-userdata-top').css({
                    "height": "1px",

                })

            } else {
                var title = parsePostData.title
                var label = $("<label>").attr("for", "searchText")
                    .css({
                        "font-weight": "normal",
                        "padding-left": "10px",
                        "padding-top": "1px"
                    })
                    .text(title);

                $(`#gbox_${idTop}`).find('.ui-jqgrid').css({
                    "min-height": "24px!important"
                })

                $(`#gbox_${idTop}`).find('.ui-userdata-top').css({
                    "height": "1px",
                    "min-height": "25px"
                })

            }

            // Mengecek apakah label belum ada sebelumnya
            if ($(`#t_${idTop} label[for='searchText']`).length === 0) {
                $(`#t_${idTop}`).append(label);
            }


            var labelColumn = parsePostData.labelColumn

            

            if (!labelColumn) {
            
                $(`#gbox_${idTop}`).find('.ui-jqgrid-hdiv').hide()
            }


            $('.ui-scroll-popup').addClass('d-none')
            $('.modal-loader-content').addClass('d-none')



            jqXHR.setRequestHeader('Authorization', `Bearer ${accessToken}`)
            setGridLastRequest($(this), jqXHR)

        },
        onSelectRow: function(id) {
            activeGrid = this;

            let limit = $(this).jqGrid("getGridParam", "postData").limit;
            let page = $(this).jqGrid("getGridParam", "page");
            let selectedIndex = $(this).jqGrid("getCell", id, "rn") - 1;

            if (selectedIndex >= limit)
                selectedIndex = selectedIndex - limit * (page - 1);

            $(this).jqGrid("setGridParam", {
                selectedIndex,
            });
        },
        loadComplete: function(data) {

            $('.loadingMessage').hide();
            idTop = selector.attr('id')

            var colModel = selector.jqGrid('getGridParam', 'colModel');
            var firstColumnName = colModel[1].name;


            if (detectDeviceType() == 'mobile') {
                $('.lookup-grid tr:not(.jqgfirstrow) td').css('padding', '12px')
                $('.lookup-grid tr:not(.jqgfirstrow) td').css('font-size', '1rem')
                $(`#gview_${idTop} .ui-th-column `).css('font-size', '1rem')

            }

            let modal = $('#crudModal')
            let form = modal.find('form')

            changeJqGridRowListText();

            if (data.data.length === 0) {

                $('#parameterGrid').each((index, element) => {
                    abortGridLastRequest($(element))
                    clearGridHeader($(element))
                })
            } else {
                $(form).find('.is-invalid').removeClass('is-invalid');
                $(form).find('.invalid-feedback').remove();
            }

            if (detectDeviceType() == 'desktop') {
                console.log('desktop');

                // $(document).unbind('keydown')
            
                initResize($(this))


                let selectedIndex = $(this).jqGrid("getGridParam").selectedIndex;
            
                if (selectedIndex > $(this).getDataIDs().length - 1) {
                    selectedIndex = $(this).getDataIDs().length - 1;
                }

                if ($(this).jqGrid("getGridParam").triggerClick) {

                    $(this)
                        .find(`tr[id="${$(this).getDataIDs()[selectedIndex]}"]`)
                        .click();

                    $(this).jqGrid("setGridParam", {
                        triggerClick: false,
                    });
                    
                } 
            }

            $('.clearsearchclass').click(function() {
                clearColumnSearch($(this))
            })

            $(this).setGridWidth($('#lookupCabang').prev().width())
            setHighlight($(this))
            // $(this).jqGrid('setSelection', 1);

        

        },

    })
}

if (filterToolbar) {
    if (detectDeviceType() == 'mobile') {
        $('.loadingMessage').css('top', '125%')
        $('.loading-text').css('margin-top', '13px')
    }
    selector.jqGrid('filterToolbar', {
        stringResult: true,
        searchOnEnter: false,
        defaultSearch: 'cn',
        groupOp: 'AND',
        beforeSearch: function() {
            isToolbarSearch = true;

            var postData = $(this).jqGrid("getGridParam", "postData");
            postData.filters = "";
            $(this).jqGrid("setGridParam", {
                search: false
            });

            $(searchText).val('');
        },
        afterSearch: function() {
            isToolbarSearch = false;

        }
    });
}

if (parsePostData.filterToolbar) {
    searchText = `.` + parsePostData.searchText;

    if (typeData == 'LOCAL') {
        var colModel = selector.jqGrid("getGridParam", "colModel");
        let filterToolbarresult = filterToolbarData(filtersData, colModel);

        finalData = typeof result !== "undefined" ? result : filtersData;

        colModel.forEach(function (cm) {
            const searchField = $(`#gs_${cm.name}`).val();
        
            if ($('#crudModal').length > 0) {
                $('#crudModal').find(`[id*="gs_${cm.name}"]`).on("input", function () {
                    filterToolbarresult = filterToolbarData(finalData, colModel);
                    
                    selector
                        .clearGridData()
                        .setGridParam({ data: filterToolbarresult })
                        .trigger("reloadGrid");
                });

                $("#crudModal").find(".clearsearchclass").click(function () {
                    $('#crudModal').find(`[id*="gs_${cm.name}"]`).val('')
                    selector
                        .clearGridData()
                        .setGridParam({ data: finalData })
                        .trigger("reloadGrid");
                });
            } else {
                $(`#gs_${cm.name}`).on("input", function () {
                    filterToolbarresult = filterToolbarData(finalData, colModel);

                    selector
                        .clearGridData()
                        .setGridParam({ data: filterToolbarresult })
                        .trigger("reloadGrid");
                });

                $(".clearsearchclass").click(function() {
                    $(`#gs_${cm.name}`).val('')
                    selector
                        .clearGridData()
                        .setGridParam({ data: finalData })
                        .trigger("reloadGrid");
                })
            }
        });
    }
} 
