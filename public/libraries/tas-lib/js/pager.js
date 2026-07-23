function loadPagerHandler(element, grid) {
    $(element).html(`
		<button type="button" id="${
            grid.getGridParam().id
        }_firstPageButton" class="btn btn-sm hover-primary mr-2 d-flex">
			<span class="fas fa-step-backward"></span>
		</button>

		<button type="button" id="${
            grid.getGridParam().id
        }_previousPageButton" class="btn btn-sm hover-primary d-flex">
			<span class="fas fa-backward"></span>
		</button>

		<div class="d-flex align-items-center my-1  justify-content-between gap-10">
			<span>Page</span>
			<input id="${grid.getGridParam().id}_pagerInput" class="pager-input" value="${
        grid.getGridParam().page
    }">
			<span id="${grid.getGridParam().id}_totalPage">of ${
        grid.getGridParam().lastpage
    }</span>
		</div>

		<button type="button" id="${
            grid.getGridParam().id
        }_nextPageButton" class="btn btn-sm hover-primary d-flex">
			<span class="fas fa-forward"></span>
		</button>

		<button type="button" id="${
            grid.getGridParam().id
        }_lastPageButton" class="btn btn-sm hover-primary ml-2 d-flex">
			<span class="fas fa-step-forward"></span>
		</button>

		<select id="${grid.getGridParam().id}_rowList" class="ml-2">
			${grid
                .getGridParam()
                .rowList.map((row, index) => {
                    return `<option value="${row}">${row}</option>`;
                })
                .join("")}
		</select>
	`);

    // Definisikan semua selector tombol dalam variabel
    let firstBtn = `#${grid.getGridParam().id}_firstPageButton`;
    let prevBtn = `#${grid.getGridParam().id}_previousPageButton`;
    let nextBtn = `#${grid.getGridParam().id}_nextPageButton`;
    let lastBtn = `#${grid.getGridParam().id}_lastPageButton`;
    let inputPager = `#${grid.getGridParam().id}_pagerInput`;
    let rowList = `#${grid.getGridParam().id}_rowList`;

    // Lepas event 'click' lama, lalu pasang yang baru
    $(document).off('click', firstBtn).on('click', firstBtn, function () {
        toFirstPage(grid);
    });

    $(document).off('click', prevBtn).on('click', prevBtn, function () {
        toPreviousPage(grid);
    });

    $(document).off('click', nextBtn).on('click', nextBtn, function () {
        toNextPage(grid);
    });

    $(document).off('click', lastBtn).on('click', lastBtn, function () {
        toLastPage(grid);
    });

    // Lepas event 'keydown' lama, lalu pasang yang baru
    // $(document).off('keydown', inputPager).on('keydown', inputPager, function (event) {
    //     if (event.which === 13) {
    //         jumpToPage(grid, $(this).val());
    //     }
    // });

    // // Lepas event 'change' lama, lalu pasang yang baru
    // $(document).off('change', rowList).on('change', rowList, function (event) {
    //     setPerPage(grid, $(this).val());
    // });

    // $(document).on(
    //     "click",
    //     `#${grid.getGridParam().id}_firstPageButton`,
    //     function () {
    //         toFirstPage(grid);
    //     }
    // );

    // $(document).on(
    //     "click",
    //     `#${grid.getGridParam().id}_previousPageButton`,
    //     function () {
    //         toPreviousPage(grid);
    //     }
    // );

    // $(document).on(
    //     "click",
    //     `#${grid.getGridParam().id}_nextPageButton`,
    //     function () {
    //         // console.log("baca lah")
    //         toNextPage(grid);
    //     }
    // );

    // $(document).on(
    //     "click",
    //     `#${grid.getGridParam().id}_lastPageButton`,
    //     function () {
    //         toLastPage(grid);
    //     }
    // );

    $(`#${grid.getGridParam().id}_pagerInput`).keydown(function (event) {
        if (event.which === 13) {
            jumpToPage(grid, $(this).val());
        }
    });

    $(`#${grid.getGridParam().id}_rowList`).change(function (event) {
        setPerPage(grid, $(this).val());
    });
}

function toNextPage(grid) {
    let currentPage = grid.getGridParam().page;
    let lastPage = grid.getGridParam("lastpage");
    let nextPage = parseInt(currentPage) + 1;
    if (nextPage <= lastPage) {
        grid.setGridParam({
            page: nextPage,
            postData: {
                proses: "page",
            },
        }).trigger("reloadGrid");
    }
}

function toLastPage(grid) {
    let lastPage = grid.getGridParam("lastpage");
    let currentPage = grid.getGridParam("page");

    if (currentPage < lastPage) {
        grid.setGridParam({
            page: lastPage,
            postData: {
                proses: "page",
            },
        }).trigger("reloadGrid");
    }
}

function toPreviousPage(grid) {
    let currentPage = grid.getGridParam().page;

    if (currentPage > 1) {
        grid.setGridParam({
            page: parseInt(currentPage) - 1,
            postData: {
                proses: "page",
            },
        }).trigger("reloadGrid");
    }
}

function toFirstPage(grid) {
    let currentPage = grid.getGridParam("page");

    if (currentPage > 1) {
        grid.setGridParam({
            page: 1,
            postData: {
                proses: "page",
            },
        }).trigger("reloadGrid");
    }
}

function jumpToPage(grid, page) {
    grid.setGridParam({
        page: page,
        postData: {
            proses: "page",
        },
    }).trigger("reloadGrid");

    // grid.trigger("reloadGrid", [
    // 	{
    // 		page: page,
    // 	},
    // ]);
}

function setPerPage(grid, perPage) {
    grid.setGridParam({
        rowNum: perPage,
        page: 1,
        postData: {
            proses: "page",
        },
    }).trigger("reloadGrid");
}

function loadPagerHandlerInfo(element, grid) {
    let page = grid.getGridParam().page;
    let totalPage = grid.getGridParam().lastpage;

    $(element).find(`#${grid.getGridParam().id}_pagerInput`).val(page);
    $(element)
        .find(`#${grid.getGridParam().id}_totalPage`)
        .text(`of ${totalPage}`);
}

function loadPagerInfo(element, grid) {
    let params = grid.getGridParam();
    let recordCount = params.reccount;
    let page = params.page;
    let perPage = params.rowNum;
    let totalRecords = params.records;
    let firstRow = (page - 1) * perPage + 1;
    let lastRow = firstRow + recordCount - 1;

    $(element).html(`
		<div class="text-md-right">
			View  ${firstRow} - ${lastRow} of ${totalRecords}
		</div>
	`);
}
function shrinkTextToFit(button) {
  let fontSize = 16; // ukuran awal
  button.style.fontSize = fontSize + "px";

  const maxHeight = button.clientHeight - 4;
  const maxWidth = button.clientWidth - 8;

  console.log('button.scrollHeight', button.scrollHeight, maxHeight, button.scrollWidth, maxWidth, button)
  // selama teksnya overflow, perkecil font-nya
  while (
    (button.scrollHeight > maxHeight || button.scrollWidth > maxWidth) &&
    fontSize > 12
  ) {
    fontSize -= 1;
    button.style.fontSize = fontSize + "px";
  }
}


$.fn.customPager = function (option = {}) {
    if (
        !$(`#gbox_${$(this).getGridParam().id}`).siblings(".grid-pager").length
    ) {
        let grid = $(this);
        let pagerHandlerId = `${grid.getGridParam().id}PagerHandler`;
        let pagerInfoId = `${grid.getGridParam().id}InfoHandler`;
        let modalBtnList = "";
        let extndBtn = "";
        let extndBtnMobile = "";
        if (option.extndBtn) {
            option.extndBtn.forEach((element) => {
                if (element.class.indexOf("dropdown-toggle") != -1) {
                    extndBtn += `<div class="btn-group dropup  scrollable-menu">`;
                    extndBtn += `<button type="button" class="${element.class}" data-toggle="dropdown" id="${element.id}">
					${element.innerHTML}
					</button>`;
                    extndBtn += `<ul class="dropdown-menu dropdown-extndbtn" id="menu-${element.id}" aria-labelledby="${element.id}">`;

                    if (element.dropmenuHTML) {
                        element.dropmenuHTML.forEach((dropmenuHTML) => {
                            extndBtn += `<li><a class="dropdown-item border-dropdown-extndbtn" id='${dropmenuHTML.id}' href="#">${dropmenuHTML.text}</a></li>`;
                            $(document).on(
                                "click",
                                `#${dropmenuHTML.id}`,
                                function (event) {
                                    event.stopImmediatePropagation();
                                    dropmenuHTML.onClick();
                                }
                            );
                        });
                    }
                    extndBtn += `</ul>`;
                    extndBtn += "</div>";

                    $(document).on("click", `#${element.id}`, function () {
                        console.log("onclicked");
                        if (detectDeviceType() == "mobile") {
                            let menuapprove = $(`#menu-${element.id}`);
                            $(`#menu-${element.id}`).remove();
                            menuapprove.insertBefore("#left-nav");

                            let widthDropdown = Math.ceil(
                                $(`#menu-${element.id}`).width()
                            );
                            let leftNavWidth = Math.ceil(
                                $("#left-nav").width()
                            );
                            let kurang = leftNavWidth - widthDropdown;
                            let widthX = 0;
                            if (kurang > 15) {
                                widthX = '-'+ (kurang / 2);
                            }
                            if(kurang < -10){
                                let getNavOffset = $("#left-nav").offset()
                                console.log( getNavOffset.left, Math.abs(kurang))
                                widthX = (Math.abs(kurang) + getNavOffset.left)/2
                            }

                            let leftNavHeight = $("#left-nav").height();

                            $(`#${element.id}`).on("click", function () {
                                let widthDropdown = Math.ceil(
                                    $(`#menu-${element.id}`).width()
                                );
                                let leftNavWidth = Math.ceil(
                                    $("#left-nav").width()
                                );
                                let kurang = leftNavWidth - widthDropdown;
                                let widthX = 0;
                                if (kurang > 15) {
                                    widthX = kurang / 2;
                                }

                                let leftNavHeight = $("#left-nav").height();

                                // Your event handler code here
                                setTimeout(function () {
                                    $(`#menu-${element.id}`).css({
                                        // transform: `translate3d(${widthX}px, 0px, 0px)`,
                                        top: `-${leftNavHeight}px`,
                                        left: `-${widthX}px`,
                                    });
                                }, 0);
                            });

                            setTimeout(function () {
                                $(`#menu-${element.id}`).css({
                                    // transform: `translate3d(${widthX}px, 0px, 0px)`,
                                    top: `-${leftNavHeight}px`,
                                    left: `${widthX}px`,
                                });
                            }, 0);
                        }
                    });
                } else {
                    let buttonElement = document.createElement("button");
                    buttonElement.id =
                        typeof element.id !== "undefined"
                            ? element.id
                            : `customButton_${index}`;
                    buttonElement.className = element.class;
                    buttonElement.innerHTML = element.innerHTML;
                    if (element.onClick) {
                        $(document).on(
                            "click",
                            `#${buttonElement.id}`,
                            function (event) {
                                event.stopImmediatePropagation();
                                element.onClick();
                            }
                        );
                    }
                    extndBtn += buttonElement.outerHTML;
                }
            });
        }
        if (option.modalBtnList) {

            option.modalBtnList.forEach((element) => {
                let buttonElement = document.createElement("button");
                buttonElement.id =
                    typeof element.id !== "undefined"
                        ? element.id
                        : `customButton_${index}`;
                let hasItem = element.item ? '<i class="ml-1 fa fa-angle-up"></i>': ''
                buttonElement.className = element.class;
                buttonElement.innerHTML = element.innerHTML+hasItem;
                let targetModal = element.targetModal ?  element.targetModal :'#listMenuModal'
                if (targetModal && element.item) {
                    $(document).on(
                        "click",
                        `#${buttonElement.id}`,
                        function (event) {
                            
                            if (buttonElement.id == 'approve') { 
                                $(`${targetModal}`).addClass('modalLainnya'); 
                            } else {
                                $(`${targetModal}`).removeClass('modalLainnya'); 
                            }
                            $(targetModal).modal('show')

                            if (element.item) {
                                let groupedItems = {};

                                // kelompokkan hanya berdasarkan group
                                element.item.forEach((dropmenuHTML) => {
                                    let groupKey = dropmenuHTML.group; 
                                    if (!groupedItems[groupKey]) groupedItems[groupKey] = [];
                                    groupedItems[groupKey].push(dropmenuHTML);
                                });
                                let listItem = `<div class="row">`
                                // element.item.forEach((dropmenuHTML) => {
                                //     let hidden = dropmenuHTML.hidden ?  'hidden' :''
                                //     // console.log(dropmenuHTML.id ,dropmenuHTML.hidden,hidden);
                                //     let colorbuton = dropmenuHTML.color ?  dropmenuHTML.color :'btn-danger'
                                //     let iconbuton = dropmenuHTML.icon ?  dropmenuHTML.icon :'fa-globe'
                                //     listItem += `<div class="col-12 mt-2" ${hidden}>
                                //         <button  class="btn ${colorbuton} btn-lg btn-block item-menu" id="${dropmenuHTML.id}"><i class="fa ${iconbuton}"></i> ${dropmenuHTML.text.toUpperCase()}</button>
                                //     </div>`;
                                // })
                                // listItem += `</div>`
                                // $(targetModal).find('.modal-body').html(listItem)

                                // element.item.forEach((dropmenuHTML) => {
                                //     if (dropmenuHTML.onClick) {
                                //         $(document).on(
                                //             "click",
                                //             `#${dropmenuHTML.id}`,
                                //             function (event) {
                                //                 event.stopImmediatePropagation();

                                //                 dropmenuHTML.onClick();
                                //             }
                                //         );
                                //     }
                                // })
                                Object.values(groupedItems).forEach((group) => {
                                    // console.log('group', group, group.length, group.length === 1 || group[0].group === undefined)

                                    // jika grup hanya berisi 1 item atau itu grup tanpa nama (_no_group_)
                                    if (group.length === 1 || group[0].group === undefined) {
                                        group.forEach((dropmenuHTML) => {
                                            let hidden = dropmenuHTML.hidden ? "hidden" : "";
                                            let colorbuton = dropmenuHTML.color || "btn-danger";
                                            let iconbuton = dropmenuHTML.icon || "fa-globe";
                                            listItem += `
                                            <div class="col-12 mt-2" ${hidden}>
                                                <button class="btn ${colorbuton} btn-lg btn-block item-menu" id="${dropmenuHTML.id}">
                                                ${dropmenuHTML.text.toUpperCase()} 
                                                </button>
                                            </div>`;
                                        })
                                    } else {
                                        // kalau grup punya lebih dari 1 item → tampilkan berdampingan
                                        let semuaHidden = group.every(x => x.hidden === true);
                                        if (semuaHidden) return; // skip row, kalau sepaket hidden, di skip barisnya

                                        group.forEach((dropmenuHTML) => {
                                            let disabled = dropmenuHTML.hidden ? "disabled" : "";
                                            let colorbuton = dropmenuHTML.color || "btn-danger";
                                            let iconbuton = dropmenuHTML.icon || "fa-globe";
                                            listItem += `
                                                <div class="col-12 col-sm-6 mt-2" >
                                                <button class="btn ${colorbuton} btn-lg btn-block item-menu item-menu-group" id="${dropmenuHTML.id}" ${disabled}>
                                                ${dropmenuHTML.text.toUpperCase()}
                                                </button>
                                                </div>`;
                                        });
                                    }
                                });

                                listItem += `</div>`;
                                $(targetModal).find(".modal-body").html(listItem);
                                // $(targetModal).on("shown.bs.modal", function () {
                                //     $(this)
                                //         .find(".item-menu-group")
                                //         .each(function () {
                                //             shrinkTextToFit(this);
                                //         });
                                // });
                                // event click handler
                                element.item.forEach((dropmenuHTML) => {
                                    if (dropmenuHTML.onClick) {
                                        $(document).on("click", `#${dropmenuHTML.id}`, function (event) {
                                        event.stopImmediatePropagation();
                                        dropmenuHTML.onClick();
                                        });
                                    }
                                });

                            }
                            $( ".item-menu" ).on( "click", function() {
                                $(targetModal).modal('hide')

                            });
                        }
                    );
                }else{
                    if (element.onClick) {
                        $(document).on(
                            "click",
                            `#${buttonElement.id}`,
                            function (event) {
                                event.stopImmediatePropagation();

                                element.onClick();
                            }
                        );
                    }
                }
                modalBtnList += buttonElement.outerHTML;
            });
        }

        if (detectDeviceType() == "desktop") {
            $(`#gbox_${$(this).getGridParam().id}`).after(`
			<div class=" bg-white grid-pager overflow-x-hidden">
				<div class="row d-flex align-items-center text-lg-left">
					<div class="col-8 id="left-nav">
						${
                            typeof option.buttons !== "undefined"
                                ? option.buttons
                                      .map((button, index) => {
                                          let buttonElement =
                                              document.createElement("button");

                                          buttonElement.id =
                                              typeof button.id !== "undefined"
                                                  ? button.id
                                                  : `customButton_${index}`;
                                          buttonElement.className =
                                              button.class;
                                          buttonElement.innerHTML =
                                              button.innerHTML;

                                          if (button.onClick) {
                                              $(document).on(
                                                  "click",
                                                  `#${buttonElement.id}`,
                                                  function (event) {
                                                        if(buttonElement.id == 'add' || buttonElement.id == 'edit' || buttonElement.id == 'delete' || buttonElement.id == 'view') {
                                                            $(".modal-loader").removeClass("d-none");
                                                        }
                                                      event.stopImmediatePropagation();

                                                      button.onClick();
                                                  }
                                              );
                                          }

                                          return buttonElement.outerHTML;
                                      })
                                      .join("")
                                : ""
                        }
							${modalBtnList}
							${extndBtn}
					</div>
					<div class="col-4">
						<div class="row d-flex align-items-center justify-content-center justify-content-lg-end pr-3">
							<div id="${pagerHandlerId}" class="pager-handler d-flex align-items-center justify-content-center mx-2">
							</div>
							<div id="${pagerInfoId}" class="pager-info">
							</div>
						</div>
					</div>
				</div>
			</div>

		`);
        } else {
            $(`#gbox_${$(this).getGridParam().id}`).after(`
				<div class="row d-flex align-items-center grid-overflow text-lg-left">

					<div class="col-12 col-lg-6" id="left-nav" style="overflow: hidden; ">
						<div style="display: flex; overflow-x: auto; padding: 5px; white-space: nowrap; background-color:white;">

							${
                                typeof option.buttons !== "undefined"
                                    ? option.buttons
                                          .map((button, index) => {
                                              let buttonElement =
                                                  document.createElement(
                                                      "button"
                                                  );
                                              buttonElement.id =
                                                  typeof button.id !==
                                                  "undefined"
                                                      ? button.id
                                                      : `customButton_${index}`;
                                              buttonElement.className =
                                                  button.class;
                                              buttonElement.style.setProperty(
                                                  "margin-right",
                                                  "6px",
                                                  "important"
                                              );
                                              buttonElement.innerHTML =
                                                  button.innerHTML;

                                              if (button.onClick) {
                                                  $(document).on(
                                                      "click",
                                                      `#${buttonElement.id}`,
                                                      function (event) {
                                                            if(buttonElement.id == 'add' || buttonElement.id == 'edit' || buttonElement.id == 'delete' || buttonElement.id == 'view') {
                                                            $(".modal-loader").removeClass("d-none");
                                                            }
                                                          event.stopImmediatePropagation();

                                                          button.onClick();
                                                      }
                                                  );
                                              }

                                              return buttonElement.outerHTML;
                                          })
                                          .join("")
                                    : ""
                            }
                            ${modalBtnList}
							${extndBtn}
						</div>
					</div>
				</div>

					<div class="col-12 col-lg-6">
						<div class="row d-flex align-items-center justify-content-center justify-content-lg-end pr-3" style="background-color:white;">
							<div id="${pagerHandlerId}" class="pager-handler d-flex align-items-center justify-content-center mx-2">
							</div>
							<div id="${pagerInfoId}" class="pager-info">
							</div>
						</div>
					</div>
				</div>
			</div>


		`);
        }

        $(`#gbox_${$(this).getGridParam().id}`)
            .siblings(".grid-overflow")
            .find("button")
            .removeClass("btn-sm");
        $(`#gbox_${$(this).getGridParam().id}`)
            .siblings(".grid-pager")
            .find("button")
            .removeClass("btn-sm");

        if(typeof option.lazyLoading == 'undefined') {
            loadPagerHandler(`#${pagerHandlerId}`, grid);
            grid.bind("jqGridLoadComplete.jqGrid", function (event, data) {
                loadPagerHandlerInfo(`#${pagerHandlerId}`, grid);
                loadPagerInfo(`#${pagerInfoId}`, grid);
            });
        }

        // if (detectDeviceType() == "desktop") {

        // }

    }

    return this;
};
