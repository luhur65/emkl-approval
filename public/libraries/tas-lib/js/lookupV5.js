
let filterPostData
let elementInput = ''
let typeData = 'JSON'
let showOnButton = true
let supressType = "input"
let isReportClicked = false
let isReportGridClicked
let isExportClicked
let lastAjaxRequest;
let activeRequests = 0;
let currentElementOpened = ''
let activeLookupElementV4 = null;
let aktifIdV4 = null;
let selectedIdV4;
// let bottomSelected;
// let topSelected;
let indexRowSelectV4;
let keydownIndexV4 = true;
// let isLookupOpenV4 = true;
let isSelectedRow = false;
let offsetWindowV4;
let oldSettings = {};
let isToolbarSearch = false
let activate = false;
let oldElement = null;
let inputValue;

$.fn.lookupV5 = function (options) {
    if (!options.lookupKey) {
        const requiredWhenNoKey = ['url', 'column', 'lookupName', 'sortname'];
        const missing = [];

        for (const key of requiredWhenNoKey) {
            if (options[key] == null) missing.push(key);
        }

        if (missing.length) {
            throw new Error(
                `[lookupV5] Options: ${missing.join(', ')} WAJIB diisi jika lookupKey tidak digunakan`
            );
        }

        // extra type safety (opsional tapi disarankan)
        if (!Array.isArray(options.column)) {
            throw new Error('lookupV5: "column" harus berupa Array');
        }
    }    

    let defaults = {
        title: null,
        singlecolumn: false,
        autoComplete: false,
        autoCompleteVersion: 'equalto',
        autoSearch: false,
        labelColumn: true,
        detail: null,
        alignRightMobile: null,
        alignRight: null,
        searching: [],
        multiColumnSize: null,
        extendSize: null,
        selectedRequired: null,
        filterToolbar: false,
        postData: {},
        endpoint: null,
        getValue: null,
        lookupName: null,
        typeData,
        showOnButton,
        data: [],
        column: [],
        localFilter: null,
        beforeProcess: function () { },
        onShowLookup: function (rowData, element) { },
        onSelectRow: function (rowData, element) { },
        onCancel: function (element) { },
        onClear: function (element) { },
    };

    let settings = $.extend({}, defaults, options);
    if (settings.typeData === 'LOCAL' && Array.isArray(settings.data)) {
        settings._originalData = [...settings.data];
    }

    this.each(function () {
        let element = $(this);        
        element.data("hasLookup", true);

        if (settings.showOnButton && !settings.autoSearch) {
            element.wrap('<div class="input-group"></div>').after(`
                ${settings.onClear
                    ? `<button type="button" class="btn position-absolute button-clear text-secondary" style="right: 34px; z-index: 99;"><i class="fa fa-times-circle" style="font-size: 15px; margin-top:2px; color:red"></i></button>`
                    : ``
                }
                <div class="input-group-append">
                        <button class="btn btn-easyui lookup-toggler" type="button">
                            <i class="fas fa-sort-down text-easyui-dark" style="font-size: 16px"></i>
                        </button>
                    </div>
                `);


            element.siblings(".button-clear").click(function () {
                handleOnClear(element);
            });

            element
                .siblings(".input-group-append")
                .find(".lookup-toggler")
                .click(function () {
                    event.preventDefault();
                    $(document).off("mousedown.lookup");
                    element.data("input", false);
                    let lookupContainer = element.siblings(`#lookup-${element.attr("id")}`);
                    // // console.log('oldElement, ', oldElement, 'settings', settings, 'oldSettings', oldSettings);

                    if (activeLookupElementV4 && activeLookupElementV4 != null) {
                        if (aktifIdV4 != `#lookup-${element.attr("id")}`) {
                            bottomSelected = 10;
                            topSelected = 0;

                            $(aktifIdV4).hide();
                            // activate = false;
                        }
                    }

                    activeLookupElementV4 = lookupContainer;
                    aktifIdV4 = `#lookup-${element.attr("id")}`;

                    if (currentElementOpened == element && activate) {
                        $(aktifIdV4).hide();
                        handleCloseLookup(currentElementOpened, false, true)
                    } else {
                        if (currentElementOpened) {
                            // ABORT MATIIN KARNA UDH MANFAATIN ABORT DARI HANDLECLOSELOOKUP KARNA ACTIVATE DIATAS UDH DI KOMEN
                            // let abortGrid = currentElementOpened?.siblings(`#lookup-${currentElementOpened.attr("id")}`).find(".lookup-grid");
                            // abortGridLastRequest($(abortGrid));
                            handleCloseLookup(currentElementOpened)
                        }
                        currentElementOpened = element
                        activateLookup(element);
                        element.focus();
                        activate = true;
                        bindKey = false;
                    }

                    // isLookupOpenV4 = true;
                });

            activate = false;
        }

        element.on("input", function (event) {
            if (supressType == "paste") return;

            let lookupContainer = element.siblings(`#lookup-${element.attr("id")}`);
            element.data("input", true);
            const searchValue = element.val().trim();
            inputValue = element.val(); // jgn dipakein trim untuk auto complete, biar ambil value asli termasuk spasi
            clearGhost(element) // hapus ghost pas pertama input biar gak numpuk

            if (activeLookupElementV4 != null) {
                if (aktifIdV4 != `#lookup-${element.attr("id")}`) {
                    $(aktifIdV4).hide();
                    activate = false;
                }
                // else {
                //     $(aktifIdV4).show()
                //     activate = true;
                // }
            } else {
                activeLookupElementV4 == lookupContainer
            }

            oldElement = element;
            oldSettings = settings;
            aktifIdV4 = `#lookup-${element.attr("id")}`;

            setTimeout(() => {
                if (!activate) {
                    currentElementOpened = element
                    activateLookup(element);
                    activate = true;

                    handleOnInputNew(element, searchValue, settings);
                    bindKey = false;
                } else {
                    // INI UNTUK KONDISI LOKAL TAPI PUNYA FILTER TOOLBAR SETELAH SEARCH FILTER TOOLBAR LALU SEARCH INPUT DATA GRID PARAM NYA HARUS DI SET KE DATA AWAL DULU LAGI
                    if (settings.typeData == 'LOCAL' && settings.data.length >= 0 && settings.filterToolbar) {                        
                        const data = finalLocalData(settings)
                        let grid = lookupContainer.find(".lookup-grid");
                        grid.clearGridData()
                        .setGridParam({
                            data: data
                        })
                        .trigger("reloadGrid");
                    }

                    handleOnInputNew(element, searchValue, settings)
                    bindKey = false;
                }
            }, 70); // KASI TIMEOUT DULU BUAT DIA NGEBACA ACTIVATE ATAU ENGGA, BIAR GA NABRAK


            // isLookupOpenV4 = true;
        });

        element.on("paste", function () {            
            if (typeof settings.data === 'string') {
                resolveDataFromString(settings.data);
            }
            settings.beforeProcess.call(settings);
            supressType = "paste";
            let searchValue;
            const $thisElement = $(this);
            $thisElement.data("input", true);
            let lookupContainer = element.siblings(`#lookup-${element.attr("id")}`);
            let grid = lookupContainer.find(".lookup-grid");
            if (grid.length > 0) {
                abortGridLastRequest($(grid));
            }

            // if (settings.autoSearch) {
            //     isLookupOpenV4 = true;

            // } else {
            setTimeout(function () {
                searchValue = $thisElement.val().trim();
                $thisElement.val(searchValue)

                if (settings.typeData == 'LOCAL' && settings.data.length >= 0) {
                    handleOnPasteLocal(element, searchValue, settings);
                    handleCloseLookup(element)  // DITAMBAHIN UNTUK CASE PASTE DENGAN KONDISI LOOKUP NYA LAGI TERBUKA TRUS PASTE DIA HARUS KETUTUP
                } else {
                    isLoading = true
                    handleOnPasteJSON(element, searchValue, settings);
                    handleCloseLookup(element)  // DITAMBAHIN UNTUK CASE PASTE DENGAN KONDISI LOOKUP NYA LAGI TERBUKA TRUS PASTE DIA HARUS KETUTUP
                }
            }, 0);

            setTimeout(function () {
                supressType = "input";
            }, 0);
            // }

        })

        // if (settings.autoSearch) {
            element.off("mousedown.lookup");
            element.on('mousedown.lookup', function (e) {
                // INI UNTUK KLIK ELEMENT INPUTAN AUTO SEARCH LAIN JADI DITUTUP DULU YG LAMA
                if (currentElementOpened && currentElementOpened[0] !== element[0]) {
                    // e.stopPropagation();  // biar gak ke document
                    // console.log('MASOKKKK COYYY 2', activate, currentElementOpened, element, 'oldSettings', oldSettings, settings);
                    if (currentElementOpened.val() && settings.autoSearch) {
                        handleAutoSearch(oldSettings, currentElementOpened)
                    } else if (currentElementOpened.val() && !settings.autoSearch) {
                        let lookupContainer = currentElementOpened?.siblings(`#lookup-${currentElementOpened.attr("id")}`);
                        getFirst(oldSettings.searching, lookupContainer, currentElementOpened, oldSettings, "DARI KLIK INPUTAN LOOKUP KETIK KLIK KE INPUTAN LOOKUP LAIN")
                            .then((firstData) => {
                                // console.log("Data pertama:", firstData);
                            }).catch((error) => {
                                console.error("Terjadi kesalahan:", error);
                            })
                    }
                    handleCloseLookup(currentElementOpened); // TUTUP YANG SEBELUMNYA
                }

                if (activate) return;
                // console.log('MASOKKKK COYYY', activate, currentElementOpened, element);

                e.stopPropagation();
                currentElementOpened = element
                oldSettings = settings;
                activateLookup(element);
                activate = true;
                bindKey = false;
                aktifIdV4 = `#lookup-${element.attr("id")}`;
            });
        // }

        element.on("blur", function (event) {
            if (element.val() == '') {
                isLoading = false
            }
            if (detectDeviceType() != "desktop") {
                const lookupContainer = element.siblings(`#lookup-${element.attr("id")}`);
                if (element.val() != '' && activate == false) {
                    getFirst(settings.searching, lookupContainer, element, settings);
                }
            }
        })
    });

    function handleAutoComplete(element, grid, settingsAutoComplete, fromUpDown = false) {         
        element.attr('suggestion', null); 
        // if (element.is(':focus') == false || !inputValue) return; 
        if (!inputValue) return; 

        const data = $(grid).data("allData");
        if (data.length === 0) return;     

        clearGhost(element)         
        const prefix = 'sw_activatelookup_';
        element.val(inputValue.startsWith(prefix) ? inputValue.slice(prefix.length) : inputValue)
        // element.val(inputValue);
        const field = Array.isArray(settingsAutoComplete.searching) ? settingsAutoComplete.searching[0] : settingsAutoComplete.searching;
        
        switch (settingsAutoComplete.autoCompleteVersion) {
            case 'selectedFirstAlphabet':
                selectedFirstAlphabet();
                break;
            case 'autoCompletingFalse':
                autoCompletingFalse();
                break;
            case 'equalto':
                equalTo();
                break;
            case 'startwith':
                startWith();
                break;
            default:
                equalTo();
                break;
        }

        function selectedFirstAlphabet() {                
            let rowId = grid.getGridParam("selrow");
            let rowIndex = grid.jqGrid("getInd", rowId);
            let rowData = data.find(item => String(item.id) === String(rowId))[field];
            if (!rowData || !rowId) return;

            if (rowIndex > 1) {
                fromUpDown = true;
            }
            const prefixData = data.filter((item) => {
                return item[field].startsWith(inputValue.toUpperCase());
            })       

            if (prefixData.length > 0 && !fromUpDown) {
                const idFirstData = prefixData[0].id;
                
                $(`#${settingsAutoComplete.lookupName}`).jqGrid('setSelection', idFirstData);   // set selection grid ke baris pertama yg prefix

                rowId = grid.getGridParam("selrow");
                rowData = data.find(item => String(item.id) === String(rowId))[field];
                if (!rowData || !rowId) return;

                const suggestion = rowData.substring(inputValue.length);
                const html = `<span style="color: transparent; visibility: hidden;">${inputValue}</span><span style="color: #aaa;">${suggestion}</span>`;
                element.closest('.lookup-autocomplete-wrapper').find('.ghost-text').html(html);
                element.attr('suggestion', rowData)
            } else {
                if (fromUpDown) {
                    element.val(rowData);
                } else {
                    return; // KALO GADA PREFIX DAN BUKAN DARI UPDOWN MAKA GA ADA NGEJALANIN APAPUN
                }
            }
        }

        function autoCompletingFalse() {
            const rowId = grid.getGridParam("selrow");    
            let rowIndex = grid.jqGrid("getInd", rowId);        
            const rowData = data.find(item => String(item.id) === String(rowId));
            if (!rowData || !rowId) return;            

            const findData = rowData[field];
            const isStartWith = findData.startsWith(inputValue.toUpperCase());
            console.log('rowIndex', rowIndex, 'inputValue', inputValue,  'isStartWith', isStartWith);
            
            if (rowIndex > 1) {                
                fromUpDown = true;
            }
        
            if (!isStartWith) {
                if (fromUpDown) {
                    element.val(findData);
                }
                return;
            } else {
                const suggestion = findData.substring(inputValue.length);
                const html = `<span style="color: transparent; visibility: hidden;">${inputValue}</span><span style="color: #aaa;">${suggestion}</span>`;
                element.closest('.lookup-autocomplete-wrapper').find('.ghost-text').html(html);
                element.attr('suggestion', findData)
            }
        }

        function equalTo() {
            const rowId = grid.getGridParam("selrow");            
            const rowData = data.find(item => String(item.id) === String(rowId));
            if (!rowData || !rowId) return;

            const findData = rowData[field];
            if (fromUpDown) {
                element.val(findData);
            }
            return;
        }

        function startWith() {            
            const rowId = grid.getGridParam("selrow");            
            const rowData = data.find(item => String(item.id) === String(rowId));
            const findData = rowData[field];
            if (!rowData || !rowId || !findData) return;            
            
            const prefixwith = inputValue.startsWith('sw_activatelookup_')
            if (prefixwith && fromUpDown) {     
                element.val(findData);
            } else {
                const suggestion = findData.substring(inputValue.length);
                const html = `<span style="color: transparent; visibility: hidden;">${inputValue}</span><span style="color: #aaa;">${suggestion}</span>`;
                element.closest('.lookup-autocomplete-wrapper').find('.ghost-text').html(html);            
                element.attr('suggestion', findData)
            }
        }
    }

    function clearGhost(input) {        
        const ghostEl = input.closest('.lookup-autocomplete-wrapper').find('.ghost-text');
        if (ghostEl.length) {
            ghostEl.html('');
            ghostEl.text('');
        }
    }

    function getFirst(fields, lookupContainer, element, currentSettings, from = '') {
        // console.log('from GET FIRST', from, 'currentSettings', currentSettings, 'isSelectedRow', isSelectedRow, 'isLoading', isLoading);

        return new Promise((resolve, reject) => {
            if (!showOnButton || currentSettings.autoSearch) {
                resolve()
                return
            }

            if (isSelectedRow) {
                resolve()
                return;
            }

            let rulesFirst = [];            
            const suggestionText = element.attr('suggestion');            
            const dataval = (currentSettings.autoComplete && currentSettings.autoCompleteVersion != 'equalto' && suggestionText) ? suggestionText : element.val().trim();
            const getDefaultConfig = lookupConfigList(currentSettings);            
            if (!Array.isArray(fields)) {
                fields = [fields]; // ubah jadi array tunggal agar tidak crash
            }

            if (fields.length > 0) {
                fields.forEach((field) => {
                    rulesFirst.push({
                        field: field,
                        op: "cn",
                        data: dataval.toUpperCase(),
                    });
                });
            } else {
                for (var i = 0; i < getDefaultConfig.column.length; i++) {
                    if (getDefaultConfig.column[i].search !== false && getDefaultConfig.column[i].hidden !== true) {
                        rulesFirst.push({
                            field: getDefaultConfig.column[i].name,
                            op: "cn",
                            data: dataval.toUpperCase(),
                        });
                    }
                }
            }

            if (currentSettings.typeData == "LOCAL" && currentSettings.data.length >= 0) {
                let localData = finalLocalData(currentSettings);
                // $("#processingLoader").removeClass("d-none");
                
                const localResults = localData.filter((row) => {
                    return rulesFirst.some((rule) => {
                        const fieldValue = row[rule.field]?.toString().toUpperCase() || "";
                        return fieldValue.includes(rule.data);
                    });
                })
                .sort((a, b) => {
                    const valA = a[getDefaultConfig.sortname] ?? "";
                    const valB = b[getDefaultConfig.sortname] ?? "";

                    if (valA < valB) return -1;
                    if (valA > valB) return 1;
                    return 0;
                });

                if (localResults.length === 0) {
                    handleCloseLookup(element)
                    // handleOnCancel(element);
                    $(element).parents().eq(1).find('input:hidden').val('')
                    showDialog(`DATA ${dataval} TIDAK DITEMUKAN`)
                    $("#processingLoader").addClass("d-none");
                    // isLoading = false;
                    // resolve([]);
                    if (activeRequests <= 0) {
                        isLoading = false;
                    }
                    return;
                } else {
                    const firstdata = localResults[0];
                    handleSelectedRow(firstdata.id, lookupContainer, element, true, firstdata, currentSettings)
                    if (activeRequests > 0) {
                        return;
                    }
                    $("#processingLoader").addClass("d-none");
                }

                isLoading = false;
                if (isSubmitClicked) {
                    $('#btnSubmit').click()
                    isSubmitClicked = false
                } else if (isSubmitAddClicked) {
                    $('#btnSaveAdd').click()
                    isSubmitAddClicked = false
                } else if (isReportClicked) {
                    $('#btnPreview').click()
                    isReportClicked = false
                } else if (isExportClicked) {
                    $('#btnExport').click()
                    isExportClicked = false
                }
                resolve()
                return;
            } else {
                const endpoint = currentSettings.postData.url ? currentSettings.postData.url : getDefaultConfig.url;
                filterPostData = mergeFilterPostData(currentSettings.postData, getDefaultConfig.filterPostData)
                const payload = {
                    ...filterPostData,
                    sortIndex: getDefaultConfig.sortname,
                    sortOrder: 'asc',
                    filters: JSON.stringify({
                        groupOp: "OR",
                        rules: rulesFirst,
                    }),
                }

                activeRequests++;
                // $("#processingLoader").removeClass("d-none");
                // console.log('activeRequests GET FIRST JSON', activeRequests, 'isLoading', isLoading, 'isSelectedRow', isSelectedRow);
                $.ajax({
                    url: `${endpoint}`,
                    method: "GET",
                    dataType: "JSON",
                    headers: {
                        Authorization: `Bearer ${accessToken}`,
                    },
                    data: payload,
                    beforeSend: function (jqXHR) {
                        lastAjaxRequest = jqXHR; // simpan referensi
                    },
                    success: (response) => {
                        if (response.data.length === 0) {
                            // handleOnCancel(element);
                            // $(element).parent().find('input:hidden').val('')
                            $(element).parents().eq(1).find('input:hidden').val('')
                            showDialog(`DATA ${dataval} TIDAK DITEMUKAN`)
                            $(element).blur();
                            handleCloseLookup(element)
                            resolve([]); // Jika tidak ada data, tetap resolve
                        } else {
                            firstdata = response.data[0];
                            // currentSettings.onSelectRow(response.data[0], element);
                            if (Object.keys(currentSettings).length > 0) {
                                handleSelectedRow(
                                    firstdata.id,
                                    lookupContainer,
                                    element,
                                    true,
                                    firstdata,
                                    currentSettings
                                );
                            } else {
                                handleSelectedRow(
                                    firstdata.id,
                                    lookupContainer,
                                    element,
                                    true,
                                    firstdata
                                );
                            }

                            resolve(firstdata);
                        }
                    },
                    error: (error) => {
                        if (error.status === 422) {
                            $(".is-invalid").removeClass("is-invalid");
                            $(".invalid-feedback").remove();
                            setErrorMessages(form, error.responseJSON.errors);
                        } else {
                            showDialog(error.responseJSON);
                        }
                        reject(error); // Reject jika terjadi error
                    },
                }).always(() => {
                    // console.log('ALWAYS', activeRequests, 'isLoading', isLoading, 'isSelectedRow', isSelectedRow);

                    activeRequests--;
                    if (activeRequests <= 0) {
                        isLoading = false;
                        $("#processingLoader").addClass("d-none");
                        if (isSubmitClicked) {
                            $('#btnSubmit').click()
                            isSubmitClicked = false
                        } else if (isSubmitAddClicked) {
                            $('#btnSaveAdd').click()
                            isSubmitAddClicked = false
                        } else if (isReportClicked) {
                            $('#btnPreview').click()
                            isReportClicked = false
                        } else if (isExportClicked) {
                            if ($('#export').length == 0) {
                                $('#btnExport').click()
                            } else {
                                $('#export').click()
                            }
                            isExportClicked = false
                        } else if (isReportGridClicked) {
                            $('#report').click()
                            isReportGridClicked = false
                        }

                        activeRequests = 0
                    }
                });
            }
        });
    }

    function handleCloseLookup(elementToClose, openNewLookup = false, needClassActive = false) {
        if (!activate) return;

        let lookupContainer = elementToClose?.siblings(`#lookup-${elementToClose.attr("id")}`);
        let grid = lookupContainer.find(".lookup-grid");
        abortGridLastRequest($(grid));
        lookupContainer.hide();
        lookupContainer?.remove();
        oldElement = null;  // SETIAP CLOSE LOOKUP OLD ELEMENT DAN OLD SETTINGS LOOKUP DI RESET (kena case: enter lookup tapi gada yg ngosongin old lalu masuk get first dan handlecloselookup dan reset currentElementOpened sehingga pas buka lookup baru, lookup lama ga ketutup)
        oldSettings = {};   
        elementToClose.data("hasLookup", false);
        inputValue = '';
        clearGhost(elementToClose);
        if (!openNewLookup) {
            activate = false;
            currentElementOpened = '';
        }
        if (!needClassActive) {
            elementToClose?.removeClass('active');
        }
    }

    async function activateLookup(element) {
        let bottomSelected = 11;
        let topSelected = 0;
        elementInput = $(element)

        if (settings.autoComplete && settings.autoCompleteVersion != 'equalto') {
            elementInput.addClass('lookup-autocomplete');

            const parent = elementInput.closest('.input-group');
            parent.addClass('lookup-autocomplete-wrapper');

            if (!parent.find('.ghost-text').length) {
                parent.append('<div class="ghost-text"></div>');
            }
        }
        
        if (element.val()) {
            if (settings.autoCompleteVersion == 'startwith') {
                inputValue = `sw_activatelookup_${element.val()}`
            } else {
                inputValue = element.val()
            }
        } else {
            inputValue = '';
        }
        
        element.attr('suggestion', null);
        showOnButton = true
        // let indexRowSelectV4 = 1;
        isSelectedRow = false;
        filtersData = undefined
        isLoading = true;
        offsetWindowV4 = window.pageYOffset;
        selectedIdV4 = null;

        if (typeof settings.data === 'string') {
            resolveDataFromString(settings.data);
        }
        settings.beforeProcess();
        settings.onShowLookup();

        element.attr('data-lookup-name', settings.lookupName);
        $('.input-group').removeClass('active');
        element.addClass('active');

        const detail = settings.detail;
        const miniSize = settings.miniSize;
        const alignRightMobile = settings.alignRightMobile;
        const alignRight = settings.alignRight;
        let elementWidth = element.parent('.input-group').outerWidth();

        const width = element[0].offsetWidth;   // WIDHT KOLOM INPUT (!include button lookup)
        let getId = element.attr("id");
        let lookupContainer = element.siblings(`#lookup-${getId}`);

        if (lookupContainer.length === 0) {
            if (miniSize) {
                let detailElement = $(".overflow");
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
                } else {
                    lookupContainer = $(
                        `<div id="lookup-${getId}" style="position: absolute; background-color: #fff; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); z-index: 9999; top: 100%; width: ${elementWidth}px; max-height: 280px;  overscroll-behavior: contain!important;"></div>`
                    ).insertAfter(element);

                    if (alignRightMobile) {
                        $(`#lookup-${getId}`).css("right", "0");
                    }
                }
            } else {
                if (detail) {
                    let detailElement = $(".overflow");
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
                    } else {
                        lookupContainer = $(
                            '<div id="lookup-' +
                            getId +
                            '" style="position: absolute; background-color: #fff; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); z-index: 9999; top: 100%;  width: ' + elementWidth + 'px; max-height: 280px;   overscroll-behavior: contain!important; "></div>'
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
                    } else {
                        lookupContainer = $(
                            '<div id="lookup-' +
                            getId +
                            '" style="position: absolute; background-color: #fff; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); z-index: 9999; top: 100%; width: ' + elementWidth + 'px; max-height: 280px;  overscroll-behavior: contain!important;"></div>'
                        ).insertAfter(element);

                        if (alignRightMobile) {
                            $(`#lookup-${getId}`).css("right", "0");
                        }
                    }
                }
            }
        }

        lookupContainer.empty();

        let lookupBody = $(
            `<div class="lookup-body"> </div>`
        ).appendTo(lookupContainer);

        lookupBody.html(`
            <table id="${settings.lookupName}" class="lookup-grid"></table>

            <div class="loadingMessage" style="display:none">
                <img class="loading-image"
                    src="/trucking/public/libraries/tas-lib/img/loading-lookup.gif"
                    alt="Loading">
                <p class="loading-text">Loading data...</p>
            </div>
            `
        );

        initJqGrid(settings)

        let grid = lookupBody.find(".lookup-grid");
        element.data('filtersData', filtersData);
        $(".ui-jqgrid-bdiv").addClass("bdiv-lookup");
        $(".jqgrid-rownum").addClass("rowNum-lookup");

        //bind key
        if (grid.length > 0) {
            bindKey = false;
            //    keydownIndexV4++
            $(element).off("keydown.lookup");
            $(element).on("keydown.lookup", function (e) {
                if (!bindKey) {
                    if (
                        e.keyCode == 33 ||
                        e.keyCode == 34 ||
                        e.keyCode == 35 ||
                        e.keyCode == 36 ||
                        e.keyCode == 38 ||
                        e.keyCode == 40 ||
                        e.keyCode == 13 ||
                        e.keyCode == 27
                    ) {
                        e.preventDefault();

                        for (let index = 0; index < keydownIndexV4; index++) {
                            if (index == 0) { }
                        }

                        var gridIds = $(grid).getDataIDs();
                        var selectedRow = $(grid).getGridParam("selrow");
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
                                var inputElement = document.activeElement;
                                if (inputElement && inputElement.tagName === "INPUT") {
                                    inputElement.setSelectionRange(inputElement.value.length, inputElement.value.length);
                                }
                                return false;
                            }

                            if (
                                36 === e.keyCode &&
                                !e.shiftKey &&
                                !e.ctrlKey
                            ) {
                                var inputElement = document.activeElement;
                                if (inputElement && inputElement.tagName === "INPUT") {
                                    inputElement.setSelectionRange(0, 0);
                                }
                                return false;
                            }

                            //    if (38 === e.keyCode && isLookupOpenV4) {
                            //        $(grid).setSelection(
                            //            gridIds[currentIndex - 1]
                            //        );
                            //        element.focus();

                            //        var selectedRowId =
                            //            $(grid).getGridParam("selrow");

                            //        indexRowSelectV4 = $(grid).jqGrid(
                            //            "getInd",
                            //            selectedRowId
                            //        );

                            //        var currentRowHeight =
                            //            $(grid).getGridParam("rowHeight") ||
                            //            26;
                            //        var visibleRows =
                            //            $(grid).getGridParam(
                            //                "recordsView"
                            //            ) || 1;

                            //        var currentScrollTop = $(grid)
                            //            .closest(".ui-jqgrid-bdiv")
                            //            .scrollTop();

                            //     //    if (indexRowSelectV4 == topSelected) {
                            //         //    bottomSelected--;
                            //         //    topSelected--;
                            //         //    $(grid)
                            //         //        .closest(".bdiv-lookup")
                            //         //        .scrollTop(
                            //         //            currentScrollTop -
                            //         //            visibleRows *
                            //         //            currentRowHeight
                            //         //        );

                            //         // Misalnya grid adalah elemen jqGrid yang sedang kamu gunakan
                            //         // Ganti dengan ID atau elemen grid kamu
                            //         var selectedRowId = grid.jqGrid('getGridParam', 'selrow');  // Mendapatkan ID baris yang terpilih
                            //         var currentScrollTop = grid.closest(".ui-jqgrid-bdiv").scrollTop();  // Menyimpan posisi scroll saat ini

                            //         // Menambahkan baris baru atau melakukan operasi lain
                            //         // (Contoh operasi: menambah baris baru)


                            //         // Setelah operasi selesai, atur kembali posisi scroll
                            //         grid.closest(".ui-jqgrid-bdiv").scrollTop(currentScrollTop);

                            //         // Pastikan baris yang terpilih tetap terlihat setelah scroll
                            //         var rowOffset = grid.find(`#${selectedRowId}`).position().top;  // Posisi baris yang terpilih
                            //         var gridHeight = grid.closest(".ui-jqgrid-bdiv").height();  // Tinggi tampilan grid
                            //         // console.log(gridHeight)
                            //         // Jika baris terpilih berada di luar tampilan (terlalu jauh ke atas)
                            //         if (rowOffset > 0 || rowOffset < gridHeight) {
                            //             // Scroll ke atas untuk memastikan baris terpilih terlihat
                            //             grid.closest(".ui-jqgrid-bdiv").scrollTop(currentScrollTop + rowOffset);
                            //         }

                            //         // Pastikan baris tetap terpilih setelah scroll
                            //         grid.jqGrid('setSelection', selectedRowId);

                            //     //    }

                            //        return false;
                            //    }

                            if (38 === e.keyCode && activate) {                                
                                $(grid).setSelection(gridIds[currentIndex - 1]);

                                if (settings.autoComplete && inputValue) {
                                    handleAutoComplete(elementInput, grid, settings, true);   
                                }
                                
                                var currentRowHeight = grid.getGridParam("rowHeight") || 26;

                                // Mendapatkan posisi scroll saat ini
                                var currentScrollTop = $(grid)
                                    .closest(".ui-jqgrid-bdiv")
                                    .scrollTop();

                                // Mendapatkan ID baris yang terpilih
                                var selectedRowId = $(grid).getGridParam("selrow");

                                // Mengambil index baris terpilih saat ini
                                var indexRowSelectV4 = $(grid).jqGrid("getInd", selectedRowId);

                                var visibleRows = $(grid).getGridParam("recordsView") || 1;
                                var lastselectedrow = currentIndex - 1; // Update index untuk navigasi ke atas

                                // Fokuskan pada elemen
                                element.focus();

                                // Pastikan untuk menggeser scroll ke atas jika baris yang terpilih terlalu tinggi
                                // if (indexRowSelectV4 > 12) {

                                // Mendapatkan posisi baris terpilih
                                var rowOffset = grid.find(`#${selectedRowId}`).position().top;
                                var gridHeight = grid.closest(".ui-jqgrid-bdiv").height();

                                // Jika baris terpilih berada di luar tampilan (terlalu jauh ke atas)
                                // if (rowOffset < 0 || rowOffset > gridHeight) {
                                //     // Scroll untuk memastikan baris terpilih terlihat
                                //     grid.closest(".ui-jqgrid-bdiv").scrollTop(rowOffset - gridHeight / 2);
                                // }
                                // // console.log(rowOffset)
                                // if (rowOffset < 0) {
                                // Scroll ke atas untuk memastikan baris terpilih tetap terlihat

                                // grid.closest(".ui-jqgrid-bdiv").scrollTop(rowOffset);
                                grid.closest(".ui-jqgrid-bdiv").scrollTop(rowOffset - 200);
                                // iniatas yg udh berhasil yg bagi 2 yang bawah

                                // }

                                // Pastikan baris tetap terpilih setelah scroll
                                // grid.jqGrid('setSelection', selectedRowId);
                                // }

                                // Set flag lookup menjadi true
                                // isLookupOpenV4 = true;

                                return false; // Mencegah peristiwa lebih lanjut
                            }

                            if (40 === e.keyCode && activate) {

                                // if(filterPostData.tipeData == 'LOCAL') {
                                // if (currentIndex + 1 < gridIds.length) {
                                // $(grid)
                                //     .resetSelection()
                                //     .setSelection(gridIds[currentIndex + 1]);

                                var currentRowHeight =
                                    grid.getGridParam("rowHeight") || 26;

                                // var selInRow = $(grid).getGridParam("selrow");
                                // indexRowSelect = $(grid).jqGrid(
                                //     "getInd",
                                //     selInRow
                                // );

                                // // console.log('currentRowHeight', currentRowHeight)
                                // // console.log('element grid',$('#coaDariLookup').getGridParam("rowHeight"))


                                var currentScrollTop = $(grid)
                                    .closest(".ui-jqgrid-bdiv")
                                    .scrollTop();

                                // var recordsAll =
                                //     $(grid).getGridParam("records");


                                // }

                                $(grid).setSelection(gridIds[currentIndex + 1]);

                                if (settings.autoComplete && inputValue) {
                                    handleAutoComplete(elementInput, grid, settings, true);   
                                }

                                //    var currentRowHeight =
                                //        $(grid).getGridParam("rowHeight") ||
                                //        26;
                                var visibleRows =
                                    $(grid).getGridParam(
                                        "recordsView"
                                    ) || 1;

                                var selectedRowId =
                                    $(grid).getGridParam("selrow");
                                //    var selectedRowId = $(grid).jqGrid("getGridParam")
                                //    .selectedIndex++;

                                indexRowSelectV4 = $(grid).jqGrid(
                                    "getInd",
                                    selectedRowId
                                );

                                //    if (keydownIndexV4) {
                                //        indexRowSelectV4 = 1
                                //    }

                                var visibleSelRow = 0;

                                element.focus();

                                //    var currentScrollTop = $(grid)
                                //        .closest(".bdiv-lookup")
                                //        .scrollTop();

                                var lastselectedrow = currentIndex + 1


                                //    if (
                                //        indexRowSelectV4 == bottomSelected
                                //    ) {


                                //        visibleSelRow = 1;
                                //        bottomSelected++;
                                //        topSelected++;

                                //    }

                                //    if (visibleSelRow === 1) {
                                //        $(grid)
                                //            .closest(".bdiv-lookup")
                                //            .scrollTop(
                                //                currentScrollTop +
                                //                visibleRows *
                                //                currentRowHeight
                                //            );

                                //    }

                                if (indexRowSelectV4 > 12) {

                                    // $(grid)
                                    //     .closest(".ui-jqgrid-bdiv")
                                    //     .scrollTop(
                                    //         currentScrollTop + currentRowHeight + 2
                                    //     );

                                    var selectedRowId = grid.jqGrid('getGridParam', 'selrow'); // Mendapatkan ID baris yang terpilih
                                    var currentScrollTop = grid.closest(".ui-jqgrid-bdiv").scrollTop(); // Menyimpan posisi scroll saat ini

                                    // Menambahkan baris baru atau melakukan operasi lain
                                    // (Contoh operasi: menambah baris baru)


                                    // Setelah operasi selesai, atur kembali posisi scroll
                                    // grid.closest(".ui-jqgrid-bdiv").scrollTop(currentScrollTop);

                                    // Pastikan baris yang terpilih tetap terlihat setelah scroll
                                    var rowOffset = grid.find(`#${selectedRowId}`).position().top; //inikah?
                                    // waitt coba dulu
                                    var gridHeight = grid.closest(".ui-jqgrid-bdiv").height();

                                    // Jika baris terpilih tidak terlihat (terlalu jauh ke bawah atau ke atas)
                                    if (rowOffset < 0 || rowOffset > gridHeight) {
                                        // Scroll untuk memastikan baris terpilih terlihat

                                        grid.closest(".ui-jqgrid-bdiv").scrollTop(rowOffset - (gridHeight + 200) / 2);


                                    }

                                    // Pastikan baris tetap terpilih setelah scroll
                                    grid.jqGrid('setSelection', selectedRowId);
                                }


                                // isLookupOpenV4 = true;
                                return false;
                            }

                            if (13 === e.keyCode) {
                                let rowId = $(grid).getGridParam("selrow");
                                let ondblClickRowHandler = $(grid).jqGrid(
                                    "getGridParam",
                                    "ondblClickRow"
                                );

                                if (ondblClickRowHandler) {
                                    ondblClickRowHandler.call(
                                        $(grid)[0],
                                        rowId
                                    );
                                }

                                if (selectedIdV4) {
                                    handleSelectedRow(selectedIdV4, lookupContainer, element);
                                    activate = false;
                                    return false;
                                }
                                getFirst(settings.searching, lookupContainer, element, settings, 'ENTERR');
                                activate = false;
                                return false;
                            }

                            if (e.keyCode === 27) {
                                handleCloseLookup(element);
                                if (element.val() == '') {
                                    handleOnCancel(element);
                                }
                                return false;
                            }
                        }

                        $(".ui-jqgrid-bdiv").find("tbody").animate({
                            scrollTop: 200,
                        });
                        // $(".table-success").position().top > 300;
                    }

                    // DITAMBAHIN KEY CODE ARROW RIGHT DAN ARROW LEFT BIAR KALO ABIS ATAS BAWAH TRUS KIRI KANAN TRUS ATAS BAWAH LAGI DIA BISA MASUK KE EVENT ENTER
                    if (e.keyCode == 37 || e.keyCode == 39 || e.keyCode == 9 || e.keyCode == 16 || e.keyCode == 17|| e.keyCode == 18) {
                        bindKey = false;          
                    } else {
                        bindKey = true;
                    }
                }
            });
        }

        if (detectDeviceType() == "desktop") {
            grid.jqGrid("setGridParam", {
                onCellSelect: function (id) {
                    if (settings.autoSearch) return;

                    handleSelectedRow(id, lookupContainer, element);
                    element.focus();
                    activate = false;
                    bindKey = false;
                    isSelectedRow = true;
                },
                onSelectRow: function (id) {
                    if (settings.autoSearch) return;
                    selectedIdV4 = id;
                },
            });
        } else {
            grid.jqGrid("setGridParam", {
                onCellSelect: function (id) {
                    if (settings.autoSearch) return;
                    handleSelectedRow(id, lookupContainer, element);
                    element.focus();
                    activate = false;
                    bindKey = false;
                    isSelectedRow = true;
                },
            });
        }
        /* Determine user selection listener */        
        let windowOffset = window.pageYOffset;
        window.scrollTo(0, windowOffset);

        lookupContainer.show();
        let activeElement = null;

        if (!settings.selectedRequired && !settings.autoSearch) { // KALAU LOOKUP TIDAK WAJIB DIPILIH MANUAL
            $(document).off("mousedown.lookup");
            $(document).on("mousedown.lookup", function (event) {
                // Cek apakah elemen yang sedang aktif adalah elemen yang diklik
                const isActive = $(element).hasClass('active');
                if (isActive) {
                    if (!activeElement || activeElement[0] !== element[0]) {
                        activeElement = element;
                    }
                    let lookupContainer = activeElement.siblings(`#lookup-${activeElement.attr("id")}`);

                    // INI BUAT LOOKUP LAGI KEBUKA LALU KLIK DIMANA PUN KECUALI LOOKUP CONTAINER DAN BUTTON LOOKUP
                    if (!$(event.target).closest(lookupContainer).length && !$(event.target).closest(".input-group-append:has(.lookup-toggler)").length) {
                        if (settings.filterToolbar) {
                            const targetInputId = `#lookup-${event.target.id}`;
                            if (targetInputId == aktifIdV4) return;
                        }

                        handleCloseLookup(activeElement)
                        
                        // if (!(typeof activeElement.data("currentValue") == 'undefined' || activeElement.data("currentValue") == '') || activeElement.val() != '') {
                        if (activeElement.val() != '') {
                            // ini dipake kalau lagi ketik, tapi belum selesai, terus pindah ke ntah kemana asal bukan inputan lain (!deknasa)
                            getFirst(settings.searching, lookupContainer, activeElement, settings, "DARI KLIK LUAR CONTAINER")
                                .then((firstData) => {
                                    if (firstData) {
                                        // console.log("Data pertama:", firstData);
                                    } else {
                                        // console.log("Tidak ada data yang ditemukan.");
                                    }
                                }).catch((error) => {
                                    console.error("Terjadi kesalahan:", error);
                                })
                        }
                        activeElement = null;
                    } else if ($(event.target).closest('.input-group-append').length) {
                        if (oldElement != null && element != oldElement && oldElement?.val() != '') {  // INI BUAT LOOKUP LAGI KEBUKA DAN ADA VALUE INPUTAN LALU KLIK BUTTON LOOKUP LAIN
                            handleCloseLookup(oldElement, true)
                            let oldLookupContainer = oldElement.siblings(`#lookup-${oldElement.attr("id")}`)

                            getFirst(oldSettings.searching, oldLookupContainer, oldElement, oldSettings, 'FROM KLIK BUTTON LOOKUP LAIN')
                                .then((firstData) => {
                                    if (firstData) {
                                        // console.log("Data pertama:", firstData);
                                    } else {
                                        // console.log("Tidak ada data yang ditemukan.");
                                    }
                                }).catch((error) => {
                                    console.error("Terjadi kesalahan:", error);
                                })
                        } else if (oldElement != null && element == oldElement && element.val() != '') {  // INI BUAT LOOKUP LAGI KEBUKA DAN ADA VALUE INPUTAN LALU KLIK BUTTON LOOKUP DIRINYA SENDIRI
                            activeElement = null;
                            getFirst(settings.searching, lookupContainer, oldElement, settings, "DARI KLIK LUAR CONTAINER")
                                .then((firstData) => {
                                    if (firstData) {
                                        // console.log("Data pertama:", firstData);
                                    } else {
                                        // console.log("Tidak ada data yang ditemukan.");
                                    }
                                }).catch((error) => {
                                    console.error("Terjadi kesalahan:", error);
                                })
                        }
                    }
                    oldElement = null;
                    oldSettings = {};
                }
            });
        } else {
            // INI ELSE NYA UNTUK KLIK LUAR CONTAINER LOOKUP AUTO SEARCH (TAPI BUKAN SESAMA AUTO SEARCH)
            $(document).off("mousedown.lookup");
            $(document).on("mousedown.lookup", function (event) {
                if (activate) {
                    if (!$(event.target).closest(lookupContainer).length) {
                        handleCloseLookup(element)
                        oldElement = null;
                        oldSettings = {};

                        if (element.val() != '') {
                            handleAutoSearch(settings, element)
                        }
                    }
                }
            });
        }

        // Tambahkan kode berikut
        lookupContainer.on("hide", function () {
            if (lookupContainer === activeLookupElementV4) {
                activeLookupElementV4 = null;
            }
        });
        // windowOffset = window.pageYOffset;
    }

    // BUAT MERGE VALUE POST DATA DEFAULT (di lookup-columns.js) DENGAN POST DATA DI INIT (settings.postData)
    function mergeFilterPostData(initPostData, defaultPayload) {
        const result = { ...defaultPayload };

        const defaultKeyMap = Object.keys(defaultPayload).reduce((acc, key) => {
            acc[key.toLowerCase()] = key;
            return acc;
        }, {});

        Object.entries(initPostData).forEach(([key, value]) => {
            const lowerKey = key.toLowerCase();
            if (lowerKey === "url") return;
            if (value == 0) return;

            if (defaultKeyMap[lowerKey]) {
                result[defaultKeyMap[lowerKey]] = value;    // timpa value kalo key dari init udh ada di default
            } else {
                result[key] = value;    // key gada di default ditambahkan
            }
        });
        return result;
    }

    function finalLocalData(currentSettings) {
        let data = currentSettings._originalData ? currentSettings._originalData : currentSettings.data;

        if (typeof currentSettings.localFilter === 'function') {
            return currentSettings.localFilter(data, currentSettings.postData);
        }
        return data;
    }

    function resolveDataFromString(stringNameData) {
        if (stringNameData in window && window[stringNameData].length > 0) {
            settings.typeData = 'LOCAL';
            settings.data = window[stringNameData];
            return;
        }    
        return [];
    }

    function initJqGrid(settingsJqGrid) {        
        // console.log('AWAL KALI INIT JQGRID', settingsJqGrid.typeData, settingsJqGrid.data, typeof settingsJqGrid.data, settingsJqGrid);
        
        const getDefaultConfig = lookupConfigList(settingsJqGrid, elementInput[0].offsetWidth);
        const selector = $(`#${settingsJqGrid.lookupName}`)
        const column = settingsJqGrid.column.length > 0 ? settingsJqGrid.column : getDefaultConfig.column;
        const endpoint = settingsJqGrid.postData.url ? settingsJqGrid.postData.url : getDefaultConfig.url;
        filterPostData = mergeFilterPostData(settingsJqGrid.postData, getDefaultConfig.filterPostData)

        if (settingsJqGrid.typeData == 'LOCAL' && settingsJqGrid.data.length >= 0) {
            const data = finalLocalData(settingsJqGrid);
            selector.jqGrid({
                mtype: 'GET',
                styleUI: 'Bootstrap4',
                iconSet: 'fontAwesome',
                datatype: settingsJqGrid.typeData,
                postData: filterPostData,
                idPrefix: '',
                colModel: column,
                data: data,
                height: 350,
                fixed: true,
                rownumbers: false,
                rownumWidth: 0,
                rowNum: 10000,
                rowList: [10, 20, 50, 0],
                sortable: true,
                sortname: getDefaultConfig.sortname,
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
                    var title = settingsJqGrid.title ? settingsJqGrid.title : 'LOOKUP';

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

                    var labelColumn = settingsJqGrid.labelColumn;

                    if (!labelColumn) {
                        $(`#gbox_${idTop}`).find(".ui-jqgrid-hdiv").hide();
                    }

                    $(".ui-scroll-popup").addClass("d-none");
                    $(".modal-loader-content").addClass("d-none");
                },
                onSelectRow: function (id) {
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
                    if (settingsJqGrid.autoComplete && inputValue) {
                        $(this).data("allData", data.rows); // simpan data asli buat kebutuhan auto complete
                    }
                    $(".loadingMessage").hide();
                    idTop = selector.attr("id");

                    // var colModel = selector.jqGrid("getGridParam", "colModel");

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
                        // console.log("desktop");

                        // $(document).unbind('keydown')

                        initResize($(this));

                        let selectedIndex =
                            $(this).jqGrid("getGridParam").selectedIndex;

                        if (selectedIndex > $(this).getDataIDs().length - 1) {
                            selectedIndex = $(this).getDataIDs().length - 1;
                        }

                        if (selectedIndex < 0) {
                            selectedIndex = selectedIndex + 1;
                        }

                        if ($(this).jqGrid("getGridParam").triggerClick) {
                            $(this)
                                .find(`tr[id="${$(this).getDataIDs()[selectedIndex]}"]`)
                                .click();

                            $(this).jqGrid("setGridParam", {
                                triggerClick: false,
                            });
                        } else {
                            selector.setSelection(selector.getDataIDs()[selectedIndex])                                
                        }
                    }
                    
                    if (settingsJqGrid.autoComplete && inputValue) { 
                        handleAutoComplete(elementInput, $(this), settingsJqGrid);   
                    }

                    $(".clearsearchclass").click(function () {
                        clearColumnSearch($(this));
                    });

                    let currentInputWidth = elementInput.closest('.input-group').width();
                    if (currentInputWidth > 0) {
                        $(this).setGridWidth(currentInputWidth);
                    }
                    setHighlight($(this));
                    // $(this).jqGrid('setSelection', 1);
                },
            })
        } else {
            selector.jqGrid({
                url: endpoint,
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
                rowNum: settingsJqGrid.limit || 20,
                rowList: [10, 20, 50, 0],
                sortable: true,
                sortname: getDefaultConfig.sortname,
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
                serializeGridData: function (postData) {
                    searching = settingsJqGrid.searching || ''
                    searchText = `.` + settingsJqGrid.searchText

                    var colModel = $(this).jqGrid("getGridParam", "colModel"),
                        rules = [],
                        searchValue = $(searchText).val(),
                        i,
                        cm;
                    l = colModel.length

                    aksi = settingsJqGrid.aksi || ''

                    postData.sort_indexes = [postData.sort_index];
                    postData.sort_orders = [postData.sort_order];

                    input = $(searchText).data('input')

                    if (isToolbarSearch) {
                        colModel.forEach(function (cm) {
                            // var searchField = $("#gs_" + cm.name).val();
                            let searchField

                            if ($('#crudModal').length > 0) {
                                searchField = $('#crudModal').find("#gs_" + cm.name).val()
                            } else {
                                searchField = $("#gs_" + cm.name).val();
                            }

                            if (searchField && cm.search !== false && (cm.stype === undefined || cm.stype === "text")) {
                                currentElementOpened.val('')
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
                loadBeforeSend: function (jqXHR) {
                    $('.loadingMessage').show();
                    idTop = selector.attr('id')
                    $(`#load_${idTop}`).remove()

                    var title = settingsJqGrid.title ? settingsJqGrid.title : 'LOOKUP';

                    if (detectDeviceType() == 'mobile') {

                        $('.lookup-grid tr:not(.jqgfirstrow) td').css('padding', '12px')
                        $('.lookup-grid tr:not(.jqgfirstrow) td').css('font-size', '1rem')

                        $(`#gview_${idTop} .ui-th-column `).css('font-size', '1rem')


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

                    var labelColumn = settingsJqGrid.labelColumn

                    if (!labelColumn) {
                        $(`#gbox_${idTop}`).find('.ui-jqgrid-hdiv').hide()
                    }

                    $('.ui-scroll-popup').addClass('d-none')
                    $('.modal-loader-content').addClass('d-none')

                    jqXHR.setRequestHeader('Authorization', `Bearer ${accessToken}`)
                    
                    if (settingsJqGrid.lookupKey == 'upahsupirrincianV4') {
                        jenisKendaraan = settings.postData.statusjeniskendaraan || '';
                        if (jenisKendaraan == 'TANGKI') {
                            $("#upahsupirrincianLookup").jqGrid("hideCol", 'nominalsupir');
                        }
                    }
                    setGridLastRequest($(this), jqXHR)
                },
                // beforeProcessing: function (data) {
                //     if (!settingsJqGrid.autoComplete || !inputValue) return;
                    
                //     if (settingsJqGrid.autoComplete && settingsJqGrid.autoCompleteVersion == 'startwith' && inputValue) {                        
                //         let field = Array.isArray(settingsJqGrid.searching) ? settingsJqGrid.searching[0] : settingsJqGrid.searching;

                //         let filtered = data.data.filter(row => {
                //             const val = (row[field] || "").toUpperCase();
                //             return val.startsWith(inputValue.toUpperCase());
                //         });      

                //         return data.data = filtered;
                //     }
                //     data.data = data.data
                // },
                onSelectRow: function (id) {
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
                    // KHUSUS KEPERLUAN AUTO COMPLETE BUAT NYIMPEN DATA NYA YG JSON HARUS DI MERGE DENGAN DATA BARU DAN LAMA, KALO ENGGA DIA BAKAL NIMPA TRUS DENGAN DATA BARU
                    if (settingsJqGrid.autoComplete && inputValue) {
                        const oldData = $(this).data("allData") || [];
                        const newData = data.data || [];
                        const merged = [...oldData, ...newData];

                        const unique = merged.filter((item, index, self) =>
                            index === self.findIndex(t => t.id === item.id)
                        );

                        $(this).data("allData", unique);
                    }
                    
                    $('.loadingMessage').hide();
                    idTop = selector.attr('id')

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
                        // console.log('desktop');

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

                        } else {
                            var selectedRowId =selector.getGridParam("selrow");

                            if (!selectedRowId) {
                                selector.setSelection(selector.getDataIDs()[selectedIndex])
                            } 
                        }
                    }

                    if (settingsJqGrid.autoComplete && inputValue) {
                        handleAutoComplete(elementInput, $(this), settingsJqGrid);   
                    }

                    $('.clearsearchclass').click(function () {
                        clearColumnSearch($(this))
                    })

                    let currentInputWidth = elementInput.closest('.input-group').width();
                    if (currentInputWidth > 0) {
                        $(this).setGridWidth(currentInputWidth);
                    }
                    setHighlight($(this))
                },
            })
        }

        if (settingsJqGrid.filterToolbar) {
            if (detectDeviceType() == 'mobile') {
                $('.loadingMessage').css('top', '125%')
                $('.loading-text').css('margin-top', '13px')
            }
            selector.jqGrid('filterToolbar', { // BIKIN CONFIG FILTER TOOLBAR JQGRID NYA KALO settingsJqGrid.filterToolbar = true
                stringResult: true,
                searchOnEnter: false,
                defaultSearch: 'cn',
                groupOp: 'AND',
                beforeSearch: function () {
                    clearGhost(elementInput);
                    inputValue = '';
                    isToolbarSearch = true;

                    var postData = $(this).jqGrid("getGridParam", "postData");
                    postData.filters = "";
                    $(this).jqGrid("setGridParam", {
                        search: false
                    });

                    if (settingsJqGrid.typeData == 'JSON') {
                        $(searchText).val('');
                    }
                },
                afterSearch: function () {
                    isToolbarSearch = false;
                }
            });

            if (settingsJqGrid.typeData == 'LOCAL' && settingsJqGrid.data.length >= 0) {
                var colModel = selector.jqGrid("getGridParam", "colModel");
                let filterToolbarLocalResult
                const finalData = finalLocalData(settingsJqGrid)

                colModel.forEach(function (cm) {
                    if ($('#crudModal').length > 0) {
                        $('#crudModal').find(`[id*="gs_${cm.name}"]`).on("input", function () {
                            clearGhost(elementInput);
                            inputValue = '';
                            filterToolbarLocalResult = filterToolbarLocal(finalData, colModel);
                            selector
                                .clearGridData()
                                .setGridParam({ data: filterToolbarLocalResult })
                                .trigger("reloadGrid");
                        });

                        $("#crudModal").find(`#gsh_${settingsJqGrid.lookupName}_${cm.name} .clearsearchclass`).click(function () {
                            $('#crudModal').find(`[id*="gs_${cm.name}"]`).val('')
                            filterToolbarLocalResult = filterToolbarLocal(finalData, colModel);

                            selector
                                .clearGridData()
                                .setGridParam({ data: filterToolbarLocalResult })
                                .trigger("reloadGrid");
                        });
                    } else {
                        $(`#gs_${cm.name}`).on("input", function () {
                            clearGhost(elementInput);
                            inputValue = '';
                            filterToolbarLocalResult = filterToolbarLocal(finalData, colModel);
                            selector
                                .clearGridData()
                                .setGridParam({ data: filterToolbarLocalResult })
                                .trigger("reloadGrid");
                        });

                        $(`#gsh_${settingsJqGrid.lookupName}_${cm.name} .clearsearchclass`).click(function () {
                            $(`#gs_${cm.name}`).val('')
                            filterToolbarLocalResult = filterToolbarLocal(finalData, colModel);

                            selector
                                .clearGridData()
                                .setGridParam({ data: filterToolbarLocalResult })
                                .trigger("reloadGrid");
                        })
                    }
                });
            }
        }
    }

    function filterToolbarLocal(localDataFilterToolbar, colModel) {
        let filteredData = [...localDataFilterToolbar];        

        colModel.forEach(function (cm) {
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
                currentElementOpened.val('')
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

    function handleSelectedRow(id, lookupContainer, element, statusDataFirst = false, dataFirst = {}, oldSettings = {}) {
        // console.log('AWAL HANDLE SELECTED ROW', 'isLoading', isLoading, 'isSelectedRow', isSelectedRow, 'activeRequests', activeRequests, 'element', element, 'currentElementOpened', currentElementOpened, currentElementOpened.length);

        if (activeRequests > 0 || (currentElementOpened.length > 0 && element != currentElementOpened)) {
            isLoading = true;
        } else {
            isLoading = false;
        }

        if (currentElementOpened.length > 0 && element != currentElementOpened) {
            isSelectedRow = false;
        } else {
            isSelectedRow = true;
        }
        selectedIdV4 = null
        // isSelectedRow = true;
        // console.log('activeRequests END HANDLE SELECTED ROW', activeRequests, 'isLoading', isLoading, 'isSelectedRow', isSelectedRow);

        if (id !== null) {
            bottomSelected = 10;
            topSelected = 1;

            let rowData = sanitize(lookupContainer.find(".lookup-grid").getRowData(id));

            if (statusDataFirst) {
                rowData = dataFirst;
            }

            const obj = rowData;
            const array = Object.values(obj);

            element.val(rowData.name);

            if (array.length == 0) {
                element.val(element.data("currentValue"));
                lookupContainer.hide();
                return rowData;
            }

            if (Object.keys(oldSettings).length > 0) {
                oldSettings.onSelectRow(rowData, element);
            } else {
                settings.onSelectRow(rowData, element);
            }

            lookupContainer.hide();
            lookupContainer.remove();
            element.data("hasLookup", false);
            clearGhost(element);
            inputValue = '';
            // isLookupOpenV4 = false;            
        }
    }

    function handleOnCancel(element) {
        settings.onCancel(element);
        activate = false
    }

    function handleOnClear(element) {
        isSelectedRow = true
        activate = false
        let lookupContainer = element.siblings(`#lookup-${element.attr("id")}`);
        let grid = lookupContainer.find(".lookup-grid");

        let colMdl = grid.jqGrid("getGridParam", "colModel");
        if (lastAjaxRequest) {
            lastAjaxRequest.abort(); // lihat status terakhir
            // lastAjaxRequest = '';
        }
        settings.onClear(element);

        rules = [];
        // colMdl.forEach(function(cm) {
        //     $("#gs_" + cm.name).val("");
        // });

        grid.jqGrid("setGridParam", {
            postData: {
                filters: "",
            },
        });

        grid.trigger("reloadGrid", [{
            page: 1,
            current: true
        }]);
    }

    async function handleOnInputNew(element, searchValue, settingsInput) {
        let searching = settingsInput.searching;          
        let lookupContainer = element.siblings(`#lookup-${element.attr("id")}`);
        let grid = lookupContainer.find(".lookup-grid");
        if (grid.length == 0) {
            return;
        }
        let colModel = grid.jqGrid("getGridParam", "colModel");
        let postData = grid.jqGrid("getGridParam", "postData");
        let cm;
        let rules = [];
        abortGridLastRequest($(grid));

        input = element.data("input");
        colModel?.forEach(function (cm) {
            $("#gs_" + cm.name).val("");
        });

        if (searching.length == 0) {
            for (var i = 0; i < colModel.length; i++) {
                cm = colModel[i];

                if (
                    cm.search !== false && cm.hidden !== true &&
                    (cm.stype === undefined || cm.stype === "text")
                ) {
                    rules.push({
                        field: cm.name,
                        op: settingsInput.autoComplete && settingsInput.autoCompleteVersion == 'startwith' ? 'bw' : 'cn',
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

            grid.trigger("reloadGrid", [{
                page: 1,
                current: true,
            },]);

            return false;
        } else if (searching.length > 0) {            
            for (var i = 0; i < colModel.length; i++) {
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
                            // op: "cn", // Contains operation
                            op: settingsInput.autoComplete && settingsInput.autoCompleteVersion == 'startwith' ? 'bw' : 'cn',
                            data: searchValue.toUpperCase(),
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

            grid.trigger("reloadGrid", [{
                page: 1,
                current: true,
            },]);

            return false;
        }
    }

    async function handleOnPasteJSON(element, searchValue, settingsPasteJSON) {
        return new Promise((resolve, reject) => {
            activeRequests++;
            const getDefaultConfig = lookupConfigList(settingsPasteJSON);
            const defaultPostData = getDefaultConfig.filterPostData;
            const equalField = defaultPostData?.equalField ? defaultPostData?.equalField : getDefaultConfig.sortname;
            const endpoint = settingsPasteJSON.postData.url ? settingsPasteJSON.postData.url : getDefaultConfig.url;
            const mergedPostData = mergeFilterPostData(settingsPasteJSON.postData, defaultPostData)

            let rules = [{
                field: equalField,
                op: "cn",
                data: searchValue.toUpperCase(),
            }]

            let payload = {
                ...mergedPostData,
                filters: JSON.stringify({
                    groupOp: "EQUAL",
                    rules: rules
                })
            }

            $.ajax({
                url: `${endpoint}`,
                method: "GET",
                dataType: "JSON",
                headers: {
                    Authorization: `Bearer ${accessToken}`,
                },
                data: payload,
                beforeSend: function (jqXHR) {
                    lastAjaxRequest = jqXHR; // simpan referensi
                },
                success: (response) => {
                    if (response.data.length === 0) {
                        if (settingsPasteJSON.autoSearch) {
                            const returnData = {
                                id: 999,
                                [equalField]: searchValue
                            }
                            settingsPasteJSON.onSelectRow(returnData, element);
                            resolve(returnData);
                        } else {
                            // handleOnCancel(element);
                            // $(element).parent().find('input:hidden').val('')
                            $(element).parents().eq(1).find('input:hidden').val('')
                            showDialog(`DATA ${searchValue} TIDAK DITEMUKAN`)
                            $(element).blur();
                            resolve([]); // Jika tidak ada data, tetap resolve
                        }
                    } else {
                        if (settingsPasteJSON.autoSearch) {
                            $(element).parents().eq(1).find('input:hidden').val('')
                            showDialog(`DATA ${searchValue} SUDAH ADA`)
                            $(element).blur();
                            resolve([]); // Jika tidak ada data, tetap resolve
                        } else {
                            settingsPasteJSON.onSelectRow(response.data[0], element);
                            resolve(response.data[0]);
                        }
                    }
                },
                error: (error) => {
                    if (error.status === 422) {
                        $(".is-invalid").removeClass("is-invalid");
                        $(".invalid-feedback").remove();
                        setErrorMessages(form, error.responseJSON.errors);
                    } else {
                        showDialog(error.responseJSON);
                    }
                    reject(error); // Reject jika terjadi error
                },
            }).always(() => {

                activeRequests--;
                if (activeRequests <= 0) {
                    isLoading = false;
                    $("#processingLoader").addClass("d-none");
                    if (isSubmitClicked) {
                        $('#btnSubmit').click()
                        isSubmitClicked = false
                    } else if (isSubmitAddClicked) {
                        $('#btnSaveAdd').click()
                        isSubmitAddClicked = false
                    } else if (isReportClicked) {
                        $('#btnPreview').click()
                        isReportClicked = false
                    } else if (isExportClicked) {
                        if ($('#export').length == 0) {
                            $('#btnExport').click()
                        } else {
                            $('#export').click()
                        }
                        isExportClicked = false
                    } else if (isReportGridClicked) {
                        $('#report').click()
                        isReportGridClicked = false
                    }

                    activeRequests = 0
                }
            });
        });
    }

    function handleOnPasteLocal(element, searchValue, settingsPasteLocal) {
        const getDefaultConfig = lookupConfigList(settings);
        const defaultPostData = getDefaultConfig.filterPostData;
        const equalField = defaultPostData?.equalField ? defaultPostData?.equalField : getDefaultConfig.sortname;
        const data = finalLocalData(settingsPasteLocal)
        // console.log('data DI HANDLE ONPASTE LOCAL', data);

        let dataPasteResult = data.filter(res =>
            res[equalField].toString().toLowerCase() === searchValue.toLowerCase()
        );

        if (dataPasteResult.length === 0) {
            if (settingsPasteLocal.autoSearch) {
                const returnData = {
                    id: 999,
                    [equalField]: searchValue
                }
                settingsPasteLocal.onSelectRow(returnData, element);
            } else {
                $(element).parents().eq(1).find('input:hidden').val('')
                showDialog(`DATA ${searchValue} TIDAK DITEMUKAN`)
            }
        } else {
            if (settingsPasteLocal.autoSearch) {
                $(element).parents().eq(1).find('input:hidden').val('')
                showDialog(`DATA ${searchValue} SUDAH ADA`)
            } else {
                settingsPasteLocal.onSelectRow(dataPasteResult[0], element);
            }
        }
        if (activeRequests <= 0) {
            isLoading = false;
        }
    }

    function handleAutoSearch(settingsAutoSearch, elementAutoSearch) {
        if (settingsAutoSearch.typeData == 'LOCAL' && settingsAutoSearch.data.length >= 0) {
            handleOnPasteLocal(elementAutoSearch, elementAutoSearch.val(), settingsAutoSearch);
        } else {
            handleOnPasteJSON(elementAutoSearch, elementAutoSearch.val(), settingsAutoSearch);
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

