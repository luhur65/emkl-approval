const serializeMaster = function (obj) {
    var str = [];
    for (var p in obj)
        if (obj.hasOwnProperty(p)) {
            str.push(encodeURIComponent(p) + "=" + encodeURIComponent(obj[p]));
        }
    return str.join("&");
};

const getLookupMaster = function (fileName, postData, singlecolumn) {
    return new Promise((resolve, reject) => {
        $.ajax({
            url: `${appUrl}/lookup/${fileName}?${serializeMaster(postData)}`,
            method: "GET",
            dataType: "html",
            success: function (response) {
                resolve(response);
            },
        });
    });
};

let activeLookupElement = null;
let aktifId = null;
let selectedId;
// let bottomSelected;
// let topSelected;
let indexRowSelect;
let keydownIndex = true;

let isKeyDown = false;
let isLookupOpen = true;
let offsetWindow;
$.fn.lookupMaster = function (options) {
    let defaults = {
        title: null,
        fileName: null,
        singlecolumn: null,
        detail: null,
        typeSearch: null,
        rowIndex: null,
        totalRow: null,
        alignRightMobile: null,
        alignRight: null,
        multiColumnSize: null,
        extendSize: null,
        searching: null,
        disabledIsUsed: null,
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

        element.data("hasLookup", true);

        element.wrap('<div class="input-group"></div>')
        .after(`
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
        `)

        // let inputGroupAppend = $(
        //     '<div class="input-group-append"></div>'
        // ).insertAfter(element);

        // if (settings.onClear) {
        //     // $(
        //     //     '<button type="button" class="btn position-absolute button-clear text-secondary" style="right: 34px; z-index: 99;"><i class="fa fa-times"></i></button>'
        //     // )
        //     $(
        //         '<button type="button" class="btn position-absolute button-clear text-secondary" style="right: 34px; z-index: 99;"><i class="fa fa-times-circle" style="font-size: 15px; margin-top:2px; color:red"></i></button>'
        //     )
        //         .appendTo(inputGroupAppend)
        //         .click(function () {
        //             handleOnClear(element);
        //         });
        // }

        element.siblings(".button-clear").click(function () {
			handleOnClear(element);
		});
        // $(
        //     `<button class="btn btn-easyui lookup-toggler" type="button"><i class="far fa-window-maximize text-easyui-dark" style="font-size: 12.25px;"></i></button>`
        // )
        //     .appendTo(inputGroupAppend)

        element
            .siblings(".input-group-append")
			.find(".lookup-toggler")
            .click(async function () {
                event.preventDefault();
                
                element.data("input", false);

                let lookupContainer = element.siblings(
                    `#lookup-${element.attr("id")}`
                );

                if (activeLookupElement != null) {
                    console.log(lookupContainer);
                    if (aktifId != `#lookup-${element.attr("id")}`) {
                        bottomSelected = 10;
                        topSelected = 0;

                        $(aktifId).hide();

                        activate = false;
                    }
                }
                if (activeLookupElement) {
                    activeLookupElement.hide();

                    lookupContainer.remove();
                    element.data("hasLookup", false);

                    let detailElement = $(".overflow");

                    // detailElement.css("overflow", "auto");
                }

                activeLookupElement = lookupContainer;

                aktifId = `#lookup-${element.attr("id")}`;

                if (activate) {
                    $(aktifId).hide();

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

                isLookupOpen = true;
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

           

            if (activeLookupElement != null) {
                if (aktifId != `#lookup-${element.attr("id")}`) {
                    $(aktifId).hide();

                    activate = false;
                }
            }

            activeLookupElement = lookupContainer;

            aktifId = `#lookup-${element.attr("id")}`;

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

            isLookupOpen = true;
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
        // let indexRowSelect = 1;
        offsetWindow = window.pageYOffset
        settings.beforeProcess();
        settings.onShowLookup();

        

        const detail = settings.detail;
        const miniSize = settings.miniSize;
        const alignRightMobile = settings.alignRightMobile;
        const alignRight = settings.alignRight;

        idElement = $(element).attr("id");

        const box = $(`#${idElement}`)[0];

        const boxRect = box.getBoundingClientRect();

        const width = element[0].offsetWidth;

        let getId = element.attr("id");

        let detailElement = $(".overflow");

        let lookupContainer = element.siblings(`#lookup-${getId}`);

        let singleColumn = settings.singlecolumn;

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

                    console.log('masuk yang ini',widthValue);
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
                      
                        const commonStyles = 'position: absolute; box-shadow: 10px 10px 5px 12px lightblue; border:1px; background-color: #fff; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); z-index: 9999; top: 100%; max-height: 300px; overscroll-behavior: contain!important;';
                        
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

       

        getLookupMaster(settings.fileName, settings.postData ?? null).then(
            (response) => {
                lookupBody.html(response);
                let grid = lookupBody.find(".lookup-grid");


                let lookupLabel = settings.fileName;

                $(".ui-jqgrid-bdiv").addClass("bdiv-lookup");
                $(".jqgrid-rownum").addClass("rowNum-lookup");

                if (grid.length > 0) {
                    bindKey = false;

                    let el = $(this);
                    // keydownIndex++

                    $(element).on("keydown", function (e) {
                        // keydownIndex = true

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
                                    index < keydownIndex;
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
                                    if (38 === e.keyCode && isLookupOpen) {
                                       
                                        $(grid).setSelection(
                                            gridIds[currentIndex - 1]
                                        );
                                        element.focus();

                                        var selectedRowId =
                                            $(grid).getGridParam("selrow");

                                        indexRowSelect = $(grid).jqGrid(
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

                                        if (indexRowSelect == topSelected) {
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

                                    if (40 === e.keyCode && isLookupOpen) {
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

                                        indexRowSelect = $(grid).jqGrid(
                                            "getInd",
                                            selectedRowId
                                        );

                                        // if (keydownIndex) {
                                        //     indexRowSelect = 1
                                        // }

                                        var visibleSelRow = 0;

                                        element.focus();

                                        var currentScrollTop = $(grid)
                                            .closest(".bdiv-lookup")
                                            .scrollTop();

                                        if (indexRowSelect == bottomSelected) {
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

                                        isLookupOpen = true;

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
                            handleSelectedRow(id, lookupContainer, element);
                            // element.focus();
                            activate = false;
                            bindKey = false;
                        },
                        onSelectRow: function (id) {
                            selectedId = id;
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
                handleSelectedRow(selectedId, lookupContainer, element);
                activate = false;
                bindKey = false;

                return false;
            }
        });

        lookupContainer.show();

        $(document).on("click.lookup", function (event) {
            if (!$(event.target).closest(".input-group").length) {
                lookupContainer.hide();

                lookupContainer.remove();
                element.data("hasLookup", false);

                // detailElement.css("overflow", "auto");

                activate = false;
            }
        });

        const modal = $(".modal-body");
        const modalheader = $(".modal-master");
       
        $(element).on("keydown", function (event) {
            if (event.keyCode === 27) {
                lookupContainer.hide();

                let detailElement = $(".overflow");

                // detailElement.css("overflow", "auto");

                lookupContainer.remove();
                element.data("hasLookup", false);

                activate = false;
                return false;
            }
        });

        // Tambahkan kode berikut
        lookupContainer.on("hide", function () {
            if (lookupContainer === activeLookupElement) {
                activeLookupElement = null;
            }
        });
        windowOffset = window.pageYOffset - 250;
    }

  

    function handleSelectedRow(id, lookupContainer, element) {
        if (id !== null) {
            bottomSelected = 10;
            topSelected = 1;

            let rowData = sanitize(
                lookupContainer.find(".lookup-grid").getRowData(id)
            );

            const obj = rowData;
            const array = Object.values(obj);
           

            element.val(rowData.name);

            if (array.length == 0) {
                element.val(element.data('currentValue'))
                lookupContainer.hide();
                return rowData
            }

            settings.onSelectRow(rowData, element);

            lookupContainer.hide();

            lookupContainer.remove();
            element.data("hasLookup", false);

            let detailElement = $(".overflow");
            isLookupOpen = false;
            // keydownIndex = false;

            // indexRowSelect = 1

          
            
        }
    }

    function handleOnCancel(element) {
        settings.onCancel(element);
    }

    function handleOnClear(element) {
        settings.onClear(element);

        let lookupContainer = element.siblings(`#lookup-${element.attr("id")}`);       
        let grid = lookupContainer.find(".lookup-grid");

        // grid.jqGrid("setGridParam", {
        //     postData: {
        //         filters: [],
        //     },
        // });

        grid.trigger("reloadGrid", [{ page: 1, current: true }]);
    }

    async function handleOnInput(element, searchValue = null, data) {
        let lookupContainer = element.siblings(`#lookup-${element.attr("id")}`);
        let grid = lookupContainer.find(".lookup-grid");
        abortGridLastRequest($(grid))
        if (searchValue) {
            /* Determine user selection listener */
            if (detectDeviceType() == "desktop") {
                timeout = 200;
            } else if (detectDeviceType() == "mobile") {
                timeout = 50;
            }

           console.log('lasterqeuest ', grid.getGridParam()?.lastRequest);
            input = element.data("input");

            if (settings.typeSearch === "ALL") {
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
                        rules.push({
                            field: cm.name,
                            op: "cn",
                            data: searchValue.toUpperCase(),
                        });
                    }

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
