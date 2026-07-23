const serialize = function (obj) {
	var str = [];
	for (var p in obj)
		if (obj.hasOwnProperty(p)) {
			str.push(encodeURIComponent(p) + "=" + encodeURIComponent(obj[p]));
		}
	return str.join("&");
};

const getLookup = function (fileName, postData) {
	return new Promise((resolve, reject) => {
		$.ajax({
			url: `${appUrl}/lookup/${fileName}?${serialize(postData)}`,
			method: "GET",
			dataType: "html",
			success: function (response) {
				resolve(response);
			},
		});
	});
};

$.fn.lookup = function (options) {
	let defaults = {
		title: null,
		fileName: null,
		beforeProcess: function () {},
		onShowLookup: function (rowData, element) {},
		onSelectRow: function (rowData, element) {},
		onCancel: function (element) {},
		onClear: function (element) {},
	};

	let settings = $.extend({}, defaults, options);

	this.each(function () {
		let element = $(this);

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

		element
			.siblings(".input-group-append")
			.find(".lookup-toggler")
			.click(async function () {
				activateLookup(element, element.val());
			});

		element.siblings(".button-clear").click(function () {
			handleOnClear(element);
		});

		element.on("input", function (event) {
			delay(function () {
				activateLookup(element, element.val());
			}, 500);
		});

		element.on("keydown", function (event) {
			if (event.keyCode === 115) {
				activateLookup(element, element.val());
			}
		});
	});

	async function activateLookup(element, searchValue = null) {
		settings.beforeProcess();
		settings.onShowLookup();

		let lookupModal = $(`
      <div class="modal modal-lookup" id="lookupModal" tabindex="-1" aria-labelledby="lookupModalLabel" aria-hidden="true">
        <div class="modal-dialog">
          <form action="#" id="crudForm">
            <div class="modal-content">
              <div class="modal-header">
                <p class="modal-title" id="lookupModalLabel">${settings.title}</p>
                <button type="button" class="close close-button" data-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body" style="min-height: 680px;">
              </div>
              <div class="modal-footer">
                <div class="mr-auto">
                  <button type="button" class="btn btn-secondary close-button" data-dismiss="modal" aria-label="Close">
                  Close
                  </button>
                </div>
              </div>
            </div>
          </form>
        </div>
      </div>
    `);

		$("body").append(lookupModal);

		lookupModal.modal("show");

		getLookup(settings.fileName, settings.postData ?? null).then((response) => {
			lookupModal.find(".modal-body").html(response);

			grid = lookupModal.find(".lookup-grid");

			/* Insert searchValue to global search input */
			if (searchValue) {
				setTimeout(() => {
					lookupModal
						.find(".global-search")
						.val(searchValue)
						.trigger("input")
						.focus();
				}, 500);
			} else {
				lookupModal.find(".global-search").focus();
			}

			/* Determine user selection listener */
			if (detectDeviceType() == "desktop") {
				grid.jqGrid("setGridParam", {
					ondblClickRow: function (id) {
						handleSelectedRow(id, lookupModal, element);
					},
				});
			} else if (detectDeviceType() == "mobile") {
				grid.jqGrid("setGridParam", {
					onSelectRow: function (id) {
						handleSelectedRow(id, lookupModal, element);
					},
				});
			}
		});

		lookupModal.on("hidden.bs.modal", function () {
			lookupModal.remove();
			element.focus();
			let isInModal = $(element).closest('.modal').length > 0; 
			if (isInModal) { 
				initDatepicker()
			}
		});

		$(document)
			.find(lookupModal)
			.find(".close-button")
			.on("click", function () {
				handleOnCancel(element);
			});

		$(document)
			.find(lookupModal)
			.on("keydown", function (event) {
				if (event.which === 27) {
					handleOnCancel(element);
				}
			});
	}

	function handleSelectedRow(id, lookupModal, element) {
		if (id !== null) {
			lookupModal.modal("hide");

			settings.onSelectRow(sanitize(grid.getRowData(id)), element);
		} else {
			alert("Please select a row");
		}
	}

	function handleOnCancel(element) {
		settings.onCancel(element);
	}

	function handleOnClear(element) {
		settings.onClear(element);
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
