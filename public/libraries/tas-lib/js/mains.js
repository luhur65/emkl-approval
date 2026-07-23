let sidebarIsOpen = false;
let formats = { "THOUSANDSEPARATOR": ",", "DECIMALSEPARATOR": "." };
let offDays;
let addedRules;

let sm_dekstop_5de = "50px";
let sm_dekstop_1 = "60px";
let sm_dekstop_2 = "100px";
let sm_dekstop_3 = "150px";
let sm_dekstop_4 = "200px";
let sm_dekstop_5 = "250px";
let md_dekstop_1 = "250px";
let md_dekstop_2 = "300px";
let md_dekstop_3 = "350px";
let md_dekstop_4 = "400px";
let md_dekstop_5 = "450px";
let lg_dekstop_1 = "450px";
let lg_dekstop_2 = "500px";
let lg_dekstop_3 = "550px";
let lg_dekstop_4 = "600px";
let lg_dekstop_5 = "650px";

let sm_mobile_5de = "110px";
let sm_mobile_1 = "150px";
let sm_mobile_2 = "200px";
let sm_mobile_3 = "250px";
let sm_mobile_4 = "300px";
let sm_mobile_5 = "350px";
let md_mobile_1 = "350px";
let md_mobile_2 = "400px";
let md_mobile_3 = "450px";
let md_mobile_4 = "500px";
let md_mobile_5 = "550px";
let lg_mobile_1 = "550px";
let lg_mobile_2 = "600px";
let lg_mobile_3 = "650px";
let lg_mobile_4 = "700px";
let lg_mobile_5 = "750px";

let sm_extendSize_1 = 50;
let sm_extendSize_2 = 100;
let sm_extendSize_3 = 150;
let sm_extendSize_4 = 200;
let md_extendSize_1 = 250;
let md_extendSize_2 = 300;
let md_extendSize_3 = 350;
let md_extendSize_4 = 400;
let lg_extendSize_1 = 450;
let lg_extendSize_2 = 500;
let lg_extendSize_3 = 550;
let lg_extendSize_4 = 600;

/**
 * colWidth(size) — helper untuk lebar kolom jqGrid responsif.
 * Deteksi desktop/mobile secara internal, tidak perlu deklarasi isDesktop di view.
 *
 * Ukuran:  'xxs' | 'xs' | 'sm' | 'md' | 'lg' | 'xl' | 'xxl'
 *
 * Contoh penggunaan di colModel:
 *   width: colWidth('lg')          → 200 (desktop) / 300 (mobile)
 *   width: colWidth('md')          → 150 (desktop) / 250 (mobile)
 *   width: colWidth('sm', 120, 220) → override custom: 120 desktop / 220 mobile
 */
function colWidth(size, desktopOverride, mobileOverride) {
    const isDesktop = detectDeviceType() === 'desktop';
    if (desktopOverride !== undefined && mobileOverride !== undefined) {
        return isDesktop ? desktopOverride : mobileOverride;
    }
    const map = {
        'xxs': [50,  110],
        'xs':  [60,  150],
        'sm':  [100, 200],
        'md':  [150, 250],
        'lg':  [200, 300],
        'xl':  [250, 350],
        'xxl': [300, 400],
    };
    const pair = map[size] || map['md'];
    return isDesktop ? pair[0] : pair[1];
}

// Disable jqGrid row hover globally
if (typeof $.jgrid !== 'undefined' && $.jgrid.defaults) {
    $.extend($.jgrid.defaults, { hoverrows: false });
}
// Remove table-hover class from Bootstrap UI defaults if it exists
if (typeof $.jgrid !== 'undefined' && $.jgrid.styleUI) {
    if ($.jgrid.styleUI.Bootstrap) {
        if ($.jgrid.styleUI.Bootstrap.table && $.jgrid.styleUI.Bootstrap.table.className) {
            $.jgrid.styleUI.Bootstrap.table.className = $.jgrid.styleUI.Bootstrap.table.className.replace('table-hover', '');
        }
    }
    if ($.jgrid.styleUI.Bootstrap4) {
        if ($.jgrid.styleUI.Bootstrap4.table && $.jgrid.styleUI.Bootstrap4.table.className) {
            $.jgrid.styleUI.Bootstrap4.table.className = $.jgrid.styleUI.Bootstrap4.table.className.replace('table-hover', '');
        }
        // if ($.jgrid.styleUI.Bootstrap4.base) {
        //     $.jgrid.styleUI.Bootstrap4.base.icon_asc = 'fa-arrow-up';
        //     $.jgrid.styleUI.Bootstrap4.base.icon_desc = 'fa-arrow-down';
        // }
    }
    if ($.jgrid.styleUI.Bootstrap5) {
        if ($.jgrid.styleUI.Bootstrap5.table && $.jgrid.styleUI.Bootstrap5.table.className) {
            $.jgrid.styleUI.Bootstrap5.table.className = $.jgrid.styleUI.Bootstrap5.table.className.replace('table-hover', '');
        }
        // if ($.jgrid.styleUI.Bootstrap5.base) {
        //     $.jgrid.styleUI.Bootstrap5.base.icon_asc = 'fa-arrow-up';
        //     $.jgrid.styleUI.Bootstrap5.base.icon_desc = 'fa-arrow-down';
        // }
    }

    // Explicitly inject fontAwesome mapping if missing or override if present
    // if (!$.jgrid.styleUI.fontAwesome) {
    //     $.jgrid.styleUI.fontAwesome = {
    //         common: { icon_base: "fas" },
    //         base: {
    //             icon_asc: 'fa-arrow-up',
    //             icon_desc: 'fa-arrow-down'
    //         }
    //     };
    // } else if ($.jgrid.styleUI.fontAwesome.base) {
    //     $.jgrid.styleUI.fontAwesome.base.icon_asc = 'fa-arrow-up';
    //     $.jgrid.styleUI.fontAwesome.base.icon_desc = 'fa-arrow-down';
    // }
}

// Inject CSS to hide disabled sort icons and align active arrows perfectly inline
$('<style>th.ui-th-column .ui-grid-ico-sort.ui-disabled { display: none !important; } th.ui-th-column .s-ico { display: inline-block !important; } th.ui-th-column .ui-grid-ico-sort { position: static !important; margin-top: 0 !important; margin-left: 6px !important; font-size: 0.9em !important; transform: translateY(1px) !important; }</style>').appendTo('head');

$(document).ready(function () {
    // setFormats();
    startTime();
    setSidebarBindKeys();
    openMenuParents();
    // initDatepicker();
    initSelect2();
    initAutoNumeric();
    initDisabled();
    activeUrl();

    /* Remove autocomplete */
    $("input").attr("autocomplete", "off");
    $("input, textarea").attr("spellcheck", "false");
    $("input[type=password]").css({
        "text-transform": "none",
        "border-right": "none",
    });
    $(".focusPass").css("background-color", "#E0ECFF");

    $(".delete-row").removeClass("btn-sm");

    // $(document).on('focus', ".password", function(event) {
    // 	$(".focusPass").css({"background-color":"#ffffee", "border-color":"#80bdff"});
    // });

    // $(document).on('blur', ".password", function(event) {
    // 	$(".focusPass").css({"background-color":"#fff", "border-color":"#ced4da"});
    // });
    $(document).on("click", "#sidebar-overlay", () => {
        $(document).trigger("sidebar:toggle");

        sidebarIsOpen = false;
    });

    function activeUrl() {
        // Use appUrl to find the relative path
        const currentUrl = window.location.href;
        let relativePath = currentUrl.replace(appUrl, '');
        // Extract the controller part (first segment after base url)
        const pathLink = relativePath.split('/')[0].split('?')[0].split('#')[0].toLowerCase();
        
        let breadcrumbString = [];

        // Find the element by checking if its href's controller matches pathLink
        var activeElement = $('.nav-sidebar a').filter(function () {
            let href = $(this).attr('href');
            if (href && href !== '#' && href !== 'javascript:void(0)' && href !== 'javascript:;') {
                let hrefRelative = href.replace(appUrl, '');
                let hrefPath = hrefRelative.split('/')[0].split('?')[0].split('#')[0].toLowerCase();
                
                // Only match if both have valid path lengths and they match
                if (hrefPath !== '' && pathLink !== '') {
                    return hrefPath === pathLink;
                }
            }
            return false;
        }).first();

        if (activeElement.length) {
            // Add 'active' class to the element
            activeElement.addClass("active");
            var topNavItem = activeElement
                .closest(".nav-item")
                .parents(".nav-item")
                .last();

            // Iterate over all parent elements up to the main sidebar
            activeElement.parents(".nav-item").each(function (index) {
                // Add 'menu-open' class to the parent 'nav-item'
                $(this).addClass("menu-open");
                let breadcrumbText = $(this).find('a').find('p')[0].innerText.trim();
                if (index === 0) {
                    const href = $(this).find('a').attr('href') || '#';
                    breadcrumbText = `<a href="${href}">${breadcrumbText}</a>`;
                }
                breadcrumbString.push(breadcrumbText);

                // Add 'active' class to the parent link
                if (topNavItem[0] == $(this)[0]) {
                    $(this).children("a.nav-link").addClass("active");
                    // }else{
                    //     $(this).children('a.nav-link').addClass('active-parrent');
                }
            });
            //   activeElement.removeClass('active-parrent');
            //   topNavItem.children('a.nav-link').addClass('active');
            breadcrumbString.reverse();

            let breadcrumbHtml = '<li class="breadcrumb-item"><a href="' + appUrl + 'home">Home</a></li>';
            breadcrumbString.forEach(function (text, index) {
                if (index === breadcrumbString.length - 1) {
                    breadcrumbHtml += '<li class="breadcrumb-item active">' + text + '</li>';
                } else {
                    breadcrumbHtml += '<li class="breadcrumb-item">' + text + '</li>';
                }
            });
            $('.breadcrumb').html(breadcrumbHtml);
        }
    }

    $(document).on("click", ".toggle-password", function (event) {
        $(this).toggleClass("fa-eye fa-eye-slash");
        var input = $($(this).attr("toggle"));
        if (input.attr("type") == "password") {
            input.attr("type", "text");
        } else {
            input.attr("type", "password");
        }
    });

    $(document).on("show.bs.modal", ".modal", function () {
        const zIndex = 1040 + 10 * $(".modal:visible").length;
        $(this).css("z-index", zIndex);
        setTimeout(() =>
            $(".modal-backdrop")
                .not(".modal-stack")
                .css("z-index", zIndex - 1)
                .addClass("modal-stack")
        );
    });

    $(document).on("hidden.bs.modal", ".modal", function () {
        $(".modal:visible").length && $(document.body).addClass("modal-open");
    });

    $("#loader").addClass("d-none");

    $.fn.modal.Constructor.Default.backdrop = "static";
});
$("#listMenuModal").on("show.bs.modal", function () {
    $(this).data("bs.modal")._config.backdrop = true;
    setTimeout(() => $(".modal-backdrop").addClass("custom-backdrop"));
});
$("#listMenuModal").on("hidden.bs.modal", function () {
    $(this).find(".modal-body").html("");
    setTimeout(() => $(".modal-backdrop").removeClass("custom-backdrop"));
});

window.onbeforeunload = () => {
    $("#loader").removeClass("d-none");
};
$(window).on("pageshow", function () {
    $("#loader").addClass("d-none");
});

function changeJqGridRowListText() {
    $(document).find('select[id$="rowList"] option[value=0]').text("ALL");
}

function setFormats() {
    $.ajax({
        url: `${appUrl}/formats/global.json`,
        method: "GET",
        dataType: "JSON",
        async: false,
        cache: false,
        success: (response) => {
            formats = response;
        },
        error: (error) => {
            showDialog(error.statusText);
        },
    });
}

function initDisabled() {
    $(".disabled").each(function () {
        $(this).disable();
    });
}

function initAutoNumeric(elements = null, options = null) {
    let option = {
        digitGroupSeparator: formats.THOUSANDSEPARATOR,
        decimalCharacter: formats.DECIMALSEPARATOR,
        modifyValueOnWheel: false,
        minimumValue: 0,
    };

    Object.assign(option, options);

    if (elements == null) {
        new AutoNumeric.multiple(".autonumeric", option);
    } else {
        $.each(elements, (index, element) => {
            new AutoNumeric(element, option);
            if ($(element).is("input")) {
                $(element).attr({
                    pattern: "d*",
                    inputmode: "numeric",
                });
                $(element).on("click", function () {
                    $(this).select();
                });
            }
        });
    }
}

function initAutoNumericMinus(elements = null, options = null) {
    let option = {
        digitGroupSeparator: formats.THOUSANDSEPARATOR,
        decimalCharacter: formats.DECIMALSEPARATOR,
        modifyValueOnWheel: false,
    };

    Object.assign(option, options);

    if (elements == null) {
        new AutoNumeric.multiple(".autonumeric", option);
    } else {
        $.each(elements, (index, element) => {
            new AutoNumeric(element, option);
        });
    }
}

function unformatAutoNumeric(data) {
    let autoNumericElements = $(".autonumeric");

    $.each(autoNumericElements, (index, autoNumericElement) => {
        let inputs = data.filter((row) => row.name == autoNumericElement.name);

        inputs.forEach((input, index) => {
            if (input.value !== "") {
                input.value = AutoNumeric.getNumber(autoNumericElement);
            }
        });
    });

    return data;
}

function setHighlight(grid) {
    let stringFilters;
    let filters;
    let gridId;

    stringFilters = grid.getGridParam("postData").filters;

    if (stringFilters) {
        filters = JSON.parse(stringFilters);
    }

    gridId = $(grid).getGridParam().id;

    if (filters) {
        filters.rules.forEach((rule) => {
            $(grid)
                .find(`tbody tr td[aria-describedby=${gridId}_${rule.field}]`)
                .each(function () {
                    // Check if the cell contains a badge element
                    if ($(this).find(".badge").length === 0) {
                        $(this).highlight(rule.data);
                    }
                });
            // .highlight(rule.data);
        });
    }
}

function currencyFormat(value) {
    let result = parseFloat(value).toLocaleString("en-US", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

    result = result.replace(/\./g, "*");
    result = result.replace(/,/g, formats.THOUSANDSEPARATOR);
    result = result.replace(/\*/g, formats.DECIMALSEPARATOR);

    return result;
}

function currencyUnformat(value) {
    let result = parseFloat(value.replaceAll(formats.THOUSANDSEPARATOR, ""));

    return result;
}

function dateFormat(value) {
    let date = new Date(value);

    let seconds = date.getSeconds("default");
    let minutes = date.getMinutes("default");
    let hours = date.getHours("default");
    let day = date.getDate("default");
    let month = date.getMonth("default") + 1;
    let year = date.getFullYear("default");

    return `${day.toString().padStart(2, "0")}-${month
        .toString()
        .padStart(2, "0")}-${year}`;
}
function monthFormat(value) {
    let date = new Date(value);

    let seconds = date.getSeconds("default");
    let minutes = date.getMinutes("default");
    let hours = date.getHours("default");
    let day = date.getDate("default");
    let month = date.getMonth("default") + 1;
    let year = date.getFullYear("default");

    return `${month
        .toString()
        .padStart(2, "0")}-${year}`;
}

function setNumberSeparators() {
    $.ajax({
        url: `${appUrl}parameter`,
        method: "GET",
        async: false,
        data: {
            filters: JSON.stringify({
                groupOp: "AND",
                rules: [
                    {
                        field: "grp",
                        op: "cn",
                        data: "FORMAT ANGKA",
                    },
                ],
            }),
        },
        beforeSend: (jqXHR) => {
            jqXHR.setRequestHeader("Authorization", `Bearer ${accessToken}`);
        },
        success: (response) => {
            response.data.forEach((data) => {
                if (data.subgrp == "DESIMAL") {
                    decimalSeparator = data.text;
                } else if (data.subgrp == "RIBUAN") {
                    thousandSeparator = data.text;
                }
            });
        },
    });
}

function openMenuParents() {
    let currentMenu = $("a.nav-link.active").first();
    let parents = currentMenu.parents("li.nav-item");

    parents.each((index, parent) => {
        $(parent).addClass("menu-open");
    });
}

$(document).on("sidebar:toggle", () => {
    if ($("body").hasClass("sidebar-collapse")) {
        sidebarIsOpen = false;

        $("#search").focusout();
        $("body").removeClass("no-scroll");
    } else if ($("body").hasClass("sidebar-open")) {
        sidebarIsOpen = true;

        $("body").addClass("no-scroll");

        if (detectDeviceType() == "desktop") {
            $("#search").focus();
        }
    }
});

$(document).ajaxError((event, jqXHR, ajaxSettings, thrownError) => {
    if (jqXHR.status === 401) {
        // showDialog(thrownError, jqXHR.responseJSON.message);
    }
});

// $(window).on("resize", function (event) {
// 	if ($(window).width() > 990) {
// 		$("body").removeClass();
// 		setTimeout(() => {
// 			$("body").addClass("sidebar-closed sidebar-collapse");
// 		}, 0);
// 	}
// });

$(".sidebars").click(function (e) {
    $("body").addClass("sidebar-open");
    e.preventDefault();
});

$(document).mouseup(function (e) {
    var container = $(".main-sidebar");
    if (!container.is(e.target) && container.has(e.target).length === 0) {
        if ($("body").hasClass("sidebar-open")) {
            $("body").removeClass("sidebar-open");
        }
    }
});

/* Disable plugin */
$.fn.disable = function () {
    this.bind("cut copy paste change", function () {
        return false;
    });

    this.on("keydown", (e) => {
        if (!e.altKey && !e.ctrlKey && e.which !== 27) {
            e.preventDefault();
            return false;
        }
    });

    if (this.is("select")) {
        let selected = this.find("option:selected");

        this.change(() => {
            this.val(selected.val());
        });
    }
};

function setErrorMessages(form, errors) {
    $.each(errors, (index, error) => {
        let indexes = index.split(".");
        let element;

        if (indexes.length > 1) {
            element = form.find(`[name="${indexes[0]}[]"]`)[indexes[1]];
        } else {
            element = form.find(`[name="${indexes[0]}"]`)[0];
        }

        if ($(element).length > 0 && !$(element).is(":hidden")) {
            $(element).addClass("is-invalid");
            $(`
					<div class="invalid-feedback">
					${error[0].toLowerCase()}
					</div>
			`).appendTo($(element).parent());
        } else {
            return showDialog(error);
        }
    });

    $(".is-invalid").first().focus();
}

function removeTags(str) {
    if (str === null || str === "") return false;
    else str = str.toString();
    return str.replace(/(<([^>]+)>)/gi, "");
}

/**
 * Set Home, End, PgUp, PgDn
 * to move grid page
 */
let topSelected = 0;
let bottomSelected = 12;
function setCustomBindKeys(grid) {
    if (grid.length > 0) {
        activeGrid = grid;
    }

    setSidebarBindKeys();

    $(document).off("keydown.grid").on("keydown.grid", function (e) {
        if (!sidebarIsOpen && activeGrid && activeGrid.length > 0) {
            // Abaikan jika fokus di input/textarea
            // if ($(e.target).is("input, textarea, select")) return;

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

                var grid = $(activeGrid);
                var gridId = grid.attr('id');
                var gridSelector = '#' + gridId;
                var datatype = grid.jqGrid('getGridParam', 'datatype');
                var isLazyLoad = (datatype === 'local' && typeof loadGridData === 'function');

                var gridIds = grid.getDataIDs();
                var selectedRow = grid.getGridParam("selrow");
                var currentIndex = gridIds.indexOf(selectedRow ? selectedRow.toString() : "");

                // Row Height & Visible Rows for Lazy Load calculations
                var bDiv = grid.closest('.ui-jqgrid-bdiv');
                var singleRowHeight = grid.find('tr.jqgrow').first().height() || 30;
                var visibleRows = Math.floor(bDiv.height() / singleRowHeight) || 10;
                var serverPageSize = (typeof rowsPerPage !== 'undefined' && rowsPerPage > 0) ? rowsPerPage : (parseInt(grid.getGridParam('rowNum')) || 50);

                // =================================================================
                // STANDARD JQGRID LOGIC (Non-Lazy)
                // =================================================================
                if (!isLazyLoad) {
                    var currentPage = grid.getGridParam("page");
                    var lastPage = grid.getGridParam("lastpage");

                    if (33 === e.keyCode) { // PageUp
                        if (currentPage > 1) {
                            grid.jqGrid("setGridParam", { page: parseInt(currentPage) - 1 }).trigger("reloadGrid");
                        }
                    } else if (34 === e.keyCode) { // PageDown
                        if (currentPage !== lastPage) {
                            grid.jqGrid("setGridParam", { page: parseInt(currentPage) + 1 }).trigger("reloadGrid");
                        }
                    } else if (35 === e.keyCode) { // End
                        if (e.ctrlKey) {
                            grid.jqGrid("setSelection", gridIds[gridIds.length - 1]);
                        } else if (currentPage !== lastPage) {
                            grid.jqGrid("setGridParam", { page: lastPage }).trigger("reloadGrid");
                        }
                    } else if (36 === e.keyCode) { // Home
                        if (e.ctrlKey) {
                            grid.jqGrid("setSelection", gridIds[0]);
                        } else if (currentPage > 1) {
                            grid.jqGrid("setGridParam", { page: 1 }).trigger("reloadGrid");
                        }
                    } else if (38 === e.keyCode) { // Up
                        if (currentIndex > 0) {
                            grid.resetSelection().setSelection(gridIds[currentIndex - 1]);
                        }
                    } else if (40 === e.keyCode) { // Down
                        if (currentIndex < gridIds.length - 1) {
                            grid.resetSelection().setSelection(gridIds[currentIndex + 1]);
                        }
                    }
                }
                // =================================================================
                // LAZY LOAD LOGIC
                // =================================================================
                else {
                    var state = (typeof getGridState === 'function') ? getGridState(grid) : {};
                    var s_minPageLoaded = state.minPageLoaded || 1;
                    var s_maxPageLoaded = state.maxPageLoaded || 1;
                    var s_totalRecord = state.totalRecord || parseInt(grid.getGridParam("records")) || 0;

                    var apiUrl = grid.jqGrid('getGridParam', 'url');
                    var postData = grid.jqGrid('getGridParam', 'postData');

                    var safeIndex = Math.max(0, currentIndex);
                    var totRec = s_totalRecord;

                    var currentAbsIndex = 0;
                    var firstDomAbsIndex = 0;
                    var lastDomAbsIndex = 0;

                    if (gridIds.length > 0) {
                        currentAbsIndex = parseInt(grid.jqGrid('getCell', gridIds[safeIndex], 'rn'), 10) - 1;
                        firstDomAbsIndex = parseInt(grid.jqGrid('getCell', gridIds[0], 'rn'), 10) - 1;
                        lastDomAbsIndex = parseInt(grid.jqGrid('getCell', gridIds[gridIds.length - 1], 'rn'), 10) - 1;
                    }

                    if (isNaN(currentAbsIndex)) currentAbsIndex = 0;
                    if (isNaN(firstDomAbsIndex)) firstDomAbsIndex = 0;
                    if (isNaN(lastDomAbsIndex)) lastDomAbsIndex = 0;

                    var currentSelectedPage = Math.floor(currentAbsIndex / serverPageSize) + 1;

                    if (e.keyCode === 38) { // Up
                        if (currentIndex > 0) {
                            grid.resetSelection().setSelection(gridIds[currentIndex - 1]);
                            scrollRowIntoView(grid, gridIds[currentIndex - 1]);
                        } else if (firstDomAbsIndex > 0) {
                            var targetAbsIndex = currentAbsIndex - 1;
                            var targetPageLoad = Math.max(1, s_minPageLoaded - 1);

                            // Arrow Up TETAP menggunakan 'up', 'page' karena menyisip data
                            loadGridData(gridSelector, apiUrl, postData, targetPageLoad, serverPageSize, 'up', 'page', function () {
                                var newIds = grid.getDataIDs();
                                var newFirstAbs = parseInt(grid.jqGrid('getCell', newIds[0], 'rn'), 10) - 1 || 0;
                                var targetDomIdx = targetAbsIndex - newFirstAbs;

                                if (targetDomIdx >= 0 && targetDomIdx < newIds.length) {
                                    var targetId = newIds[targetDomIdx];
                                    grid.resetSelection().setSelection(targetId);
                                    scrollRowIntoView(grid, targetId);
                                }
                            });
                        }
                    } else if (e.keyCode === 40) { // Down
                        if (currentIndex < gridIds.length - 1) {
                            grid.resetSelection().setSelection(gridIds[currentIndex + 1]);
                            scrollRowIntoView(grid, gridIds[currentIndex + 1]);
                        } else if (lastDomAbsIndex < totRec - 1) {
                            var targetAbsIndex = currentAbsIndex + 1;
                            var targetPageLoad = s_maxPageLoaded + 1;

                            // Arrow Down TETAP menggunakan 'down', 'page' karena menyisip data
                            loadGridData(gridSelector, apiUrl, postData, targetPageLoad, serverPageSize, 'down', 'page', function () {
                                var newIds = grid.getDataIDs();
                                var newFirstAbs = parseInt(grid.jqGrid('getCell', newIds[0], 'rn'), 10) - 1 || 0;
                                var targetDomIdx = targetAbsIndex - newFirstAbs;

                                if (targetDomIdx >= 0 && targetDomIdx < newIds.length) {
                                    var targetId = newIds[targetDomIdx];
                                    grid.resetSelection().setSelection(targetId);
                                    scrollRowIntoView(grid, targetId);
                                }
                            });
                        }
                    } else if (e.keyCode === 33) { // PageUp
                        var targetAbsIndex = currentAbsIndex - visibleRows + 1;
                        if (targetAbsIndex < 0) targetAbsIndex = 0;

                        if (targetAbsIndex >= firstDomAbsIndex && targetAbsIndex <= lastDomAbsIndex) {
                            var targetDomIdx = targetAbsIndex - firstDomAbsIndex;
                            var targetId = gridIds[targetDomIdx];
                            grid.resetSelection().setSelection(targetId);
                            scrollRowIntoView(grid, targetId);
                        } else if (firstDomAbsIndex > 0) {
                            var targetPageLoad = Math.floor(targetAbsIndex / serverPageSize) + 1;

                            // UBAH 'up' MENJADI 'jump' DI SINI
                            loadGridData(gridSelector, apiUrl, postData, targetPageLoad, serverPageSize, 'jump', 'jump', function () {
                                var newIds = grid.getDataIDs();
                                if (newIds.length > 0) {
                                    var newFirstAbs = parseInt(grid.jqGrid('getCell', newIds[0], 'rn'), 10) - 1 || 0;
                                    var targetDomIdx = targetAbsIndex - newFirstAbs;
                                    if (targetDomIdx < 0) targetDomIdx = 0;
                                    if (targetDomIdx >= newIds.length) targetDomIdx = newIds.length - 1;

                                    var targetId = newIds[targetDomIdx];
                                    grid.resetSelection().setSelection(targetId);
                                    scrollRowIntoView(grid, targetId);
                                }
                            });
                        }
                    } else if (e.keyCode === 34) { // PageDown
                        var targetAbsIndex = currentAbsIndex + visibleRows - 1;
                        if (targetAbsIndex >= totRec) targetAbsIndex = totRec - 1;

                        if (targetAbsIndex >= firstDomAbsIndex && targetAbsIndex <= lastDomAbsIndex) {
                            var targetDomIdx = targetAbsIndex - firstDomAbsIndex;
                            var targetId = gridIds[targetDomIdx];
                            grid.resetSelection().setSelection(targetId);
                            scrollRowIntoView(grid, targetId);
                        } else if (lastDomAbsIndex < totRec - 1) {
                            var targetPageLoad = Math.floor(targetAbsIndex / serverPageSize) + 1;

                            // UBAH 'down' MENJADI 'jump' DI SINI
                            loadGridData(gridSelector, apiUrl, postData, targetPageLoad, serverPageSize, 'jump', 'jump', function () {
                                var newIds = grid.getDataIDs();
                                if (newIds.length > 0) {
                                    var newFirstAbs = parseInt(grid.jqGrid('getCell', newIds[0], 'rn'), 10) - 1 || 0;
                                    var targetDomIdx = targetAbsIndex - newFirstAbs;
                                    if (targetDomIdx < 0) targetDomIdx = 0;
                                    if (targetDomIdx >= newIds.length) targetDomIdx = newIds.length - 1;

                                    var targetId = newIds[targetDomIdx];
                                    grid.resetSelection().setSelection(targetId);
                                    scrollRowIntoView(grid, targetId);
                                }
                            });
                        }
                    } else if (e.keyCode === 36) { // Home
                        if (firstDomAbsIndex === 0) {
                            if (gridIds.length > 0) {
                                grid.resetSelection().setSelection(gridIds[0]);
                                scrollRowIntoView(grid, gridIds[0]);
                            }
                        } else {
                            // UBAH 'down' MENJADI 'jump' DI SINI
                            loadGridData(gridSelector, apiUrl, postData, 1, serverPageSize, 'jump', 'jump', function () {
                                var newIds = grid.getDataIDs();
                                if (newIds.length > 0) {
                                    grid.resetSelection().setSelection(newIds[0]);
                                    scrollRowIntoView(grid, newIds[0]);
                                }
                            });
                        }
                    } else if (e.keyCode === 35) { // End
                        if (lastDomAbsIndex >= totRec - 1) {
                            if (gridIds.length > 0) {
                                grid.resetSelection().setSelection(gridIds[gridIds.length - 1]);
                                scrollRowIntoView(grid, gridIds[gridIds.length - 1]);
                            }
                        } else {
                            var lastPageLoad = Math.ceil(totRec / serverPageSize);

                            // UBAH 'down' MENJADI 'jump' DI SINI
                            loadGridData(gridSelector, apiUrl, postData, lastPageLoad, serverPageSize, 'jump', 'jump', function () {
                                var newIds = grid.getDataIDs();
                                if (newIds.length > 0) {
                                    grid.resetSelection().setSelection(newIds[newIds.length - 1]);
                                    scrollRowIntoView(grid, newIds[newIds.length - 1]);
                                }
                            });
                        }
                    }
                }

                // ENTER Handling
                if (13 === e.keyCode) {
                    if (selectedRow) {
                        var ondblClickRowHandler = grid.jqGrid("getGridParam", "ondblClickRow");
                        if (typeof ondblClickRowHandler === 'function') {
                            ondblClickRowHandler.call(grid[0], selectedRow);
                        }
                    }
                }
            }
        }
    });
}

function scrollRowIntoView(grid, rowId) {
    var bDiv = grid.closest('.ui-jqgrid-bdiv');
    var escapedRowId = $.jgrid.jqID(rowId);
    var row = grid.find('tr#' + escapedRowId);

    if (row.length > 0) {
        var rowTop = row[0].offsetTop;
        var rowBottom = rowTop + row[0].offsetHeight;
        var divScrollTop = bDiv.scrollTop();

        // clientHeight sudah otomatis akurat tanpa menghitung scrollbar horizontal
        var divHeight = bDiv[0].clientHeight;

        if (rowTop < divScrollTop) {
            bDiv.scrollTop(rowTop);
        } else if (rowBottom > divScrollTop + divHeight) {
            // Cukup kurangi dengan divHeight murni
            bDiv.scrollTop(rowBottom - divHeight);
        }
    }
}

function setSidebarBindKeys() {
    $(document).on("keydown", (event) => {
        if (event.keyCode === 77 && event.altKey) {
            event.preventDefault();

            $("#sidebarButton").click();
        }

        if (sidebarIsOpen) {
            let allowedKeyCodes = [37, 38, 39, 40];

            if (allowedKeyCodes.includes(event.keyCode)) {
                event.preventDefault();

                $("#search").val("");

                if ($(".nav-link.active, .nav-link.hover").length <= 0) {
                    $(".main-sidebar nav .nav-link").first().addClass("hover");
                }

                switch (event.keyCode) {
                    case 37:
                        setUpOneLevelMenu();

                        break;
                    case 38:
                        setPreviousMenuHover();

                        break;
                    case 39:
                        setDownOneLevelMenu();

                        break;
                    case 40:
                        setNextMenuHover();

                        break;
                    default:
                        break;
                }
            } else if (event.keyCode === 13) {
                let hoveredElement = $(".nav-link.hover");

                if (hoveredElement.length > 0) {
                    if (hoveredElement.siblings("ul").length > 0) {
                        setDownOneLevelMenu();
                    } else {
                        hoveredElement[0].click();
                    }
                }
            }
        }
    });
}

function setNextMenuHover() {
    let currentElement = $(".nav-link.hover").first();

    if (currentElement.length <= 0) {
        currentElement = $(".nav-link.selected-link");
    }

    if (currentElement.length <= 0) {
        currentElement = $(".nav-link.active");
    }

    let nextElement = currentElement
        .parent(".nav-item")
        .next()
        .find(".nav-link")
        .first();

    if (nextElement.length > 0) {
        currentElement.removeClass("selected-link hover");
        nextElement.addClass("hover");
    }
}

function setPreviousMenuHover() {
    let currentElement = $(".nav-link.hover").first();

    if (currentElement.length <= 0) {
        currentElement = $(".nav-link.selected-link");
    }

    if (currentElement.length <= 0) {
        currentElement = $(".nav-link.active");
    }

    let nextElement = currentElement
        .parent(".nav-item")
        .prev()
        .find(".nav-link")
        .first();

    if (nextElement.length > 0) {
        currentElement.removeClass("selected-link hover");
        nextElement.addClass("hover");
    }
}

function setUpOneLevelMenu() {
    let currentElement = $(".nav-link.hover").first();

    if (currentElement.length <= 0) {
        currentElement = $(".nav-link.selected-link");
    }

    if (currentElement.length <= 0) {
        currentElement = $(".nav-link.active");
    }

    let upOneLevelElement = currentElement.parents().eq(2);

    if (upOneLevelElement.length > 0) {
        currentElement.removeClass("selected-link hover");
        upOneLevelElement.removeClass("menu-is-opening menu-open");
        upOneLevelElement.find(".nav-link").first().addClass("hover");
    }
}

function setDownOneLevelMenu() {
    let currentElement = $(".nav-link.hover").first();

    if (currentElement.length <= 0) {
        currentElement = $(".nav-link.selected-link");
    }

    if (currentElement.length <= 0) {
        currentElement = $(".nav-link.active");
    }

    let downOneLevelElement = currentElement
        .siblings("ul")
        .css({
            display: "",
        })
        .find(".nav-link")
        .first();

    if (downOneLevelElement.length > 0) {
        currentElement.removeClass("selected-link hover");
        currentElement.parent(".nav-item").addClass("menu-open");
        downOneLevelElement.addClass("hover");
    }
}

function fillSearchMenuInput() {
    let currentElement = $(".nav-link.hover").first();

    if (currentElement.length <= 0) {
        currentElement = $(".nav-link.selected-link");
    }

    if (currentElement.length <= 0) {
        currentElement = $(".nav-link.active");
    }

    $("#search").val(currentElement.attr("id"));
}

// Bind virtual grid keys
function setCustomLazyLoadingKeys(gridId, menu) {
    setSidebarBindKeys();

    $(document).on("keydown", function (e) {
        // Pastikan sidebar tertutup dan ada grid aktif
        if (!sidebarIsOpen && activeGrid) {

            // Filter tombol navigasi: PageUp(33), PageDown(34), End(35), Home(36), Up(38), Down(40), Enter(13)
            if ([33, 34, 35, 36, 38, 40, 13].includes(e.keyCode)) {
                e.preventDefault();

                var grid = $(activeGrid);
                var gridId = grid.attr('id');
                var gridSelector = '#' + gridId;

                // --- VARIABEL KUNCI ---
                // rowNum = jumlah baris yang TERLIHAT di layar (bukan server page size)
                var bDiv = grid.closest('.ui-jqgrid-bdiv');
                var singleRowHeight = grid.find('tr.jqgrow').first().height()
                    || grid.find('tr[id]').first().height()
                    || (typeof DEFAULT_ROW_HEIGHT !== 'undefined' ? DEFAULT_ROW_HEIGHT : 30);
                var visibleRows = Math.floor(bDiv.height() / singleRowHeight);
                if (visibleRows < 1) visibleRows = 1;

                // Server page size untuk kalkulasi halaman (Home/End)
                var serverPageSize = (typeof rowsPerPage !== 'undefined' && rowsPerPage > 0)
                    ? rowsPerPage
                    : (parseInt(grid.getGridParam('rowNum')) || 50);

                // Ambil total record ASLI dari server
                var records = parseInt(grid.getGridParam("records")) || 0;
                if (typeof totalRecord !== 'undefined' && totalRecord > 0) records = totalRecord;

                var gridIds = grid.getDataIDs();
                var selectedRowId = grid.getGridParam("selrow");
                var currentIndex = -1;

                if (selectedRowId) {
                    currentIndex = gridIds.indexOf(selectedRowId);
                }

                // Helper: scroll bDiv agar baris targetRow terlihat
                function scrollRowIntoGridView(targetRowEl) {
                    if (!targetRowEl) return;
                    var rowTop = targetRowEl.offsetTop;
                    var rowBottom = rowTop + targetRowEl.offsetHeight;
                    var divScrollTop = bDiv.scrollTop();
                    var divHeight = bDiv.height();
                    if (rowTop < divScrollTop) {
                        bDiv.scrollTop(rowTop);
                        lastScrollTop = rowTop;
                    } else if (rowBottom > divScrollTop + divHeight) {
                        var newST = rowBottom - divHeight;
                        bDiv.scrollTop(newST);
                        lastScrollTop = newST;
                    }
                }

                // =================================================================
                // KEY 33: PAGE UP — Scroll ke atas sebanyak jumlah baris terlihat
                // =================================================================
                if (e.keyCode === 33) {
                    var targetIdx = Math.max(0, currentIndex - visibleRows);
                    if (targetIdx !== currentIndex && gridIds.length > 0) {
                        var targetId = gridIds[targetIdx];
                        grid.setSelection(targetId);
                        scrollRowIntoGridView(grid.find('tr#' + targetId)[0]);
                    }

                    // Mentok atas: load halaman sebelumnya (prepend)
                    if (targetIdx <= 0) {
                        var curPage = (typeof minPageLoaded !== 'undefined') ? minPageLoaded : 1;
                        if (curPage > 1) {
                            loadGridData(
                                gridSelector,
                                apiUrl + menu,
                                accessToken,
                                grid.jqGrid('getGridParam', 'postData'),
                                curPage - 1,
                                serverPageSize,
                                'up',    // prepend data di atas
                                'page'   // TIDAK trigger auto-fill
                            );
                        }
                    }
                }

                // =================================================================
                // KEY 34: PAGE DOWN — Scroll ke bawah sebanyak jumlah baris terlihat
                // =================================================================
                if (e.keyCode === 34) {
                    var moveStep = Math.max(1, visibleRows - 1);
                    var targetIdx = Math.min(gridIds.length - 1, currentIndex + moveStep);
                    if (targetIdx !== currentIndex && gridIds.length > 0) {
                        var targetId = gridIds[targetIdx];
                        grid.setSelection(targetId);
                        scrollRowIntoGridView(grid.find('tr#' + targetId)[0]);
                    }

                    // Mentok bawah: load halaman berikutnya (append)
                    if (targetIdx >= gridIds.length - 1) {
                        var nextPage = (typeof maxPageLoaded !== 'undefined') ? maxPageLoaded + 1 : 2;
                        var maxPages = (typeof totalPages !== 'undefined' && totalPages > 0) ? totalPages : 999;
                        if (nextPage <= maxPages) {
                            loadGridData(
                                gridSelector,
                                apiUrl + menu,
                                accessToken,
                                grid.jqGrid('getGridParam', 'postData'),
                                nextPage,
                                serverPageSize,
                                'down',  // append data di bawah
                                'page'   // TIDAK trigger auto-fill
                            );
                        }
                    }
                }

                // =================================================================
                // KEY 36: HOME (Ke Halaman 1)
                // =================================================================
                if (e.keyCode === 36) {
                    var currentPage = (typeof minPageLoaded !== 'undefined') ? minPageLoaded : 1;

                    if (currentPage !== 1) {
                        // Jump ke halaman 1 via loadGridData
                        loadGridData(
                            gridSelector,
                            apiUrl + menu,
                            accessToken,
                            grid.jqGrid('getGridParam', 'postData'),
                            1,
                            serverPageSize,
                            'jump',
                            'jump',
                            function () {
                                var newIds = $(activeGrid).getDataIDs();
                                if (newIds.length > 0) {
                                    grid.setSelection(newIds[0]);
                                    bDiv.scrollTop(0);
                                    lastScrollTop = 0;
                                }
                            }
                        );
                    } else {
                        // Sudah di halaman 1 — select baris pertama
                        if (gridIds.length > 0) {
                            grid.setSelection(gridIds[0]);
                            bDiv.scrollTop(0);
                            lastScrollTop = 0;
                        }
                    }
                }

                // =================================================================
                // KEY 35: END (Ke Halaman Terakhir)
                // =================================================================
                if (e.keyCode === 35) {
                    var realLastPage = (records > 0) ? Math.ceil(records / serverPageSize) : 1;
                    var currentPage = (typeof minPageLoaded !== 'undefined') ? minPageLoaded : 1;

                    if (currentPage !== realLastPage) {
                        var cleanUrl = menu;
                        if (cleanUrl.indexOf('?') !== -1) cleanUrl = cleanUrl.split('?')[0];

                        var cleanPostData = grid.jqGrid('getGridParam', 'postData');
                        delete cleanPostData.page;
                        delete cleanPostData.rows;

                        loadGridData(
                            gridSelector,
                            apiUrl + cleanUrl,
                            accessToken,
                            cleanPostData,
                            realLastPage,
                            serverPageSize,
                            'jump',
                            'jump',
                            function () {
                                var newIds = $(activeGrid).getDataIDs();
                                if (newIds.length > 0) {
                                    grid.setSelection(newIds[newIds.length - 1]);
                                    bDiv.scrollTop(bDiv[0].scrollHeight);
                                    lastScrollTop = bDiv[0].scrollHeight;
                                }
                            }
                        );
                    } else {
                        // Sudah di halaman terakhir — select baris terakhir
                        if (gridIds.length > 0) {
                            grid.setSelection(gridIds[gridIds.length - 1]);
                            bDiv.scrollTop(bDiv[0].scrollHeight);
                            lastScrollTop = bDiv[0].scrollHeight;
                        }
                    }
                }

                // =================================================================
                // KEY 38: ARROW UP
                // =================================================================
                if (e.keyCode === 38) {
                    if (currentIndex > 0) {
                        // Normal: pindah ke baris di atasnya
                        var prevRowId = gridIds[currentIndex - 1];
                        grid.setSelection(prevRowId);
                        scrollRowIntoGridView(grid.find('tr#' + prevRowId)[0]);

                    } else if (currentIndex === 0) {
                        // Mentok atas: prepend data halaman sebelumnya
                        // Gunakan direction='up', proses='page' agar TIDAK trigger auto-fill
                        var curPage = (typeof minPageLoaded !== 'undefined') ? minPageLoaded : 1;
                        if (curPage > 1) {
                            var prevPage = curPage - 1;
                            loadGridData(
                                gridSelector,
                                apiUrl + menu,
                                accessToken,
                                grid.jqGrid('getGridParam', 'postData'),
                                prevPage,
                                serverPageSize,
                                'up',    // direction: prepend data di atas
                                'page',  // proses=page: TIDAK trigger auto-fill
                                function () {
                                    // Setelah prepend, select baris terakhir dari halaman baru
                                    // (yaitu baris tepat di atas baris yang tadi mentok)
                                    var newIds = grid.getDataIDs();
                                    if (newIds.length > 0 && selectedRowId) {
                                        var newIdx = newIds.indexOf(selectedRowId);
                                        if (newIdx > 0) {
                                            grid.setSelection(newIds[newIdx - 1]);
                                            scrollRowIntoGridView(grid.find('tr#' + newIds[newIdx - 1])[0]);
                                        }
                                    }
                                }
                            );
                        }
                    }
                }

                // =================================================================
                // KEY 40: ARROW DOWN
                // =================================================================
                if (e.keyCode === 40) {
                    if (currentIndex >= 0 && currentIndex < gridIds.length - 1) {
                        // Normal: pindah ke baris di bawahnya
                        var nextRowId = gridIds[currentIndex + 1];
                        grid.setSelection(nextRowId);
                        scrollRowIntoGridView(grid.find('tr#' + nextRowId)[0]);

                    } else if (currentIndex >= gridIds.length - 1) {
                        // Mentok bawah: append data halaman berikutnya
                        // Gunakan direction='down', proses='page' agar TIDAK trigger auto-fill
                        var nextPage = (typeof maxPageLoaded !== 'undefined') ? maxPageLoaded + 1 : 2;
                        var maxPages = (typeof totalPages !== 'undefined' && totalPages > 0) ? totalPages : 999;
                        if (nextPage <= maxPages) {
                            var savedSelRowId = selectedRowId;
                            loadGridData(
                                gridSelector,
                                apiUrl + menu,
                                accessToken,
                                grid.jqGrid('getGridParam', 'postData'),
                                nextPage,
                                serverPageSize,
                                'down',  // direction: append data di bawah
                                'page',  // proses=page: TIDAK trigger auto-fill
                                function () {
                                    // Setelah append, select baris pertama dari halaman baru
                                    var newIds = grid.getDataIDs();
                                    if (newIds.length > 0 && savedSelRowId) {
                                        var oldIdx = newIds.indexOf(savedSelRowId);
                                        if (oldIdx >= 0 && oldIdx < newIds.length - 1) {
                                            grid.setSelection(newIds[oldIdx + 1]);
                                            scrollRowIntoGridView(grid.find('tr#' + newIds[oldIdx + 1])[0]);
                                        }
                                    }
                                }
                            );
                        }
                    }
                }

                // =================================================================
                // KEY 13: ENTER — Trigger double-click handler
                // =================================================================
                if (e.keyCode === 13) {
                    if (selectedRowId) {
                        var ondblClickRowHandler = grid.jqGrid("getGridParam", "ondblClickRow");
                        if (typeof ondblClickRowHandler === 'function') {
                            ondblClickRowHandler.call(grid[0], selectedRowId, currentIndex, 0, e);
                        }
                    }
                }
            }
        }
    });
}


/**
 * Move to closest input when using press enter
 */
function setFormBindKeys(form = null) {
    let element;
    let position;
    let inputs;

    if (form !== null) {
        inputs = form.find(
            "[name]:not(:hidden, [readonly], [disabled], .disabled), button:submit"
        );
    } else {
        inputs = $(document).find(
            "[name]:not(:hidden, [readonly], [disabled], .disabled), button:submit"
        );
    }

    $($(inputs.filter(":not(button)")[0])).focus();

    if (!$("#crudForm").attr("has-binded")) {
        inputs.each(function (i, el) {
            $(el).attr("data-input-index", i);
        });

        inputs.focus(function () {
            $(this).data("input-index");
        });

        inputs.keydown(function (e) {
            let operator;
            switch (e.keyCode) {
                case 38:
                    // if ($(this).parents('table').length > 0) {
                    // 	element = $(this).parents('tr').prev('tr').find('td').eq($(this).parent().index()).find('input')
                    // } else {
                    element = $(inputs[$(this).data("input-index") - 1]);
                    // }

                    break;
                case 13:
                    if (e.shiftKey) {
                        element = $(inputs[$(this).data("input-index") - 1]);
                    } else if (e.ctrlKey) {
                        $(this).closest("form").find("button:submit").click();
                    } else {
                        element = $(inputs[$(this).data("input-index") + 1]);

                        if (e.keyCode == 13 && $(this).is("button")) {
                            $(this).click();
                        }
                    }

                    break;
                case 40:
                    element = $(inputs[$(this).data("input-index") + 1]);

                    break;
                default:
                    return;
            }

            if (element !== undefined) {
                if (
                    element.is(":not(select, button)") &&
                    element.attr("type") !== "email" &&
                    element.attr("type") !== "time"
                ) {
                    position = element.val().length;
                    element[0].setSelectionRange(position, position);
                }

                element.hasClass("hasDatePicker")
                    ? $(".ui-datepicker").show()
                    : $(".ui-datepicker").hide();
                element.focus();
            }

            e.preventDefault();
        });

        form.attr("has-binded", true);
    }
}

function initResize(grid) {
    /* Check if scrollbar appears */
    $(window).height() < $(document).height()
        ? grid.setGridWidth($(window).width() - 15)
        : "";

    /* Resize grid while resizing window */
    $(window).resize(function () {
        grid.setGridWidth($(window).width() - 15);
    });
}

var delay = (function () {
    var timer = 0;
    return function (callback, ms) {
        clearTimeout(timer);
        timer = setTimeout(callback, ms);
    };
})();

function loadGlobalSearch(grid, lazyLoading = false, url = '') {
    let isTextLookup = $.jgrid.jqID(grid[0].id).toLowerCase().includes("lookup")
    let gridSettingBtn = `<i class="fa fa-cog"></i>`
    if (isTextLookup) {
        gridSettingBtn = ``
    }

    /* Append global search textfield */
    $("#t_" + $.jgrid.jqID(grid[0].id)).html(
        $(
            `
            <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between w-100 px-2 py-1">
                <form class="form-inline">
                    <div class="form-group w-100 px-2" id="titlesearch">
                        <label for="searchText" style="font-weight: normal !important;">Search : </label>
                        <input type="text" class="form-control form-control-sm global-search" id="${$.jgrid.jqID(grid[0].id)}_searchText" placeholder="Search" autocomplete="off">
                    </div>
                </form>
                <div class="px-2 d-flex align-items-center">
                    <div id="searchDetail_${$.jgrid.jqID(grid[0].id)}" class="px-2"></div>
                    <div id="infoContainer_${$.jgrid.jqID(grid[0].id)}" class="px-2"></div>
                    <div id="gridSetting_${$.jgrid.jqID(grid[0].id)}" class="px-2 gridSetting">${gridSettingBtn}</div>
                </div>
            </div>`
        )
    );

    /* Handle textfield on input */
    $(document).on(
        "input",
        `#${$.jgrid.jqID(grid[0].id)}_searchText`,
        function () {
            delay(function () {
                abortGridLastRequest(grid);
                if (grid.getGridParam().id == "jqGrid") {
                    $("#left-nav")
                        .find(`button:not(#add)`)
                        .attr("disabled", "disabled");
                }
                clearColumnSearch(grid);

                var postData = grid.jqGrid("getGridParam", "postData"),
                    colModel = grid.jqGrid("getGridParam", "colModel"),
                    rules = [],
                    searchText = $(
                        `#${$.jgrid.jqID(grid[0].id)}_searchText`
                    ).val(),
                    l = colModel.length,
                    i,
                    cm;
                if (addedRules) {
                    rules.push(addedRules);
                }
                for (i = 0; i < l; i++) {
                    cm = colModel[i];
                    if (
                        cm.search !== false &&
                        (cm.stype === undefined ||
                            cm.stype === "text" ||
                            cm.stype === "select")
                    ) {
                        rules.push({
                            field: cm.name,
                            op: "cn",
                            data: searchText.toUpperCase(),
                        });
                    }
                }
                postData.filters = JSON.stringify({
                    groupOp: "OR",
                    rules: rules,
                });

                grid.jqGrid("setGridParam", {
                    search: true,
                });
                if (lazyLoading) {
                    grid.jqGrid('clearGridData');
                    loadGridData(gridId, `${apiUrl}${url}`, accessToken, grid.jqGrid('getGridParam', 'postData'), 1, 50, 'down', 'reload', function () {
                        executePostLoadTasks(gridId)
                    });
                } else {
                    grid.trigger("reloadGrid", [
                        {
                            page: 1,
                            current: true,
                        },
                    ]);
                }
                return false;
            }, 500);
        }
    );

}


function loadFilterChecked(grid, typeGrid = 'pageGrid', url = '') {

    if ($.jgrid.jqID(grid[0].id) == 'jqGrid') {
        $(`#infoContainer_${$.jgrid.jqID(grid[0].id)} `).html(
            $(`
                <div class="d-flex align-items-center" >
                    <select id="filterSelection" class="form-control form-control-sm ${typeGrid}" style="width: 150px;" data-url="${url}">
                        <option value="all">All Rows</option>
                        <option value="checked">Checked Rows</option>
                        <option value="unchecked">Unchecked Rows</option>
                    </select>
                </div>
            `)
        );
        $('#filterSelection').select2({
            minimumResultsForSearch: -1, // Hides the search box inside the dropdown
            theme: 'bootstrap4' // Use this if you are using the Select2 Bootstrap theme
        });
    }
}

//   dipakai kalau mau buat filter checked rows diletak diatas checkbox
$(document).on('change', '#filterSelection', function () {
    const filterValue = $(this).val();
    const grid = $("#jqGrid");
    const typeGridLazy = $(this).hasClass('lazyLoading')
    grid.bind("jqGridBeforeRequest", function () {
        // Show a loading state on the select if you want
        $('#filterSelection').prop('disabled', true);
    });
    if (typeGridLazy) {

        if (typeof cachedData !== 'undefined') cachedData = {};
        if (typeof minPageLoaded !== 'undefined') minPageLoaded = 1;
        if (typeof maxPageLoaded !== 'undefined') maxPageLoaded = 1;
        if (typeof lastScrollTop !== 'undefined') lastScrollTop = 0;
        if (typeof currentFilters !== 'undefined') currentFilters = "";

        let rawUrl = $('#filterSelection').data('url');
        if (rawUrl.indexOf('?') !== -1) {
            rawUrl = rawUrl.split('?')[0];
        }

        // 3. UPDATE POST DATA (BERSIHKAN PAGE NYANGKUT)
        var currentPostData = grid.jqGrid('getGridParam', 'postData');

        // Penting: Hapus page/rows agar tidak menimpa logic loadGridData
        delete currentPostData.page;
        delete currentPostData.rows;

        grid.jqGrid('setGridParam', {
            postData: {
                ...currentPostData,
                filterChecked: filterValue,
                filters: '', // Reset filter pencarian
                proses: 'reload',
                selectedIds: (typeof selectedRowsIndex != 'undefined') ? JSON.stringify(selectedRowsIndex) : JSON.stringify(selectedRows)
            }
        });

        console.log("Switching Filter. Clean URL:", rawUrl);

        // 4. LOAD GRID DENGAN URL BERSIH
        loadGridData(
            '#jqGrid',
            `${apiUrl}${rawUrl}`, // Gunakan URL bersih
            accessToken,
            grid.jqGrid('getGridParam', 'postData'),
            1,
            50,
            'down',
            'reload'
        );

    } else {
        grid.jqGrid('setGridParam', {
            postData: {
                filterChecked: filterValue,
                filters: '',
                proses: 'page',
                selectedIds: (typeof selectedRowsIndex != 'undefined') ? JSON.stringify(selectedRowsIndex) : JSON.stringify(selectedRows)
            },
            page: 1 // Go back to page 1 to show results
        }).trigger("reloadGrid");
    }

    grid.bind("jqGridLoadComplete", function () {
        // Re-enable when data is finished
        $('#filterSelection').prop('disabled', false);
    });
    grid[0].clearToolbar();

    $(`#gview_${grid.getGridParam("id")}`)
        .find('select[id*="gs_"]')
        .val("")
        .trigger("change.select2");
    $(`#${$.jgrid.jqID(grid[0].id)}_searchText`).val('')
});

// CLEAR  SELECT2 FILTER
$(document).on('click', '.ui-search-clear', function () {
    const $tr = $(this).closest('tr');
    if ($tr.length > 0) {
        $tr.find('select').val("").trigger("change.select2");
    }
});


function loadDetailSearchModal(grid) {

    if ($.jgrid.jqID(grid[0].id) == 'jqGrid') {
        $(`#searchDetail_${$.jgrid.jqID(grid[0].id)} `).html(
            $(`
                <div class="d-flex align-items-center" >
                    <button class="btn btn-sm btn-outline-primary"
                            title="Cari Detail"
                            data-toggle="modal"
                            data-target="#searchDetailModal">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            `)
        );
    }
}


function initHeaderFilter() {
    const gridId = "jqGrid";
    const headerCell = $(`#${gridId}_`); // Matches the 'name' in colModel

    // Inject the Select2 HTML
    headerCell.find('#headerSelectContainer').html(`
        <select id="headerFilterSelection" class="select2">
            <option value="all">ALL</option>
            <option value="checked">CHECKED ROWS</option>
            <option value="unchecked">UNCHECKED ROWS</option>
        </select>
    `);
    let getColModel = $(`#${gridId}`).jqGrid('getGridParam', 'colModel');

    // Find the index where the label contains your specific div ID
    const colIndex = getColModel.findIndex(col =>
        col.label && col.label.includes('id="headerSelectContainer"')
    );

    // Initialize Select2
    $('#headerFilterSelection').select2({
        minimumResultsForSearch: -1,
        theme: 'bootstrap4',
        width: '100%'
    }).on('change', function () {
        const val = $(this).val();

        // Reload the grid with the value
        $("#jqGrid").jqGrid('setGridParam', {
            postData: {
                filterChecked: val,
                filters: '',
                proses: 'page',
                selectedIds: (typeof selectedRowsIndex != 'undefined') ? JSON.stringify(selectedRowsIndex) : JSON.stringify(selectedRows)
            },
            page: 1 // Go back to page 1 to show results
        }).trigger("reloadGrid");

        $("#jqGrid")[0].clearToolbar();
    }).on('select2:open', function () {
        // 1. Expand column width to 120px when clicked
        $(`#jqGrid_`).css('width', '120px');

        if (colIndex !== -1) {
            const nthChild = colIndex + 1;
            $(`#jqGrid tr td:nth-child(${nthChild})`).css('width', '120px');
        }
    })
        .on('select2:close', function () {
            // 2. Shrink back to 40px when closed
            $(`#jqGrid_`).css('width', '40px');
            if (colIndex !== -1) {
                const nthChild = colIndex + 1;
                $(`#jqGrid tr td:nth-child(${nthChild})`).css('width', '40px');
            }
        });

    // Prevent sorting when clicking the dropdown
    headerCell.on('click', function (e) {
        e.stopPropagation();
    });
}

function additionalRulesGlobalSearch(params) {
    if (JSON.parse(params).rules[0]) {
        addedRules = JSON.parse(params).rules[0];
    }
}

function clearColumnSearch(grid) {
    $(`#gview_${grid.getGridParam("id")}`)
        .find('input[id*="gs_"]')
        .val("");
    $(`#gview_${grid.getGridParam("id")}`)
        .find('select[id*="gs_"]')
        .val("")
        .trigger("change.select2");
    $(`#resetdatafilter_${grid.getGridParam("id")}`).removeClass("active");
}

function clearGlobalSearch(grid) {
    $(`#${grid.getGridParam("id")}_searchText`).val("");
}

function loadClearFilter(grid, lazyLoading = false, url = '') {
    /* Append Button */
    $("#gsh_" + $.jgrid.jqID(grid[0].id) + "_rn").html(
        $(
            `<div id='resetfilter' class='reset'><span id="resetdatafilter_${grid.getGridParam(
                "id"
            )}" class='btn btn-default'> X </span></div>`
        )
    );

    /* Handle button on click */
    $(`#resetdatafilter_${grid.getGridParam("id")}`).click(function () {
        highlightSearch = "";

        clearColumnSearch(grid);
        clearGlobalSearch(grid);

        if (lazyLoading) {
            grid.jqGrid('setGridParam', {
                postData: {
                    filters: "",
                }
            })
            loadGridData(gridId, `${apiUrl}${url}`, accessToken, $(gridId).jqGrid('getGridParam', 'postData'), 1, 50, 'down', 'reload', function () {
                executePostLoadTasks(gridId)
            });
        } else {
            grid.jqGrid("setGridParam", {
                search: false,
                postData: {
                    filters: "",
                },
            }).trigger("reloadGrid");
        }
    });
}

function startTime() {
    setInterval(() => {
        let date = new Date();

        let day = date.toLocaleString("id", {
            dateStyle: "medium",
        });

        let time = date.toLocaleString("id", {
            timeStyle: "medium",
        });

        $(".datetime-place .date-place").html(day);
        $(".datetime-place .time-place").html(time.replaceAll(".", ":"));
    }, 1000);
}
function isLeapYear(year) {
    return (year % 4 === 0 && year % 100 !== 0) || (year % 400 === 0);
}
function initDatepicker(classDatepicker = "datepicker") {
    let element = $(document).find("." + classDatepicker);

    if (!offDays) {
        offDays = getOffDays();
    }

    if (!element.parent().hasClass("input-group")) {
        element.wrap(`
				<div class="input-group">
				</div>
			`);
    }

    element
        .datepicker({
            dateFormat: "dd-mm-yy",
            changeYear: true,
            changeMonth: true,
            assumeNearbyYear: true,
            showOn: "button",
            beforeShow: function (element, instance) {
                let calendar = instance.dpDiv;

                $(element).css({
                    position: "relative",
                });
                calendar.removeClass("no-date");
                let isInModal = $(element).closest(".modal").length > 0;
                if (isInModal) {
                    $(".ui-datepicker").insertAfter(element);
                }

                // Dirty hack, but we can't do anything without it (for now, in jQuery UI 1.8.20)
                setTimeout(function () {
                    calendar.position({
                        my: "left top",
                        at: "left bottom",
                        collision: "none",
                        of: element,
                    });
                }, 1);
            },
            beforeShowDay: function (date) {
                let y = date.getFullYear().toString(); // get full year
                let m = (date.getMonth() + 1).toString(); // get month.
                let d = date.getDate().toString(); // get Day

                if (m.length == 1) {
                    m = "0" + m;
                } // append zero(0) if single digit
                if (d.length == 1) {
                    d = "0" + d;
                } // append zero(0) if single digit
                let currDate = y + "-" + m + "-" + d;

                let offDay = offDays.find((offDay) => offDay.date == currDate);
                let isSunday = date.getDay() === 0;
                let isSat = date.getDay() === 6;

                if (offDay || isSunday || isSat) {
                    if (isSunday) {
                        desc = "sunday";
                        styleClass = "datepicker-offday";
                    } else if (isSat) {
                        desc = "Saturday";
                        styleClass = "datepicker-saturday";
                    } else {
                        desc = offDay.description;
                        styleClass = "datepicker-offday";
                    }
                    return [true, styleClass, desc];
                } else {
                    return [true];
                }
            },
        })
        .inputmask({
            inputFormat: "dd-mm-yyyy",
            alias: "datetime",
        })
        .focusout(function (e) {
            let val = $(this).val();
            if (val.match("[a-zA-Z]") == null) {
                if (val.length == 8) {
                    $(this)
                        .inputmask({
                            inputFormat: "dd-mm-yyyy",
                        })
                        .val([val.slice(0, 6), "20", val.slice(6)].join(""));
                }
            } else {
                $(this).focus();
            }
        });

    element
        .siblings(".ui-datepicker-trigger")
        .wrap(
            `
			<div class="input-group-append">
			</div>
		`
        )
        .addClass("btn btn-easyui text-easyui-dark").html(`
			<i class="fa fa-calendar-alt"></i>
		`);

    element.on("input", function (event) {
        element.datepicker("widget").hide();
    });
    element.on("keydown", function (event) {
        if (event.keyCode === 115) {
            if (element.datepicker("widget").not(":visible")) {
                element.datepicker("show");
            }
        }
    });
    element.on("keypress", function (e) {
        const key = String.fromCharCode(e.which);
        const val = $(this).val();
        if (val.length >= 9 && val.startsWith("29-02-")) {
            const baseYearPart = val.slice(6, 9); // ambil 3 digit awal tahun
            if (!/^\d{3}$/.test(baseYearPart)) return; // abaikan kalau belum angka semua

            const fullYear = parseInt(baseYearPart + key, 10);

            if (!isLeapYear(fullYear)) {
                const newVal = val.slice(0, 9);
                $(this).val(newVal);
            }
        }
    });

    // Di HP/tablet: tap pada input langsung membuka kalender tanpa memunculkan
    // keyboard virtual. preventDefault pada mousedown mencegah fokus (pemicu
    // keyboard), lalu kalender dibuka manual lewat event click karena showOn
    // "button" tidak membuka kalender dari input. inputmode="none" sebagai
    // lapisan kedua bila fokus masuk lewat jalur lain (mis. programatik).
    if (detectDeviceType() !== "desktop") {
        element.attr("inputmode", "none");
        element.on("mousedown", function (e) {
            e.preventDefault();
        });
        element.on("click", function () {
            $(this).datepicker("show");
        });
    }
}

function initMonthpicker(classDatepicker = "monthpicker") {
    let element = $(document).find("." + classDatepicker);

    if (!element.parent().hasClass("input-group")) {
        element.wrap(`
				<div class="input-group">
				</div>
			`);
    }

    element
        .datepicker({
            dateFormat: "mm-yy",
            changeYear: true,
            changeMonth: true,
            assumeNearbyYear: true,
            // showButtonPanel: true,
            showOn: "button",
            beforeShow: function (element, instance) {
                let calendar = instance.dpDiv;

                $(element).css({
                    position: "relative",
                });
                calendar.addClass("no-date");

                // Dirty hack, but we can't do anything without it (for now, in jQuery UI 1.8.20)
                setTimeout(function () {
                    calendar.position({
                        my: "left top",
                        at: "left bottom",
                        collision: "none",
                        of: element,
                    });
                }, 1);

                // Ambil tanggal saat ini dari input
                var currentInputValue = $(element).val(); // Formatnya 'mm-yy'
                var currentMonthYear = currentInputValue.split("-");

                if (currentMonthYear.length == 2) {
                    var currentMonth = parseInt(currentMonthYear[0]) - 1;
                    var currentYear = parseInt(currentMonthYear[1]);

                    $(element)
                        .datepicker(
                            "option",
                            "defaultDate",
                            new Date(currentYear, currentMonth, 1)
                        )
                        .siblings(".ui-datepicker-trigger")
                        .wrap('<div class="input-group-append"></div>')
                        .addClass("btn btn-easyui text-easyui-dark")
                        .html('<i class="fa fa-calendar-alt"></i>');

                    $(element).datepicker(
                        "setDate",
                        new Date(currentYear, currentMonth, 1)
                    );
                }

                setTimeout(function () {
                    // Function to attach the change event
                    function attachMonthYearChangeEvents() {
                        $(".ui-datepicker-month, .ui-datepicker-year")
                            .off("change")
                            .on("change", function () {
                                var selectedMonth = $(
                                    ".ui-datepicker-month"
                                ).val();
                                var selectedYear = $(
                                    ".ui-datepicker-year"
                                ).val();

                                var newDate = new Date(
                                    selectedYear,
                                    selectedMonth,
                                    1
                                );

                                // Set tanggal yang baru ke dalam datepicker
                                $(element).datepicker("setDate", newDate);

                                // Re-attach the events after the change
                                attachMonthYearChangeEvents();
                            });
                    }

                    // Initial attachment of events
                    attachMonthYearChangeEvents();
                }, 1);
            },
            onClose: function (dateText, inst) {
                // $(this).datepicker(
                //     "setDate",
                //     new Date(inst.selectedYear, inst.selectedMonth, 1)
                // );
                var selectedMonth = inst.selectedMonth; // bulan yang dipilih (0-11)
                var selectedYear = inst.selectedYear; // tahun yang dipilih

                // Set tanggal yang baru berdasarkan bulan dan tahun yang dipilih
                var newDate = new Date(selectedYear, selectedMonth, 1);

                // Set tanggal yang baru ke dalam input
                $(this).datepicker("setDate", newDate);
            },
        })

        .inputmask({
            inputFormat: "mm-yyyy",
            alias: "datetime",
        })
        .focusout(function (e) {
            let val = $(this).val();
            if (val.match("[a-zA-Z]") == null) {
                if (val.length == 8) {
                    $(this)
                        .inputmask({
                            inputFormat: "mm-yyyy",
                        })
                        .val([val.slice(0, 6), "20", val.slice(6)].join(""));
                }
            } else {
                $(this).focus();
            }
        });

    element
        .siblings(".ui-datepicker-trigger")
        .wrap(
            `
			<div class="input-group-append">
			</div>
		`
        )
        .addClass("btn btn-easyui text-easyui-dark").html(`
			<i class="fa fa-calendar-alt"></i>
		`);

    element.on("keydown", function (event) {
        if (event.keyCode === 115) {
            if (element.datepicker("widget").not(":visible")) {
                element.datepicker("show");
            }
        }
    });
}

function initMonthpicker(classDatepicker = "monthpicker") {
    let element = $(document).find("." + classDatepicker);

    if (!element.parent().hasClass("input-group")) {
        element.wrap(`
				<div class="input-group">
				</div>
			`);
    }

    element.MonthPicker({ MonthFormat: "mm-yy" }).inputmask({
        inputFormat: "mm-yyyy",
        alias: "datetime",
    });

    // Di HP/tablet: tap pada input langsung membuka dialog tanpa keyboard.
    // MonthPicker.Open() membatalkan diri jika event-nya sudah di-preventDefault,
    // jadi jangan mengandalkan binding click internal plugin — batalkan seluruh
    // rangkaian event sintetis di touchend (fokus, keyboard, click) lalu buka
    // dialognya secara eksplisit. inputmode="none" tetap dipasang karena Open()
    // memfokuskan input secara programatik.
    if (detectDeviceType() !== "desktop") {
        let touchMoved = false;
        element.attr("inputmode", "none");
        element.on("touchstart", function () {
            touchMoved = false;
        });
        element.on("touchmove", function () {
            touchMoved = true;
        });
        element.on("touchend", function (e) {
            if (touchMoved) return; // gesture scroll, bukan tap
            e.preventDefault();
            $(this).MonthPicker("Open");
        });
        element.on("mousedown", function (e) {
            e.preventDefault();
        });
    }

    // Style the span to look like a button
    let spanButton = element.siblings(".month-picker-open-button");

    if (spanButton.length) {
        spanButton
            .wrap(`<div class="input-group-append"></div>`)
            .removeClass("ui-button-icon-only")
            .removeClass("ui-button")
            .addClass("ui-datepicker-trigger btn btn-easyui text-easyui-dark")
            .html(`<i class="fa fa-calendar-alt"></i>`);

        spanButton
            .attr({
                role: "button",
                "aria-label": "Open Month Chooser",
            })
            .css({
                height: "100%",
                width: "35px",
                display: "inline-flex",
                "align-items": "center",
                "justify-content": "center",
                cursor: "pointer",
                "border-radius": "0",
                padding: "0.5rem",
                "box-sizing": "border-box",
            });
    }

    element.on("input", function (event) {
        element.MonthPicker("Close");
    });

    // Validasi format pada blur
    element.on("blur", function () {
        let value = $(this).val();
        let regex = /^(0[1-9]|1[0-2])-\d{4}$/;

        $(this).removeClass("is-invalid");
        $(this).siblings(".invalid-feedback").remove();

        if (!regex.test(value)) {
            let error = "Format salah! Harus dalam format mm-yyyy.";

            $(this).addClass("is-invalid");

            $(`
                <div class="invalid-feedback">
                ${error}
                </div>
            `).appendTo($(this).parent());

            $(this).focus(); // Kembali ke input jika format salah
        }
    });
}

function initYearpicker(classYearpicker = "yearpicker") {
    let element = $("." + classYearpicker);

    element.each(function () {
        let $input = $(this);

        // 1. Bungkus input ke dalam input-group jika belum ada
        if (!$input.parent().hasClass("input-group")) {
            $input.wrap(`<div class="input-group"></div>`);
        }

        // 2. Tambahkan tombol ikon kalender di sebelah kanan input
        let $btnContainer = $(`
            <div class="input-group-append">
                <button class="btn btn-easyui text-easyui-dark yearpicker-trigger" type="button">
                    <i class="fa fa-calendar-alt"></i>
                </button>
            </div>
        `);

        // Cek agar tidak terjadi duplikasi tombol saat inisialisasi ulang
        if ($input.next('.input-group-append').length === 0) {
            $input.after($btnContainer);
        }

        // 3. Inisialisasi plugin YearPicker kustom kita
        $input.YearPicker({
            onSelect: function (year) {
                $input.val(year).trigger('change');
            }
        });

        // 4. Hubungkan klik tombol ke trigger input (agar popup muncul)
        $input.next('.input-group-append').find('.yearpicker-trigger').on('click', function () {
            $input.trigger('click'); // Memicu event click pada input yang sudah di-handle oleh YearPicker
        });

        // 5. Tambahkan inputmask agar format tetap 4 digit
        $input.inputmask({
            mask: "9999",
            placeholder: "yyyy",
            showMaskOnHover: false,
            showMaskOnFocus: true
        });

        // Di HP/tablet: tap pada input langsung membuka popup tanpa keyboard.
        // Batalkan seluruh rangkaian event sintetis di touchend (fokus, keyboard,
        // click) lalu jalankan handler click milik YearPicker secara eksplisit
        // lewat trigger — pola yang sama dengan initDatepicker/initMonthpicker.
        if (detectDeviceType() !== "desktop") {
            let touchMoved = false;
            $input.attr("inputmode", "none");
            $input.on("touchstart", function () {
                touchMoved = false;
            });
            $input.on("touchmove", function () {
                touchMoved = true;
            });
            $input.on("touchend", function (e) {
                if (touchMoved) return; // gesture scroll, bukan tap
                e.preventDefault();
                $input.trigger("click"); // buka popup via handler click YearPicker
            });
            $input.on("mousedown", function (e) {
                e.preventDefault();
            });
        }
    });
}


// function initMonthpicker(classDatepicker = "monthpicker") {
//     let element = $(document).find("." + classDatepicker);

//     if (!offDays) {
//         offDays = getOffDays();
//     }

//     if (!element.parent().hasClass("input-group")) {
//         element.wrap(`
// 				<div class="input-group">
// 				</div>
// 			`);
//     }

//     element
//         .datepicker({
//             dateFormat: "mm-yy",
//             changeYear: true,
//             changeMonth: true,
//             assumeNearbyYear: true,
//             showOn: "button",
//             beforeShow: function (element, instance) {
//                 let calendar = instance.dpDiv;

//                  $(element).css({
//                     position: "relative",
//                 });
//                 calendar.addClass("no-date");

//                 // Dirty hack, but we can't do anything without it (for now, in jQuery UI 1.8.20)
//                 setTimeout(function () {
//                     calendar.position({
//                         my: "left top",
//                         at: "left bottom",
//                         collision: "none",
//                         of: element,
//                     });
//                 }, 1);

//                 // Ambil tanggal saat ini dari input
//                 var currentInputValue = $(element).val(); // Formatnya 'mm-yy'
//                 var currentMonthYear = currentInputValue.split("-");

//                 if (currentMonthYear.length == 2) {
//                     var currentMonth = parseInt(currentMonthYear[0]) - 1;
//                     var currentYear = parseInt(currentMonthYear[1]);

//                     $(element)
//                         .datepicker(
//                             "option",
//                             "defaultDate",
//                             new Date(currentYear, currentMonth, 1)
//                         )
//                         .siblings(".ui-datepicker-trigger")
//                         .wrap('<div class="input-group-append"></div>')
//                         .addClass("btn btn-easyui text-easyui-dark")
//                         .html('<i class="fa fa-calendar-alt"></i>');

//                     $(element).datepicker(
//                         "setDate",
//                         new Date(currentYear, currentMonth, 1)
//                     );
//                 }

//                 setTimeout(function () {
//                     // Function to attach the change event
//                     function attachMonthYearChangeEvents() {
//                         $(".ui-datepicker-month, .ui-datepicker-year")
//                             .off("change")
//                             .on("change", function () {
//                                 var selectedMonth = $(".ui-datepicker-month").val();
//                                 var selectedYear = $(".ui-datepicker-year").val();

//                                 var newDate = new Date(selectedYear, selectedMonth, 1);

//                                 // Set tanggal yang baru ke dalam datepicker
//                                 $(element).datepicker("setDate", newDate);

//                                 // Re-attach the events after the change
//                                 attachMonthYearChangeEvents();
//                             });
//                     }

//                     // Initial attachment of events
//                     attachMonthYearChangeEvents();
//                 }, 1);
//             },
//             // onClose: function (dateText, inst) {
//             //     // $(this).datepicker(
//             //     //     "setDate",
//             //     //     new Date(inst.selectedYear, inst.selectedMonth, 1)
//             //     // );
//             //     var selectedMonth = inst.selectedMonth; // bulan yang dipilih (0-11)
//             //     var selectedYear = inst.selectedYear; // tahun yang dipilih

//             //     // Set tanggal yang baru berdasarkan bulan dan tahun yang dipilih
//             //     var newDate = new Date(selectedYear, selectedMonth, 1);

//             //     // Set tanggal yang baru ke dalam input
//             //     $(this).datepicker("setDate", newDate);
//             // },
//         })
//         .inputmask({
//             inputFormat: "mm-yyyy",
//             alias: "datetime",
//         })
//         .focusout(function (e) {
//             let val = $(this).val();
//             if (val.match("[a-zA-Z]") == null) {
//                 if (val.length == 8) {
//                     $(this)
//                         .inputmask({
//                             inputFormat: "mm-yyyy",
//                         })
//                         .val([val.slice(0, 6), "20", val.slice(6)].join(""));
//                 }
//             } else {
//                 $(this).focus();
//             }
//         });

//     element
//         .siblings(".ui-datepicker-trigger")
//         .wrap(
//             `
// 			<div class="input-group-append">
// 			</div>
// 		`
//         )
//         .addClass("btn btn-easyui text-easyui-dark").html(`
// 			<i class="fa fa-calendar-alt"></i>
// 		`);

//     element.on("input", function (event) {
//         element.datepicker("widget").hide();
//     });
//     element.on("keydown", function (event) {
//         if (event.keyCode === 115) {
//             if (element.datepicker("widget").not(":visible")) {
//                 element.datepicker("show");
//             }
//         }
//     });
// }

function getOffDays() {
    let offDays = [];

    $.ajax({
        url: `${appUrl}harilibur`,
        method: "GET",
        dataType: "JSON",
        async: false,
        cache: true,
        success: (response) => {
            let convertedResponse = [];

            if (response && response.data) {
                response.data.forEach((row) => {
                    convertedResponse.push({
                        date: row.date,
                        description: row.description,
                    });
                });
            }

            offDays = convertedResponse;
        },
        error: () => {
            offDays = [];
        }
    });

    return offDays;
}

function destroyDatepicker() {
    let datepickerElements = $(document).find(".datepicker");

    $.each(datepickerElements, (index, datepickerElement) => {
        $(datepickerElement).datepicker("destroy");
    });
}

$(document).on("input", ".numbernoseparate", function () {
    this.value = this.value.replace(/\D/g, "");
});

/* Select2: Autofocus search input on open */
function initSelect2(elements = null, isInsideModal = true) {
    if (elements === null) {
        $(document)
            .find("select")
            .each((index, element) => {
                let option = {
                    width: "100%",
                    theme: "bootstrap4",
                };

                if (isInsideModal && $(element).parents(".modal-content").length > 0) {
                    option.dropdownParent = $(element).parents(".modal-content");
                }

                $(element)
                    .select2(option)
                    .on("select2:open", function (e) {
                        document
                            .querySelector(".select2-search__field")
                            .focus();
                    });
            });
    } else {
        $.each(elements, (index, element) => {
            let option = {
                width: "100%",
                theme: "bootstrap4",
                dropdownParent: isInsideModal
                    ? $(element).parents(".modal-content")
                    : "",
            };

            $(element)
                .select2(option)
                .on("select2:open", function (e) {
                    document.querySelector(".select2-search__field").focus();
                });
        });
    }
}

function destroySelect2() {
    let select2Elements = $(document).find("select");

    $.each(select2Elements, (index, select2Element) => {
        $(select2Element).select2("destroy");
    });
}

function showSuccessDialog(statusText = "", message = "") {
    $("#dialog-success-message").find("p").remove();
    $("#dialog-success-message").append(
        `<p> ${statusText} </p><p> ${message} </p>`
    );
    $("#dialog-success-message").dialog({
        modal: true,
        buttons: [
            {
                text: "Ok",
                click: function () {
                    $(this).dialog("close");
                },
            },
        ],
    });
}

// function showDialog(statusText="", message="") {
// 	$("#dialog-message").html(`
// 		<span class="fa fa-exclamation-triangle" aria-hidden="true" style="font-size:25px;"></span>
// 	`)
// 	$("#dialog-message").append(
// 		`<p class="text-dark"> ${statusText} </p> ${message}`
// 	);
// 	$("#dialog-message").dialog({
// 		modal: true,
// 		buttons: [
// 			{
// 				text: "Ok",
// 				click: function () {
// 					$(this).dialog("close");
// 				},
// 			},
// 		]
// 	});
// 	$(".ui-dialog-titlebar-close").find("p").remove();
// }

function showDialog(response, originalError, maxWIdth = "600px", callback = null, urlDestination = null) {
    $("#dialog-message").html(`
		<span class="fa fa-exclamation-triangle" aria-hidden="true" style="font-size:25px;"></span>
	`);
    $("#dialog-warning-message").html(`
		<span class="fa fa-exclamation-triangle" aria-hidden="true" style="font-size:25px;"></span>
	`);
    if ($.type(response) == "undefined") {
        response = originalError
        console.error(response);
    } else {
        if ($.type(response) === "object") {
            if ("file" in response) {
                $("#dialog-message").append(
                    // `<p class="text-dark"> ${statusText} </p> ${message}`
                    `<p>file: ${response.file}</p>` +
                    `<p>line : ${response.line}</p>` +
                    `<p>message : ${response.message}</p>`
                );

                $("#dialog-message").dialog({
                    modal: true,
                    width: "auto", // Automatically adjust width
                    height: "auto",
                    resizable: false,
                    buttons: [
                        {
                            text: "Ok",
                            click: function () {
                                $(this).dialog("close");
                            },
                        },
                    ],
                    open: function () {
                        // Adjust the dialog size after it is opened
                        $(this).css({
                            "min-width": "300px",
                            "max-width": maxWIdth, // Set your desired maximum width here
                        });
                        $(this).dialog("option", "position", {
                            my: "center",
                            at: "center",
                            of: window,
                        });
                    },
                    close: function () {
                        $(".modal-loader").addClass("d-none");
                        if (callback) callback(); // Panggil callback setelah dialog ditutup
                    }
                });
                $(".ui-dialog-titlebar-close").find("p").remove();
            } else {
                $(`#dialog-${response.statuspesan}-message`).append(
                    `<p class="text-dark">${response.message}</p>`
                );

                $(`#dialog-${response.statuspesan}-message`).dialog({
                    modal: true,
                    width: "auto", // Automatically adjust width
                    height: "auto",
                    resizable: false,
                    buttons: [
                        {
                            text: "Ok",
                            click: function () {
                                $(this).dialog("close");
                                $(`#dialog-${response.statuspesan}-message`)
                                    .find("p")
                                    .remove();
                            },
                        },
                    ],
                    open: function () {
                        // Adjust the dialog size after it is opened
                        $(this).css({
                            "min-width": "300px",
                            "max-width": maxWIdth, // Set your desired maximum width here
                        });
                        $(this).dialog("option", "position", {
                            my: "center",
                            at: "center",
                            of: window,
                        });
                    },
                    close: function () {
                        $(".modal-loader").addClass("d-none");
                        if (callback) callback(); // Panggil callback setelah dialog ditutup
                    }
                });

                $(".ui-dialog-titlebar-close").find("p").remove();
            }
        } else {
            $("#dialog-warning-message").append(
                `<p class="text-dark">${response}</p>`
            );

            $("#dialog-warning-message").dialog({
                modal: true,
                width: "auto", // Automatically adjust width
                height: "auto",
                resizable: false,
                buttons: [
                    {
                        text: "Ok",
                        click: function () {
                            $(this).dialog("close");
                            $(`#dialog-warning-message`).find("p").remove();
                            if (urlDestination != null) {
                                window.location.href = urlDestination
                            }
                        },
                    },
                ],
                open: function () {
                    // Adjust the dialog size after it is opened
                    $(this).css({
                        "min-width": "300px",
                        "max-width": maxWIdth, // Set your desired maximum width here
                    });
                    $(this).dialog("option", "position", {
                        my: "center",
                        at: "center",
                        of: window,
                    });
                },
                close: function () {
                    $(".modal-loader").addClass("d-none");
                    if (callback) callback(); // Panggil callback setelah dialog ditutup
                }
            });
            $(".ui-dialog-titlebar-close").find("p").remove();
        }
    }
}

function showConfirm(statusText = "", message = "", urlDestination = "") {
    var def = $.Deferred();
    $("#dialog-confirm").find("p").remove();
    $("#dialog-confirm").append(`<p> ${statusText} </p><p> ${message} </p>`);
    $("#dialog-confirm").dialog({
        modal: true,
        open: function () {
            // console.log($(this));
        },
        buttons: [
            {
                text: "Ok",
                open: function () {
                    $(this).addClass("btn btn-success");
                },
                click: function () {
                    $(this).dialog("close");
                    if (urlDestination != "") {
                        processResult(true, urlDestination);
                    }
                    def.resolve();
                },
            },
            {
                text: "Cancel",
                open: function () {
                    $(this).addClass("btn btn-danger");
                },
                click: function () {
                    $(this).dialog("close");
                    processResult(false);
                    def.reject();
                },
            },
        ],
    });
    return def.promise();
}

function showDialogForce(response) {
    $("#dialog-force-message").html(`
		<span class="fa fa-exclamation-triangle" aria-hidden="true" style="font-size:25px;"></span>
	`);
    $("#dialog-force-message").append(`<p class="text-dark">${response}</p>`);

    $("#dialog-force-message").dialog({
        modal: true,
        buttons: [
            {
                text: "Ok",
                click: function () {
                    $(this).dialog("close");
                    $(`#dialog-force-message`).find("p").remove();
                },
            },
        ],
    });
    $(".ui-dialog-titlebar-close").find("p").remove();
}
function showConfirmForce(message = "", Id = "") {
    // var def = $.Deferred();
    $("#dialog-confirm-force").find("p").remove();
    $("#dialog-confirm-force").append(`<p> ${message} </p>`);
    // $("#dialog-confirm-force").dialog({
    // 	modal: true,
    // 	open: function () {
    // 		// console.log($(this));
    // 	},
    // 	buttons: [
    // 		{
    // 			text: "Force Edit",
    // 			open: function () {
    // 				$(this).addClass("btn btn-success");
    // 			},
    // 			click: function () {
    // 				$(this).dialog("close");
    // 				if(urlDestination != ""){
    // 					processResult(true, urlDestination);
    // 				}
    // 				def.resolve()
    // 			},
    // 		},
    // 		{
    // 			text: "Cancel",
    // 			open: function () {
    // 				$(this).addClass("btn btn-danger");
    // 			},
    // 			click: function () {
    // 				$(this).dialog("close");
    // 				processResult(false);
    // 				def.reject()
    // 			},
    // 		},
    // 	],
    // });
    $("#dialog-confirm-force").dialog({
        modal: true,
        buttons: [
            {
                id: "approval-kacab-force-edit",
                text: "approval",
                click: function () {
                    $(this).dialog("close");
                    console.log(Id);
                    approveKacab(Id);
                },
            },
            {
                id: "Cancel",
                text: "Cancel",
                click: function () {
                    $(this).dialog("close");
                },
            },
        ],
    });
    // return def.promise();
}

$(document).ready(function () {
    $("#sidebarButton").click(function () {
        setTimeout(() => {
            $(document).trigger("sidebar:toggle");
        }, 0);

        $(".nav-treeview").each(function (i, el) {
            $(el).removeAttr("style");
        });
    });

    var url = window.location.href;

    /** add active class and stay opened when selected */
    var url = window.location;

    // for sidebar menu entirely but not cover treeview
    $("ul.sidebar-menu a")
        .filter(function () {
            return this.href == url;
        })
        .parent()
        .addClass("active");

    // for treeview
    $("ul.treeview-menu a")
        .filter(function () {
            return this.href == url;
        })
        .parentsUntil(".sidebar-menu > .treeview-menu")
        .addClass("active");
});

// $("#search").keyup(function () {
// 	$(this).data("val", $(this).val());
// });

// $("#search").on("input", function (e) {
// 	var code = $(this).val();
// 	var test = $("#" + code).attr("id");
// 	var attr = $("#" + test).attr("href");

// 	$(".sidebar .hover").removeClass("hover");

// 	if (code === "") {
// 		$(".selected").click().removeClass("selected");
// 	} else {
// 		if (
// 			$("#" + test).hasClass("selected") ||
// 			$("#" + test).hasClass("selected-link")
// 		) {
// 			var prev = $(this).data("val");
// 			$("#" + prev)
// 				.removeClass("selected")
// 				.click();
// 			$("#" + prev).removeClass("active selected-link");
// 		} else {
// 			if (attr != "javascript:void(0)") {
// 				var link = $("#" + test).addClass("selected-link");
// 				$(document).on("keypress", function (e) {
// 					if (e.keyCode == 13) {
// 						if ($(link).hasClass("selected-link")) {
// 							$(link)[0].click();
// 						} else {
// 							return false;
// 						}
// 					}
// 				});
// 			} else {
// 				if (
// 					$("#" + test)
// 						.parent(".nav-item")
// 						.hasClass("menu-is-opening menu-open") ||
// 					$("#" + test)
// 						.parent(".nav-item")
// 						.hasClass("menu-open")
// 				) {
// 					$("#" + test).addClass("selected");
// 				} else {
// 					$("#" + test)[0].click();
// 					$("#" + test).addClass("selected");
// 				}
// 			}
// 		}
// 	}
// });

/* Table bindkeys */
$(document).on("keydown", ".table-bindkeys [name]", function (event) {
    switch (event.keyCode) {
        case 13:
            event.preventDefault();
        case 38:
            incomingElement = $(this)
                .parents("tr")
                .prev("tr")
                .find("td")
                .eq($(this).parents("td").index())
                .find("[name]");

            if (incomingElement.length !== 0) {
                setPrevFocus(incomingElement);
            }
            break;
        case 40:
            incomingElement = $(this)
                .parents("tr")
                .next("tr")
                .find("td")
                .eq($(this).parents("td").index())
                .find("[name]");

            if (incomingElement.length == 0) {
                $("form button#btnSimpan").focus();
            } else {
                incomingElement.focus();
            }
            break;
        default:
            break;
    }
});

$(document)
    .on("mousedown", "#addrow", function (event) {
        activeElement = document.activeElement;
    })
    .on("mouseup", "#addrow", function (event) {
        if (
            ($(activeElement).is("input") ||
                $(activeElement).is("select") ||
                $(activeElement).is("textarea")) &&
            $(activeElement).parents(".table-bindkeys").length > 0
        ) {
            if (
                typeof $(activeElement).attr("name") !== "undefined" &&
                $(activeElement).attr("name") !== false
            ) {
                activeElement.focus();
            }
        } else {
            $(".table-bindkeys").find("[name]")[0].focus();
        }
    });

function setPrevFocus(incomingElement) {
    position = incomingElement.val().length;

    setTimeout(() => {
        incomingElement[0].setSelectionRange(position, position);
    }, 0);

    incomingElement.focus();
}

function detectDeviceType() {
    const ua = navigator.userAgent;
    if (/(tablet|ipad|playbook|silk)|(android(?!.*mobi))/i.test(ua)) {
        return "tablet";
    } else if (
        /Mobile|Android|iP(hone|od)|IEMobile|BlackBerry|Kindle|Silk-Accelerated|(hpw|web)OS|Opera M(obi|ini)/.test(
            ua
        )
    ) {
        return "mobile";
    }
    return "desktop";
}

function setGridLastRequest(grid, lastRequest) {
    grid.setGridParam({
        lastRequest,
    });
}

function getGridLastRequest(grid) {
    return grid.getGridParam()?.lastRequest;
}

function abortGridLastRequest(grid) {
    // getGridLastRequest(grid)?.abort()

    var lastRequest = getGridLastRequest(grid);
    if (lastRequest) {
        lastRequest.abort();
        grid.abortComplete = false; // Set completion flag to false
        lastRequest.onreadystatechange = function () {
            if (lastRequest.readyState === 4) {
                grid.abortComplete = true; // Set completion flag to true
            }
        };
    }
}
function isAbortComplete(grid) {
    return grid.abortComplete !== false;
}
function isAbortComplete(grid) {
    return grid.abortComplete !== false;
}
function clearGridData(grid) {
    grid.jqGrid("setGridParam", {
        datatype: "local",
        data: [],
    }).trigger("reloadGrid");
}
function clearGridHeader(grid) {
    grid.jqGrid("setGridParam", {
        data: [],
    }).trigger("reloadGrid");
}

function setSpaceBarCheckedHandler(table = null) {
    // PREVENT WINDOW BEING SCROLLED WHEN SPACEBAR PRESSED
    window.addEventListener("keydown", function () {
        if (event.keyCode == 32) {
            let checkModal = $("#crudModal").hasClass("show");
            let checkSidebar = $('body').hasClass('sidebar-collapse');
            if (checkModal) {
                return;
            } else if (!checkSidebar) {
                return;
            } else {
                // ALLOW FILTER PRESS SPACEBAR
                if ($(".ui-search-toolbar input").is(":focus") || $("#jqGrid_searchText").is(":focus") || $("#titlesearch input").is(":focus")) {
                    return;
                } else {
                    var selectedRowId = $("#jqGrid").jqGrid(
                        "getGridParam",
                        "selrow"
                    );
                    if (selectedRowId) {
                        var $checkbox = $("#jqGrid").find(
                            `tr#${selectedRowId} td input[type='checkbox']`
                        );
                        // Toggle the checkbox state
                        let value = $checkbox.val();
                        if ($checkbox.is(":checked")) {
                            $checkbox.prop("checked", false);
                            $checkbox
                                .parents("tr")
                                .removeClass("bg-light-blue");
                            for (var i = 0; i < selectedRows.length; i++) {
                                if (selectedRows[i] == value) {
                                    selectedRows.splice(i, 1);
                                    selectedbukti.splice(i, 1);
                                }
                            }

                            if (
                                selectedRows.length !=
                                $("#jqGrid").jqGrid("getGridParam").records
                            ) {
                                $("#gs_").prop("checked", false);
                            }
                        } else {
                            $checkbox.prop("checked", true);
                            if (table == "suratpengantar") {
                                selectedRows.push(
                                    $("#jqGrid")
                                        .find(
                                            `tr#${selectedRowId} td[aria-describedby="jqGrid_nobukti"]`
                                        )
                                        .text()
                                );
                            } else {
                                selectedRows.push($checkbox.val());

                                selectedbukti.push(
                                    $(`#jqGrid tr#${selectedRowId}`)
                                        .find(
                                            `td[aria-describedby="jqGrid_nobukti"]`
                                        )
                                        .attr("title")
                                );
                            }
                            $checkbox.parents("tr").addClass("bg-light-blue");
                        }
                        event.preventDefault();
                    }
                }
            }
            document.body.style.overflow = "hidden";
        }
    });
    window.addEventListener("keyup", function () {
        if (event.keyCode == 32) {
            document.body.style.overflow = "auto";
        }
    });
}

function setSpaceBarCheckedHandler2() {
    // PREVENT WINDOW BEING SCROLLED WHEN SPACEBAR PRESSED
    window.addEventListener("keydown", function () {
        if (event.keyCode == 32) {
            let checkModal = $("#crudModal").hasClass("show");
            let checkSidebar = $('body').hasClass('sidebar-collapse');
            if (checkModal) {
                return;
            } else if (!checkSidebar) {
                return;
            } else {
                // ALLOW FILTER PRESS SPACEBAR
                if ($(".ui-search-toolbar input").is(":focus")) {
                    return;
                } else {
                    var selectedRowId = $("#jqGrid").jqGrid(
                        "getGridParam",
                        "selrow"
                    );
                    if (selectedRowId) {
                        var $checkbox = $("#jqGrid").find(
                            `tr#${selectedRowId} td input[type='checkbox']`
                        );
                        // Toggle the checkbox state
                        let value = $checkbox.val();
                        if ($checkbox.is(":checked")) {
                            $checkbox.prop("checked", false);
                            $checkbox
                                .parents("tr")
                                .removeClass("bg-light-blue");
                            for (var i = 0; i < selectedRowsIndex.length; i++) {
                                if (selectedRowsIndex[i] == value) {
                                    selectedRowsIndex.splice(i, 1);
                                    selectedbukti.splice(i, 1);
                                }
                            }

                            if (
                                selectedRowsIndex.length !=
                                $("#jqGrid").jqGrid("getGridParam").records
                            ) {
                                $("#gs_check").prop("checked", false);
                                $("#gs_").prop("checked", false);
                            }
                        } else {
                            $checkbox.prop("checked", true);
                            selectedRowsIndex.push($checkbox.val());
                            selectedbukti.push(
                                $(`#jqGrid tr#${selectedRowId}`)
                                    .find(
                                        `td[aria-describedby="jqGrid_nobukti"]`
                                    )
                                    .attr("title")
                            );
                            $checkbox.parents("tr").addClass("bg-light-blue");
                        }
                        event.preventDefault();
                    }
                }
            }
            document.body.style.overflow = "hidden";
        }
    });
    window.addEventListener("keyup", function () {
        if (event.keyCode == 32) {
            document.body.style.overflow = "auto";
        }
    });
}

function reloadGrid() {
    $("#jqGrid").trigger("reloadGrid");
}

function preventNewTab(table) {
    showDialog("TIDAK PUNYA HAK UNTUK MENGAKSES " + table);
}

function elementPager() {
    let elPager = $(`
    <div class="row d-flex align-items-center justify-content-center justify-content-lg-end pr-3">
        <div id="PagerHandler"
            class="pager-handler d-flex align-items-center justify-content-center mx-2">
            <button type="button" id="firstPageButton"
                class="btn btn-sm hover-primary mr-2 d-flex">
                <span class="fas fa-step-backward"></span>
            </button>

            <button type="button" id="previousPageButton"
                class="btn btn-sm hover-primary d-flex">
                <span class="fas fa-backward"></span>
            </button>

            <div class="d-flex align-items-center my-1  justify-content-between gap-10" id="infoPage">
                <span>Page</span>
                <input id="pagerInput" class="pager-input" value="1" autocomplete="off">

            </div>

            <button type="button" id="nextPageButton"
                class="btn btn-sm hover-primary d-flex">
                <span class="fas fa-forward"></span>
            </button>

            <button type="button" id="lastPageButton"
                class="btn btn-sm hover-primary ml-2 d-flex">
                <span class="fas fa-step-forward"></span>
            </button>

            <select id="rowList" class="ml-2">
                <option value="10">10</option>
                <option value="20">20</option>
                <option value="50">50</option>
                <option value="0">ALL</option>
            </select>
        </div>
        <div id="InfoHandlerEditAll" class="pager-info">

        </div>
    </div>

    `);

    $(".editAllPager").append(elPager);
}

let dateFilter = $("#editAllForm").find("[name=tglpengiriman]").val();
function filtersEditAll(dataColumn = []) {
    let elFilters = ` <th>
    <div id="resetfilter" class="reset"><span id="resetdatafilter"
            class="btn btn-default align-items-center"> X </span></div>
    </th>`;

    $.each(dataColumn, (index, detail) => {
        elFilters += `

        <th rowspan="1" colspan="1" >
            <div class="row">
                <div class="col-3 col-sm-12 input-group">
                    <input type="text" name="nama[]" class="form-control filter-input" data-field="${detail}" autocomplete="off">
                    <button type="button" title="Reset Search Value" data-column="${detail}" class="clearsearchclass btn position-absolute button-clear text-secondary" style="right: 14px; z-index: 99;"><i class="fa fa-times"></i></button>
                </div>


            </div>

        </th>

    `;
    });

    $("table tr.filters").html($(elFilters));

    $("#resetdatafilter").on("click", function () {
        var filters = [];
        $(".filter-input").each(function () {
            var field = $(this).data("field");
        });

        filterObject = {
            groupOp: "AND",
            rules: [],
        };

        $(".filter-input").val("");
        $("#searchText").val("");

        getAll(1, $("#rowList").val(), filterObject, dateFilter);
        setTimeout(function () {
            totalInfoPage(totalPages);
            viewPageEdit(currentPage, $("#editAll tbody tr").length);
        }, 500);
    });

    $(".filter-input").on("input", function () {
        var filters = [];
        $(".filter-input").each(function () {
            var field = $(this).data("field");

            var data = $(this).val();
            if (data !== "") {
                filters.push({
                    field: field,
                    op: "cn",
                    data: data,
                });
            }
        });

        filterObject = {
            groupOp: "AND",
            rules: filters,
        };
        // firstPage = false;
        getAll(1, 0, filterObject, dateFilter);
        console.log(filters, "filter");
        // setTimeout(function () {
        //     totalInfoPage(totalPages);
        //     viewPageEdit(currentPage, $("#editAll tbody tr").length);
        // }, 500);
    });

    $("#searchText").on("keyup", function () {
        var filters = [];
        var l = $(".filter-input").length;

        for (i = 0; i < l; i++) {
            var data = $(this).val();
            field = $(".filter-input").eq(i).data("field");

            if (data !== "") {
                filters.push({
                    field: field,
                    op: "cn",
                    data: data,
                });
            }
        }

        filterObject = {
            groupOp: "OR",
            rules: filters,
        };

        getAll(1, $("#rowList").val(), filterObject, dateFilter);
        setTimeout(function () {
            totalInfoPage(totalPages);
            viewPageEdit(currentPage, $("#editAll tbody tr").length);
        }, 500);
    });

    var filters = {};
    $(".clearsearchclass").on("click", function () {
        var column = $(this).data("column");

        if (!filters[column]) {
            filters[column] = $('.filter-input[data-field="' + column + '"]');
        }

        if (filters[column]) {
            filters[column].val("");
        }

        filterObject = {
            groupOp: "AND",
            rules: [],
        };

        $(".filter-input").each(function () {
            var field = $(this).data("field");
            var data = $(this).val();

            if (data !== "") {
                filterObject.rules.push({
                    field: field,
                    op: "cn",
                    data: data,
                });
            }
        });

        getAll(1, $("#rowList").val(), filterObject, dateFilter);

        setTimeout(function () {
            totalInfoPage(totalPages);
            viewPageEdit(currentPage, $("#editAll tbody tr").length);
        }, 500);
    });

    // togglePassword();
}

function bindKeyPagerEditAll(date) {
    $("#previousPageButton").click(function (e) {
        if (currentPage > 1) {
            getAll(parseInt(currentPage) - 1, rowCount, filterObject, date);
            $("#pagerInput").val(parseInt(currentPage) - 1);
        }

        if (tglPengiriman) {
            setTimeout(function () {
                viewPageEdit(10, lengthValue);
            }, 500);
        } else {
            viewPageEdit();
        }
    });
    // Handle next page button click
    $("#nextPageButton").click(function (e) {
        if (currentPage < totalPages) {
            // console.log(lengthValue);

            getAll(parseInt(currentPage) + 1, rowCount, filterObject, date);

            $("#pagerInput").val(parseInt(currentPage) + 1);
            // viewPageEdit(selectedValue, rowCount,lengthValue);
        }

        if (tglPengiriman) {
            setTimeout(function () {
                viewPageEdit(10, lengthValue);
            }, 500);
        } else {
            viewPageEdit();
        }
    });

    $("#lastPageButton").click(function (e) {
        getAll(lastPageEditAll);
        console.log(lengthValue);
        $("#pagerInput").val(lastPageEditAll);
        viewPageEdit();
    });

    $("#firstPageButton").click(function (e) {
        getAll(1, selectedValue);

        $("#pagerInput").val(1, selectedValue);
        viewPageEdit(selectedValue, rowCount);
    });

    $("#pagerInput").on("input", function () {
        let inputValue = $(this).val();

        if (inputValue === "" || inputValue == 0) {
            inputValue = 1; // Jika kosong, paksakan nilai menjadi 1
            $(this).val(inputValue);
        }
        getAll(inputValue);

        viewPageEdit();
    });

    $("#rowList").on("change", function () {
        selectedValue = $(this).val();

        getAll($("#pagerInput").val(), selectedValue);

        setTimeout(function () {
            rowCount = $("#editAll tbody tr").length;

            totalInfoPage(totalPages);
            viewPageEdit(selectedValue, rowCount);
        }, 500);
    });
}

function viewPageEdit(perPage = 10, rowCountEdit = 10) {
    let pageEditAll = $("#pagerInput").val();
    let perPageEditAll = perPage;
    let recordCountEditAll = rowCountEdit;
    let firstRowEditAll = (pageEditAll - 1) * perPageEditAll + 1;
    let lastRowEditAll = firstRowEditAll + recordCountEditAll - 1;
    $("#InfoHandlerEditAll").html(`
        <div class="text-md-right">
            View  ${firstRowEditAll} - ${lastRowEditAll} of ${totalRowsEditAll}
        </div>
    `);
}

function totalInfoPage() {
    $("#totalPage").remove();
    $("#infoPage").append(`
    <span id="totalPage">of ${totalPages}</span>
`);
}

function getQueryParameter() {
    setTimeout(() => {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get("nobukti") != null) {
            $("#gs_nobukti").val(urlParams.get("nobukti"));
            $("#jqGrid")
                .jqGrid("setGridParam", {
                    postData: {
                        filters: JSON.stringify({
                            groupOp: "AND",
                            rules: [
                                {
                                    field: "nobukti",
                                    op: "cn",
                                    data: urlParams.get("nobukti"),
                                },
                            ],
                        }),
                    },
                    datatype: "json",
                })
                .trigger("reloadGrid");

            window.history.replaceState(
                null,
                "",
                window.location.origin + window.location.pathname
            );
        }
    }, 1000);
}

function syncHeaderScroll(gridId) {
    // Get the jqGrid header and body containers dynamically based on the grid ID
    let gridHeader = $(`#${gridId}`)
        .closest(".ui-jqgrid")
        .find(".ui-jqgrid-hdiv");
    let gridBody = $(`#${gridId}`)
        .closest(".ui-jqgrid")
        .find(".ui-jqgrid-bdiv");

    // Sync header scroll with body scroll for the specified grid
    gridBody.on("scroll", function () {
        gridHeader.scrollLeft(gridBody.scrollLeft());
    });
}

function uploadUserPreferencesToServer(userId, menu, preferences, type = '') {
    const formData = new FormData();
    formData.append("userId", userId);
    formData.append("menu", menu);
    formData.append("preferences", JSON.stringify(preferences));
    formData.append("type", type);

    $.ajax({
        url: `${appUrl}/api/upload-user-preferences`,
        type: "POST",
        data: formData,
        processData: false,
        contentType: false,

        success: function (response) {
            console.log("Preferensi pengguna berhasil disimpan:", response);
        },
        error: function (error) {
            console.error("Gagal menyimpan preferensi pengguna:", error);
        },
    });
}

function reorderColModel(baseColModel, savedOrder) {
    const reorderedCols = savedOrder
        .map((savedCol) => {
            const baseCol = baseColModel.find(
                (col) => col.name === savedCol.name
            );
            return baseCol ? { ...baseCol, width: savedCol.width } : undefined;
        })
        .filter((col) => col !== undefined);

    baseColModel.forEach((baseCol) => {
        if (!savedOrder.some((savedCol) => savedCol.name === baseCol.name)) {
            // reorderedCols.push({ ...baseCol, hidden: true });
            if (baseCol.hasOwnProperty('hidden')) {
                reorderedCols.push({ ...baseCol, hidden: true });
            } else {
                reorderedCols.push({ ...baseCol });
            }
        }
    });

    return reorderedCols;
}

function fetchUserPreferencesFromServer(userId) {
    let userref = {};
    $.ajax({
        url: `${appUrl}/api/get-user-preferences?userId=${userId}`,
        type: "GET",
        async: false,
        success: function (response) {
            if (response && response.userPreferences) {
                userref = response.userPreferences;
            }
        },
        error: function (error) {
            console.error(new Error("Gagal memuat preferensi pengguna:", error));

            userref = {};
        },
    });

    return userref;
}

function resetColumns(baseColModel, grid, menu) {
    $("#resetColModel").on("click", function () {
        $("#customCard").css({ display: "none" });
        let preferences = baseColModel.map((col) => ({
            name: col.name,
            width: col.width,
        }));

        let colModel = grid.jqGrid("getGridParam", "colModel");
        let nameToIndexMap = {};
        colModel.forEach((col, index) => {
            nameToIndexMap[col.name] = index;
        });

        let permutation = preferences.map((col) => nameToIndexMap[col.name]);
        permutation.splice(0, 0, nameToIndexMap["rn"]);

        grid.jqGrid("remapColumns", permutation, true);
        uploadUserPreferencesToServer(authUserId, menu, preferences);

        baseColModel.forEach((baseCol, index) => {
            let colWidth =
                baseCol.width !== undefined ? parseInt(baseCol.width, 10) : 150;
            grid.jqGrid("setColWidth", baseCol.name, colWidth, false);
        });
        // setColWidth calls grid.dragEnd() without the "events" flag, so
        // jqGridResizeStop never fires and sticky columns are left stale.
        grid.jqGrid("updateStickyFrozenColumns");
    });
}

function newResetColumns(baseColModel, grid, menu) {
    dataGridId = $("#resetColModel").attr("data-grid-id")
    // $(`#resetColModel`).on("click", function () {
    $("#customCard").css({ display: "none" });
    let preferences = baseColModel.map((col) => ({
        name: col.name,
        width: col.width,
    }));

    let colModel = grid.jqGrid("getGridParam", "colModel");
    let nameToIndexMap = {};
    colModel.forEach((col, index) => {
        nameToIndexMap[col.name] = index;
    });

    let permutation = preferences.map((col) => nameToIndexMap[col.name]);
    permutation.splice(0, 0, nameToIndexMap["rn"]);

    grid.jqGrid("remapColumns", permutation, true);
    uploadUserPreferencesToServer(authUserId, menu, preferences);

    baseColModel.forEach((baseCol, index) => {
        let colWidth =
            baseCol.width !== undefined ? parseInt(baseCol.width, 10) : 150;
        grid.jqGrid("setColWidth", baseCol.name, colWidth, false);
    });
    grid.jqGrid("updateStickyFrozenColumns");
    // });
}

function filterToolbarData(filtersData, colModel) {
    let filteredData = [...filtersData];

    colModel.forEach(function (cm) {
        // const searchField = $(`#gs_${cm.name}`).val();
        let searchField

        if ($('#crudModal').length > 0) {
            searchField = $('#crudModal').find(`#gs_${cm.name}`).val()
        } else {
            searchField = $(`#gs_${cm.name}`).val();
        }

        if (
            searchField &&
            cm.search !== false &&
            (cm.stype === undefined || cm.stype === "text")
        ) {
            const regex = new RegExp(`(${searchField})`, "gi");

            filteredData = filteredData
                .map((row) => {
                    const fieldValue = row[cm.name]?.toString() || ""; // Nilai kolom
                    if (fieldValue.toLowerCase().includes(searchField.toLowerCase())) {
                        const highlightedValue = fieldValue.replace(
                            regex,
                            `<span class="highlight">$1</span>`
                        );
                        return { ...row, [cm.name]: highlightedValue };
                    }
                    return row;
                })
                .filter((row) => {
                    const fieldValue = row[cm.name]?.toString() || "";
                    return fieldValue.toLowerCase().includes(searchField.toLowerCase());
                });
        }
    });

    return filteredData;
}

function filterLocalData(searchText, colModel, onPaste, sortIndex) {
    let filteredData = [...filtersData];
    if (!searchText) {
        return filteredData;
    }

    if (!onPaste) {

        return filteredData.filter((row) => {
            let found = false;

            colModel.filter((cm) => {
                if (
                    cm.search !== false &&
                    (!cm.stype || cm.stype === "text")
                ) {
                    let fieldValue = row[cm.name]?.toString() || ""; // Nilai kolom
                    if (fieldValue.toLowerCase().includes(searchText.toLowerCase())) {
                        found = true;
                    }
                }
            });
            return found;
        });

    } else {
        return filteredData.filter((row) => {
            let found = false;
            let colFiltered = colModel.find(col => col.name === sortIndex);

            let fieldValue = row[colFiltered.name]?.toString() || ""; // Nilai kolom
            if (fieldValue.toLowerCase() === searchText.toLowerCase()) {
                found = true;
            }
            return found;
        });
    }
}
function previewPDFs(url) {
    const winTab = window.open("", "_blank");
    // const origin = window.location.origin; // http://localhost
    // const pathSegments = window.location.pathname.split('/'); // ['/', 'nama_aplikasi', 'public', ...]
    // const basePath = pathSegments[1]; // "nama_aplikasi"
    // const baseUrl = `${origin}/${basePath}`; // http://localhost/nama_aplikasi

    // // URL ikon
    // const faviconUrl = `${baseUrl}/public/libraries/myimage/favicon.ico?v=${Date.now()}`;
    // <link rel="icon" type="image/x-icon" href="${faviconUrl}"></link>
    console.log('url', url)
    winTab.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>TRUCKING | PT. TRANSPORINDO AGUNG SEJAHTERA</title>
            <style>
                html, body {
                    margin: 0;
                    height: 100%;
                    overflow: hidden;
                }
                iframe {
                    width: 100%;
                    height: 100%;
                    border: none;
                }
            </style>
        </head>
        <body>
            <iframe src="${url}"></iframe>
        </body>
        </html>
    `);
    winTab.document.close();
}
function deleteSelectedRowLazyLoad(gridSelector) {
    var grid = $(gridSelector);
    var rowId = grid.jqGrid('getGridParam', 'selrow');

    if (rowId) {
        var ids = grid.jqGrid('getDataIDs');
        var index = ids.indexOf(rowId);

        var nextId = null;
        if (index < ids.length - 1) {
            // next row
            nextId = ids[index + 1];
        } else if (index > 0) {
            // prev row
            nextId = ids[index - 1];
        }

        grid.jqGrid('delRowData', rowId);

        if (nextId) {
            grid.jqGrid('setSelection', nextId);
        }
    }
}

// console.log('ICON ASC BEFORE OVERRIDE: ', $.jgrid.styleUI.fontAwesome ? $.jgrid.styleUI.fontAwesome.base.icon_asc : 'fontAwesome is missing');

// console.log('STYLEUI KEYS:', Object.keys($.jgrid.styleUI));

// console.log('BOOTSTRAP4 ICON ASC:', $.jgrid.styleUI.Bootstrap4 ? $.jgrid.styleUI.Bootstrap4.base.icon_asc : 'Bootstrap4 missing');

$(document).ready(function () {
    if ($('.fa-caret-up').length || $('.fa-caret-down').length) {
        $('.fa-caret-up').removeClass('fa-caret-up').addClass('fa-fw fa-arrow-up');
        $('.fa-caret-down').removeClass('fa-caret-down').addClass('fa-fw fa-arrow-down');
    }
});

// Global extension for CSS-based Sticky Frozen Columns
$.jgrid.extend({
    setupStickyFrozenColumns: function() {
        return this.each(function() {
            var $t = this, $grid = $($t);
            if (!$t.grid) return;
            
            var cm = $grid.jqGrid('getGridParam', 'colModel');
            if (!cm) return;
            var hasFrozen = cm.some(function(c) { return c.frozen; });
            if (!hasFrozen) return;
            
            var gridId = $grid.attr('id');
            var stickyCols = [];
            // Frozen columns must be contiguous from the left (see docs). The left
            // offset below is a running cumulative sum, so a non-frozen column in
            // between would make every sticky column after the gap sit at a "left"
            // that ignores that gap's width, overlapping it as soon as you scroll.
            // Hidden columns don't occupy screen space, so they don't break the chain.
            for (var i = 0; i < cm.length; i++) {
                if (cm[i].hidden) continue;
                if (cm[i].name === 'rn' || cm[i].name === 'cb' || cm[i].frozen) {
                    stickyCols.push(cm[i].name);
                } else {
                    break;
                }
            }
            
            var applySticky = function() {
                var cumulativeLeft = 0;
                var $hdiv = $grid.closest('.ui-jqgrid-view').find('.ui-jqgrid-hdiv');

                stickyCols.forEach(function(colName, idx) {
                    var cmEntry = cm.find(function(c) { return c.name === colName; });
                    if (cmEntry && cmEntry.hidden) return;

                    var isLast = (idx === stickyCols.length - 1);
                    var leftPos = cumulativeLeft + 'px';

                    // 1. Header label
                    var $th = $hdiv.find('th#' + gridId + '_' + colName);
                    $th.addClass('frozen-col-sticky').css('left', leftPos);
                    if (isLast) $th.addClass('frozen-col-last');

                    // 2. Filter toolbar cell
                    var colIdx = $th.index();
                    if (colIdx >= 0) {
                        var $filterCell = $hdiv.find('tr.ui-search-toolbar th:eq(' + colIdx + '), tr.ui-search-toolbar td:eq(' + colIdx + ')');
                        $filterCell.addClass('frozen-col-sticky').css('left', leftPos);
                        if (isLast) $filterCell.addClass('frozen-col-last');
                    }

                    // 3. Body cells and Footer cells
                    var selector = '[aria-describedby="' + gridId + '_' + colName + '"]';
                    var $cells = $grid.closest('.ui-jqgrid-view').find(selector);
                    $cells.addClass('frozen-col-sticky').css('left', leftPos);
                    if (isLast) $cells.addClass('frozen-col-last');

                    var colWidth = $th.outerWidth() || 0;
                    if (colWidth === 0) {
                        colWidth = (cmEntry && parseInt(cmEntry.width)) || 45;
                    }
                    cumulativeLeft += colWidth;
                });
            };

            $t.applyStickyFrozenColumns = applySticky;
            applySticky();

            var tbody = $grid.find('tbody')[0];
            if (tbody && !$grid.data('stickyObserver')) {
                var observer = new MutationObserver(function() {
                    applySticky();
                });
                observer.observe(tbody, { childList: true });
                $grid.data('stickyObserver', observer);
            }

            // Re-apply sticky positions once a resize finishes. applySticky() is
            // idempotent (addClass + fresh left each call), so there is no need to
            // strip the sticky classes on jqGridResizeStart first - doing that only
            // opened a window where a column stayed "detached" (no position:sticky)
            // if the reattach step raced with layout, which is what caused headers
            // to get stuck after resizing an unrelated column.
            if (!$grid.data('stickyResizeHooked')) {
                $grid.on('jqGridResizeStop.sticky', function() {
                    var $view = $grid.closest('.ui-jqgrid-view');
                    var $hdiv = $view.find('.ui-jqgrid-hdiv');
                    // Double rAF guarantees the browser has finished layout for the
                    // new column width before we read $th.outerWidth() in applySticky().
                    requestAnimationFrame(function() {
                        requestAnimationFrame(function() {
                            void $hdiv[0].offsetHeight;
                            applySticky();
                            // Do NOT manually assign hDiv.scrollLeft here. jqGrid's own
                            // dragEnd already synced it, and when the search/filter
                            // toolbar is active, jqGrid also listens for "scroll" on
                            // hDiv itself (grid.filter.js) to mirror back to bDiv, using
                            // grid.bScroll/grid.hScroll as a re-entrancy guard. A manual
                            // hDiv.scrollLeft assignment here fires that listener, which
                            // sets grid.bScroll = true; that flag is only ever reset back
                            // to false from inside scrollGrid's own `if (!grid.bScroll)`
                            // block, so if a real body scroll happens while it's stuck
                            // true, scrollGrid's whole body (including the reset) gets
                            // skipped and the header desyncs from the body permanently -
                            // exactly the "header stuck" symptom. Reset both flags
                            // defensively instead of touching scrollLeft ourselves.
                            if ($t.grid) {
                                $t.grid.bScroll = false;
                                $t.grid.hScroll = false;
                            }
                        });
                    });
                });
                $grid.data('stickyResizeHooked', true);
            }
        });
    },
    updateStickyFrozenColumns: function() {
        return this.each(function() {
            if (this.applyStickyFrozenColumns) {
                this.applyStickyFrozenColumns();
            }
        });
    },
    refreshStickyFrozenColumns: function() {
        return this.each(function() {
            var $grid = $(this);
            $grid.closest('.ui-jqgrid-view').find('.frozen-col-sticky')
                .removeClass('frozen-col-sticky frozen-col-last')
                .css('left', '');
            var observer = $grid.data('stickyObserver');
            if (observer) {
                observer.disconnect();
                $grid.removeData('stickyObserver');
            }
            this.applyStickyFrozenColumns = null;
            $grid.jqGrid('setupStickyFrozenColumns');
        });
    }
});
