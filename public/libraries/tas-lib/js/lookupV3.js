let elementReference = null;

const serializeLookupV3 = function (obj) {
    var str = [];
    for (var p in obj)
        if (obj.hasOwnProperty(p)) {
            str.push(encodeURIComponent(p) + "=" + encodeURIComponent(obj[p]));
        }
    return str.join("&");
};

const getLookupV3 = function (
    fileName,
    postData,
    element,
    title,
    searching,
    singleColumn,
    labelColumn,
    filter
) {
    let classString = element.attr("class");
    let classArray = classString.split(" ");
    let lookupClasses = classArray.filter((className) =>
        className.includes("-lookup")
    );
  
    
    postData.searchText = lookupClasses[0];
    postData.title = title;
    postData.searching = searching;
    postData.singleColumn = singleColumn;
    postData.labelColumn = labelColumn;
    postData.filterToolbar = filter;

    // console.log(postData.searching);

    return new Promise((resolve, reject) => {
        $.ajax({
            url: `${appUrl}/lookup/${fileName}?${serializeLookupV3(postData)}`,
            method: "GET",
            dataType: "html",
            success: function (response) {
                resolve(response);
            },
        });
    });
};

let activeLookupElementV3 = null;
let aktifIdV3 = null;
let selectedIdV3;
// let bottomSelected;
// let topSelected;
let indexRowSelectV3;
let keydownIndexV3 = true;

let isKeyDownV3 = false;
let isLookupOpenV3 = true;

let offsetWindowV3;

$.fn.lookupV3 = function (options) {
    let defaults = {
        title: null,
        fileName: null,
        singlecolumn: false,
        labelColumn: true,
        detail: null,
        typeSearch: null,
        rowIndex: null,
        totalRow: null,
        alignRightMobile: null,
        alignRight: null,
        searching: [],
        multiColumnSize: null,
        searchingSpesific: null,
        extendSize: null,
        disabledIsUsed: null,
        selectedRequired: null,
        filterToolbar: false,
        postData: {},
        beforeProcess: function () {},
        onShowLookup: function (rowData, element) {},
        onSelectRow: function (rowData, element) {},
        onCancel: function (element) {},
        onClear: function (element) {},
    };

    let settings = $.extend({}, defaults, options);
    let sidebarIsOpen = false;

    this.each(function () {
        let element = $(this);
        let lookupContainer;
        elementReference = element;

        element.data("hasLookup", true);

        element.wrap('<div class="input-group"></div>').after(`
        ${
            settings.onClear
                ? `<button type="button" class="btn position-absolute button-clear text-secondary" style="right: 34px; z-index: 99;"><i class="fa fa-times-circle" style="font-size: 15px; margin-top:2px; color:red"></i></button>`
                : ``
        }
        <div class="input-group-append">
				<button class="btn btn-easyui lookup-toggler" type="button">
					<i class="far fa-window-maximize text-easyui-dark" style="font-size: 12.25px"></i>
				</button>
			</div>
        `);

        element.siblings(".button-clear").click(function () {
            handleOnClear(element);
        });

        element
            .siblings(".input-group-append")
            .find(".lookup-toggler")
            .click(async function () {
                event.preventDefault();
                element.data("input", false);

                let lookupContainer = element.siblings(
                    `#lookup-${element.attr("id")}`
                );

                if (activeLookupElementV3 != null) {
                    console.log(lookupContainer);
                    if (aktifIdV3 != `#lookup-${element.attr("id")}`) {
                        bottomSelected = 10;
                        topSelected = 0;

                        $(aktifIdV3).hide();

                        activate = false;
                    }
                }
                if (activeLookupElementV3) {
                    activeLookupElementV3.hide();

                    lookupContainer.remove();
                    element.data("hasLookup", false);

                    handleOnCancel(element);
                    // detailElement.css("overflow", "auto");
                }

                activeLookupElementV3 = lookupContainer;

                aktifIdV3 = `#lookup-${element.attr("id")}`;

                if (activate) {
                    $(aktifIdV3).hide();

                    activate = false;

                    lookupContainer.remove();
                    element.data("hasLookup", false);

                    let detailElement = $(".overflow");

                    // $(".modal-overflow").css("overflow-y", "auto");
                } else {
                    activateLookup(element, element.val());
                    element.focus();
                    activate = true;
                    bindKey = false;

                    // $(".modal-overflow").css("overflow-y", "hidden");
                }

                isLookupOpenV3 = true;
            });

        activate = false;
        // element.on("focus", function (event) {

        // });

        element.on("input", function (event) {
            let lookupContainer = element.siblings(
                `#lookup-${element.attr("id")}`
            );

            element.data("input", true);

            const searchValue = element.val();

            if (activeLookupElementV3 != null) {
                if (aktifIdV3 != `#lookup-${element.attr("id")}`) {
                    $(aktifIdV3).hide();

                    activate = false;
                }
            }

            activeLookupElementV3 = lookupContainer;

            aktifIdV3 = `#lookup-${element.attr("id")}`;

            if (!activate) {
                delay(function () {
                    activateLookup(element, searchValue);
                    activate = true;
                }, 50);
            } else {
                console.log("else");
                delay(function () {
                    handleOnInput(element, searchValue);
                }, 100);
                bindKey = false;
            }

            isLookupOpenV3 = true;
        });

        element.focus(function () {
            const lookupContainer = element.siblings(
                `#lookup-${element.attr("id")}`
            );
            if (lookupContainer.is(":visible")) {
                lookupContainer.show();
            }
        });
    });



    async function activateLookup(element, searchValue = null, singlecolumn) {
        let bottomSelected = 11;
        let topSelected = 0;
        // let indexRowSelectV3 = 1;

        offsetWindowV3 = window.pageYOffset;

        settings.beforeProcess();
        settings.onShowLookup();

        $('.input-group').removeClass('active'); 
        element.addClass('active');

        
        const detail = settings.detail;
        const miniSize = settings.miniSize;
        const alignRightMobile = settings.alignRightMobile;
        const alignRight = settings.alignRight;

        idElement = $(element).attr("id");

        const box = $(`#${idElement}`)[0];

        const boxRect = box.getBoundingClientRect();

        const width = element[0].offsetWidth;

        let getId = element.attr("id");

        let lookupContainer = element.siblings(`#lookup-${getId}`);

        if (lookupContainer.length === 0) {
            if (miniSize) {
                let detailElement = $(".overflow");
                let modalBody = $(".modal-overflow");

                let prevOverflow = detailElement.css("overflow");

                // detailElement.css("overflow", "visible");

                if (detectDeviceType() == "desktop") {
                    lookupContainer = $(
                        '<div id="lookup-' +
                            getId +
                            '" style="position: absolute; box-shadow: 10px 10px 5px 12px lightblue; border:1px; background-color: #fff;  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); z-index: 9999; top: 100%;  width: 400px; max-height: 150px;  overscroll-behavior: contain!important;"></div>'
                    ).insertAfter(element);

                    if (alignRight) {
                        $(`#lookup-${getId}`).css("right", "0");
                    }
                } else if (detectDeviceType() == "mobile") {
                    let ukuranDevice = window.innerWidth;
                    let widthValue = ukuranDevice < 400 ? 250 : 250;

                    lookupContainer = $(
                        `<div id="lookup-${getId}" style="position: absolute; background-color: #fff; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); z-index: 9999; top: 100%; width: ${widthValue}px; max-height: 280px;  overscroll-behavior: contain!important;"></div>`
                    ).insertAfter(element);

                    if (alignRightMobile) {
                        $(`#lookup-${getId}`).css("right", "0");
                    }
                }
            } else {
                if (detail) {
                    let detailElement = $(".overflow");

                    let modalBody = $(".modal-overflow");

                    let prevOverflow = detailElement.css("overflow");

                    detailElement.css("overflow", "visible");

                    if (detectDeviceType() == "desktop") {
                        lookupContainer = $(
                            '<div id="lookup-' +
                                getId +
                                '" style="position: absolute; box-shadow: 10px 10px 5px 12px lightblue; border:1px; background-color: #fff;  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); z-index: 9999; top: 100%;  width: 1000px; max-height: 300px;  overscroll-behavior: contain!important;"></div>'
                        ).insertAfter(element);

                        if (alignRight) {
                            $(`#lookup-${getId}`).css("right", "0");
                        }
                    } else if (detectDeviceType() == "mobile") {
                        lookupContainer = $(
                            '<div id="lookup-' +
                                getId +
                                '" style="position: absolute; background-color: #fff; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); z-index: 9999; top: 100%;  width: 330px; max-height: 280px;   overscroll-behavior: contain!important; "></div>'
                        ).insertAfter(element);

                        if (alignRightMobile) {
                            $(`#lookup-${getId}`).css("right", "0");
                        }
                    }
                } else {
                   
                    if (detectDeviceType() == "desktop") {
                        let multiColumnSize = settings.multiColumnSize;
                        let extend = settings.extendSize;
                        let sizeExtend = width + extend;
                        
                        console.log(sizeExtend, width);
                        
                        const commonStyles =
                            "position: absolute; box-shadow: 10px 10px 5px 12px lightblue; border:1px; background-color: #fff; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); z-index: 9999; top: 100%; max-height: 300px; overscroll-behavior: contain!important;";

                        if (multiColumnSize) {
                            lookupContainer = $(
                                `<div id="lookup-${getId}" style="${commonStyles} width: ${sizeExtend}px;"></div>`
                            ).insertAfter(element);
                        } else {
                            lookupContainer = $(
                                `<div id="lookup-${getId}" style="${commonStyles} width: ${width}px;"></div>`
                            ).insertAfter(element);
                        }

                        if (alignRight) {
                            $(`#lookup-${getId}`).css("right", "0");
                        }
                    } else if (detectDeviceType() == "mobile") {
                        console.log("masuk baru");
                        lookupContainer = $(
                            '<div id="lookup-' +
                                getId +
                                '" style="position: absolute; background-color: #fff; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); z-index: 9999; top: 100%; width: 350px; max-height: 280px;  overscroll-behavior: contain!important;"></div>'
                        ).insertAfter(element);

                        if (alignRightMobile) {
                            $(`#lookup-${getId}`).css("right", "0");
                        }
                    }
                }
            }
        }

        lookupContainer.empty();

        let lookupBody = $('<div class="lookup-body"></div>').appendTo(
            lookupContainer
        );

        const {
            fileName: flnm,
            postData: pst,
            title: title = "Default Title", // Default value for 'title'
            searching: src = [], // Default value for 'searching' as an empty array
            singleColumn: singleclm = false, // Default value for 'singleColumn' as false
            labelColumn: hidelbl = false, // Default value for 'labelColumn' as false
            filterToolbar: filter = false,
        } = settings;

        console.log(src);

        getLookupV3(flnm, pst, element, title, src, singleclm, hidelbl, filter).then(
            (response) => {
                lookupBody.html(response);
                let grid = lookupBody.find(".lookup-grid");

                let lookupLabel = flnm;

                $(".ui-jqgrid-bdiv").addClass("bdiv-lookup");
                $(".jqgrid-rownum").addClass("rowNum-lookup");

                if (grid.length > 0) {
                    bindKey = false;

                    let el = $(this);
                    // keydownIndexV3++

                    $(element).on("keydown", function (e) {
                        // keydownIndexV3 = true

                        if (!bindKey) {
                            if (
                                e.keyCode == 33 ||
                                e.keyCode == 34 ||
                                e.keyCode == 35 ||
                                e.keyCode == 36 ||
                                e.keyCode == 38 ||
                                e.keyCode == 40 ||
                                e.keyCode == 13
                            ) {
                                e.preventDefault();

                                for (
                                    let index = 0;
                                    index < keydownIndexV3;
                                    index++
                                ) {
                                    if (index == 0) {
                                    }
                                }

                                var gridIds = $(grid).getDataIDs();
                                var selectedRow =
                                    $(grid).getGridParam("selrow");

                                var currentPage = $(grid).getGridParam("page");
                                var lastPage = $(grid).getGridParam("lastpage");
                                var currentIndex = -1;

                                var triggerClick = false;

                                for (var i = 0; i < gridIds.length; i++) {
                                    if (gridIds[i] == selectedRow)
                                        currentIndex = i;
                                }

                                if (triggerClick == false) {
                                    if (
                                        35 === e.keyCode &&
                                        !e.shiftKey &&
                                        !e.ctrlKey
                                    ) {
                                        var inputElement =
                                            document.activeElement;
                                        if (
                                            inputElement &&
                                            inputElement.tagName === "INPUT"
                                        ) {
                                            inputElement.setSelectionRange(
                                                inputElement.value.length,
                                                inputElement.value.length
                                            );
                                        }
                                        return false;
                                    }
                                    if (
                                        36 === e.keyCode &&
                                        !e.shiftKey &&
                                        !e.ctrlKey
                                    ) {
                                        var inputElement =
                                            document.activeElement;
                                        if (
                                            inputElement &&
                                            inputElement.tagName === "INPUT"
                                        ) {
                                            inputElement.setSelectionRange(
                                                0,
                                                0
                                            );
                                        }
                                        return false;
                                    }
                                    if (38 === e.keyCode && isLookupOpenV3) {
                                        $(grid).setSelection(
                                            gridIds[currentIndex - 1]
                                        );
                                        element.focus();

                                        var selectedRowId =
                                            $(grid).getGridParam("selrow");

                                        indexRowSelectV3 = $(grid).jqGrid(
                                            "getInd",
                                            selectedRowId
                                        );

                                        var currentRowHeight =
                                            $(grid).getGridParam("rowHeight") ||
                                            26;
                                        var visibleRows =
                                            $(grid).getGridParam(
                                                "recordsView"
                                            ) || 1;

                                        var currentScrollTop = $(grid)
                                            .closest(".ui-jqgrid-bdiv")
                                            .scrollTop();

                                        if (indexRowSelectV3 == topSelected) {
                                            bottomSelected--;
                                            topSelected--;
                                            $(grid)
                                                .closest(".bdiv-lookup")
                                                .scrollTop(
                                                    currentScrollTop -
                                                        visibleRows *
                                                            currentRowHeight
                                                );
                                        }

                                        return false;
                                    }

                                    if (40 === e.keyCode && isLookupOpenV3) {
                                        console.log("bind key lookup");
                                        $(grid).setSelection(
                                            gridIds[currentIndex + 1]
                                        );

                                        var currentRowHeight =
                                            $(grid).getGridParam("rowHeight") ||
                                            26;
                                        var visibleRows =
                                            $(grid).getGridParam(
                                                "recordsView"
                                            ) || 1;

                                        var selectedRowId =
                                            $(grid).getGridParam("selrow");
                                        // var selectedRowId = $(grid).jqGrid("getGridParam").selectedIndex++;

                                        indexRowSelectV3 = $(grid).jqGrid(
                                            "getInd",
                                            selectedRowId
                                        );

                                        // if (keydownIndexV3) {
                                        //     indexRowSelectV3 = 1
                                        // }

                                        var visibleSelRow = 0;

                                        element.focus();

                                        var currentScrollTop = $(grid)
                                            .closest(".bdiv-lookup")
                                            .scrollTop();

                                        if (
                                            indexRowSelectV3 == bottomSelected
                                        ) {
                                            visibleSelRow = 1;
                                            bottomSelected++;
                                            topSelected++;
                                        }

                                        if (visibleSelRow === 1) {
                                            $(grid)
                                                .closest(".bdiv-lookup")
                                                .scrollTop(
                                                    currentScrollTop +
                                                        visibleRows *
                                                            currentRowHeight
                                                );
                                        }

                                        isLookupOpenV3 = true;

                                        return false;
                                    }

                                    if (13 === e.keyCode) {
                                        let rowId =
                                            $(grid).getGridParam("selrow");
                                        let ondblClickRowHandler = $(
                                            grid
                                        ).jqGrid(
                                            "getGridParam",
                                            "ondblClickRow"
                                        );

                                        if (ondblClickRowHandler) {
                                            ondblClickRowHandler.call(
                                                $(grid)[0],
                                                rowId
                                            );
                                        }

                                        return false;
                                    }
                                }

                                $(".ui-jqgrid-bdiv").find("tbody").animate({
                                    scrollTop: 200,
                                });
                                // $(".table-success").position().top > 300;
                            }
                            bindKey = true;
                        }
                    });
                }

                /* Determine user selection listener */
                if (detectDeviceType() == "desktop") {
                    grid.jqGrid("setGridParam", {
                        onCellSelect: function (id) {
                            console.log('INI YA',id, lookupContainer, element)
                            handleSelectedRow(id, lookupContainer, element);
                            // element.focus();
                            activate = false;
                            bindKey = false;

                        },
                        onSelectRow: function (id) {
                            selectedIdV3 = id;
                        },
                    });
                } else if (detectDeviceType() == "mobile") {
                    grid.jqGrid("setGridParam", {
                        onCellSelect: function (id) {
                            handleSelectedRow(id, lookupContainer, element);
                            element.focus();
                            activate = false;
                            bindKey = false;
                        },
                    });
                }

                window.scrollTo(0, windowOffset);
            }
        );

        $(element).on("keydown", function (event) {
            if (event.keyCode === 13) {
                handleSelectedRow(selectedIdV3, lookupContainer, element);
                activate = false;
                bindKey = false;

                return false;
            }
        });

        lookupContainer.show();

        if (!settings.selectedRequired) {
            $(document).on("click.lookup", function (event) {
           
                const isActive = $(element).hasClass('active');

                let lookupContainer = element.siblings(
                    `#lookup-${element.attr("id")}`
                );

                    
                if (isActive && !$(event.target).closest(lookupContainer).length && !$(event.target).closest(".input-group").length) {

                    lookupContainer.hide();
                    lookupContainer.remove();
                    element.data("hasLookup", false);

                    handleOnCancel(element); 

                    activate = false; 
                    $(element).removeClass('active');
                }
            
            });
        }

        const modal = $(".modal-body");
        const modalheader = $(".modal-master");

        $(element).on("keydown", function (event) {
            if (event.keyCode === 27) {
                lookupContainer.hide();

                let detailElement = $(".overflow");

                console.log(element);
                // element.val('')

                // detailElement.css("overflow", "auto");

                lookupContainer.remove();
                element.data("hasLookup", false);

                handleOnCancel(element);

                activate = false;
                return false;
            }
        });

        // Tambahkan kode berikut
        lookupContainer.on("hide", function () {
            if (lookupContainer === activeLookupElementV3) {
                activeLookupElementV3 = null;
            }
        });
        windowOffset = window.pageYOffset;
    }

    function handleSelectedRow(id, lookupContainer, element) {
       
        if (id !== null) {
            bottomSelected = 10;
            topSelected = 1;

            let rowData = sanitize(
                lookupContainer.find(".lookup-grid").getRowData(id)
            );
                
            console.log('rowdara', rowData);
            
            const obj = rowData;
            const array = Object.values(obj);

            // element.val(rowData.name);
            element.val(rowData.name);

            if (array.length == 0) {
                element.val(element.data("currentValue"));
                lookupContainer.hide();
                return rowData;
            }

            console.log(rowData);
            

            settings.onSelectRow(rowData, element);

            lookupContainer.hide();

            lookupContainer.remove();
            element.data("hasLookup", false);

            let detailElement = $(".overflow");
            isLookupOpenV3 = false;
            // keydownIndexV3 = false;

            // indexRowSelectV3 = 1
        }
    }

    function handleOnCancel(element) {
        
        settings.onCancel(element);
    }

    function handleOnClear(element) {
        let lookupContainer = element.siblings(`#lookup-${element.attr("id")}`);
        let grid = lookupContainer.find(".lookup-grid");

        let colMdl = grid.jqGrid("getGridParam", "colModel");

        settings.onClear(element);

        rules = [];
        colMdl.forEach(function (cm) {
            $("#gs_" + cm.name).val("");
        });

        grid.jqGrid("setGridParam", {
            postData: {
                filters: "",
            },
        });

        grid.trigger("reloadGrid", [{ page: 1, current: true }]);
    }

    async function handleOnInput(element, searchValue = null, data) {
        let lookupContainer = element.siblings(`#lookup-${element.attr("id")}`);
        let grid = lookupContainer.find(".lookup-grid");
        abortGridLastRequest($(grid));
        if (searchValue) {
            /* Determine user selection listener */
            if (detectDeviceType() == "desktop") {
                timeout = 200;
            } else if (detectDeviceType() == "mobile") {
                timeout = 50;
            }

            console.log("lasterqeuest ", grid.getGridParam()?.lastRequest);
            input = element.data("input");

            let colMdl = grid.jqGrid("getGridParam", "colModel");

        
            rules = [];
            colMdl.forEach(function (cm) {
                $("#gs_" + cm.name).val("");
            });
            if (settings.searching.length == 0) {
                console.log(settings.searching.length);
                delay(function () {
                    var postData = grid.jqGrid("getGridParam", "postData"),
                        colModel = grid.jqGrid("getGridParam", "colModel"),
                        rules = [],
                        searchText = searchValue,
                        l = colModel.length,
                        i,
                        cm;

                    // searching = settings.searching;

                    // cm = colModel[searching];

                    // if (
                    //     cm.search !== false &&
                    //     (cm.stype === undefined || cm.stype === "text")
                    // ) {
                    //     rules.push({
                    //         field: cm.name,
                    //         op: "cn",
                    //         data: searchValue.toUpperCase(),
                    //     });
                    // }

                    for (i = 0; i < l; i++) {
                        cm = colModel[i];

                        if (
                            cm.search !== false &&
                            (cm.stype === undefined || cm.stype === "text")
                        ) {
                            grid.jqGrid("setGridParam", {
                                field: cm.name,
                                op: "cn",
                                data: searchValue.toUpperCase(),
                            });
                        }
                    }
                    postData.filter_group = "OR";

                    postData.filters = JSON.stringify({
                        groupOp: "OR",
                        rules: rules,
                    });

                    grid.jqGrid("setGridParam", {
                        search: true,
                    });

                    grid.trigger("reloadGrid", [
                        {
                            page: 1,
                            current: true,
                        },
                    ]);

                    return false;
                }, timeout);
            } else if (settings.searching.length > 0) {
                delay(function () {
                    var postData = grid.jqGrid("getGridParam", "postData"),
                        colModel = grid.jqGrid("getGridParam", "colModel"),
                        rules = [],
                        searchText = searchValue,
                        l = colModel.length,
                        i,
                        j,
                        cm;

                    searching = settings.searching;

                    // Loop through the array of column indices
                    // for (j = 0; j < searching.length; j++) {
                    //     var searchIndex = searching[j];
                    //     cm = colModel[searchIndex];

                    //     console.log(cm,colModel);

                    //     if (
                    //         cm.search !== false &&
                    //         (cm.stype === undefined || cm.stype === "text")
                    //     ) {
                    //         rules.push({
                    //             field: cm.name,
                    //             op: "cn",
                    //             data: searchValue.toUpperCase(),
                    //         });
                    //     }
                    // }
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
                                    data: searchText.toUpperCase(),
                                });
                            }
                        }
                    }

                    postData.filter_group = "OR";

                    postData.filters = JSON.stringify({
                        groupOp: "OR",
                        rules: rules,
                    });

                    grid.jqGrid("setGridParam", {
                        search: true,
                    });

                    grid.trigger("reloadGrid", [
                        {
                            page: 1,
                            current: true,
                        },
                    ]);

                    return false;
                }, timeout);
            } else {
                delay(function () {
                    var postData = grid.jqGrid("getGridParam", "postData"),
                        colModel = grid.jqGrid("getGridParam", "colModel"),
                        rules = [],
                        searchText = searchValue,
                        l = colModel.length,
                        i,
                        cm;

                    searching = settings.searching;

                    cm = colModel[searching];

                    if (
                        cm.search !== false &&
                        (cm.stype === undefined || cm.stype === "text")
                    ) {
                        grid.jqGrid("setGridParam", {
                            field: cm.name,
                            op: "cn",
                            data: searchValue.toUpperCase(),
                        });
                    }

                    postData.filter_group = "OR";

                    grid.jqGrid("setGridParam", {
                        search: true,
                    });

                    grid.trigger("reloadGrid", [
                        {
                            page: 1,
                            current: true,
                        },
                    ]);

                    return false;
                }, timeout);
            }
        } else {
            var postData = grid.jqGrid("getGridParam", "postData"),
                colModel = grid.jqGrid("getGridParam", "colModel"),
                l = colModel.length,
                i,
                cm;

            for (i = 0; i < l; i++) {
                cm = colModel[i];
                if (
                    cm.search !== false &&
                    (cm.stype === undefined || cm.stype === "text")
                ) {
                    postData.filters = JSON.stringify({
                        groupOp: "AND",
                        rules: [
                            {
                                field: cm.name,
                                op: "cn",
                                data: "",
                            },
                        ],
                    });
                }
            }

            grid.trigger("reloadGrid", [
                {
                    page: 1,
                    current: true,
                },
            ]);
        }
    }

    function sanitize(rowData) {
        Object.keys(rowData).forEach((key) => {
            rowData[key] = rowData[key]
                .replaceAll('<span class="highlight">', "")
                .replaceAll("</span>", "");
        });

        return rowData;
    }

    return this;
};
