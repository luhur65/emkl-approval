// const serialize = function (obj) {
// 	var str = [];
// 	for (var p in obj)
// 		if (obj.hasOwnProperty(p)) {
// 			str.push(encodeURIComponent(p) + "=" + encodeURIComponent(obj[p]));
// 		}
// 	return str.join("&");
// };

const getModalInput = function (fileName, postData,currentVal) {


	return new Promise((resolve, reject) => {
		$.ajax({
			url: `${appUrl}/lookup/${fileName}?data=${currentVal}&${serialize(postData)}`,
			method: "GET",
			dataType: "html",
			success: function (response) {
				resolve(response);
			},
            error: function (xhr, status, error) {
                console.log(error);

                reject(error);
            }
		});
	});
};

$.fn.modalInput = function (options) {
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
		element.hide()
		element.wrap('<div class="input-group"></div>').after(`

			<div class="input-app-data">
				<button class="btn btn-success lookup-toggler" type="button">
					<i class="far fa-window-maximize text-white text-easyui-dark" style="font-size: 12.25px"> ${settings.title}</i>
				</button>
			</div>
		`);

		element
			.siblings(".input-app-data")
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
                  <button type="button" class="btn btn-success savemodal-input"  aria-label="save">
                  Save
                  </button>
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
		console.log(searchValue);
		getModalInput(settings.fileName, settings.postData, searchValue ?? null).then((response) => {
			lookupModal.find(".modal-body").html('')
			lookupModal.find(".modal-body").html(response);


		});

		lookupModal.on("hidden.bs.modal", function () {
			lookupModal.html('');
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
		$(document)
			.find(lookupModal)
			.on("click", ".savemodal-input",function () {
				let data = $('#input-modal-form').serializeArray()
				$('#input-modal-form').find(`[name="nominal_job[]"`).each((index, element) => {
					data.filter((row) => row.name === 'nominal_job[]')[index].value = AutoNumeric.getNumber($(`#crudForm [name="nominal_job[]"]`)[index])
				})
				$('#input-modal-form').find(`[name="nominal_biaya[]"`).each((index, element) => {
					data.filter((row) => row.name === 'nominal_biaya[]')[index].value = AutoNumeric.getNumber($(`#crudForm [name="nominal_biaya[]"]`)[index])
				})


				handleSelectedRow(serializeToJson(data), lookupModal, element)
			});

	}

	function serializeToJson(data) {
		let jobEmklArray = [];
		let nominalArray = [];
		let biayaArray = [];
		let nominalBiayaArray = [];
		let keteranganArray = [];
		let containerArray = [];
		let jenisorderArray = [];
		let tujuanArray = [];

		// Pisahkan berdasarkan nama "job_emkl[]" dan "nominal[]"
		data.forEach(item => {
			if (item.name === "job_emkl[]") {
				jobEmklArray.push(item.value);
			} else if (item.name === "container_job[]") {
				containerArray.push(item.value);
			} else if (item.name === "jenisorder_job[]") {
				jenisorderArray.push(item.value);
			} else if (item.name === "tujuan_job[]") {
				tujuanArray.push(item.value);
			} else if (item.name === "nominal_job[]") {
				nominalArray.push(item.value);
			} else if (item.name === "biaya_emkl[]") {
				biayaArray.push(item.value);
			} else if (item.name === "nominal_biaya[]") {
				nominalBiayaArray.push(item.value);
			} else if (item.name === "keterangan_biaya[]") {
				keteranganArray.push(item.value);
			}
		});

		// Gabungkan kembali berdasarkan indeks yang sama
		let result;
		if (jobEmklArray.length) {
			result = jobEmklArray.map((job, index) => {
				if(job.length > 0) {
					return {
						"job_emkl": job,
						"container_job": containerArray[index],
						"jenisorder_job": jenisorderArray[index],
						"tujuan_job": tujuanArray[index],
						"nominal": nominalArray[index]
					};
				}
			});

		} else {
			result = biayaArray.map((biaya, index) => {
				return {
					"biaya_emkl": biaya,
					"nominal_biaya": nominalBiayaArray[index],
					"keterangan_biaya": keteranganArray[index]
				};
			});

		}

		return result;
	}

	function handleSelectedRow(data, lookupModal, element) {
		if (id !== null) {
			lookupModal.modal("hide");
			//ambil nilai,submit
			settings.onSelectRow(data, element);
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



	return this;
};
$.fn.linkInput = function (options) {
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
		element.hide()
		element.wrap('<div class="input-group"></div>').after(`<div class="input-app-data"><button class="btn btn-success lookup-toggler" type="button">${settings.title}</button></div>`);

		element
			.siblings(".input-app-data")
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
                  <button type="button" class="btn btn-success savemodal-input"  aria-label="save">
                  Save
                  </button>
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
		console.log(searchValue);
		getModalInput(settings.fileName, settings.postData ,searchValue ?? null).then((response) => {
			lookupModal.find(".modal-body").html('')
			lookupModal.find(".modal-body").html(response);

		});

		lookupModal.on("hidden.bs.modal", function () {
			lookupModal.html('');
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
		$(document)
			.find(lookupModal)
			.on("click", ".savemodal-input",function () {
				let data = $('#input-modal-form').serializeArray()
				$('#input-modal-form').find(`[name="nominal_job[]"`).each((index, element) => {
					data.filter((row) => row.name === 'nominal_job[]')[index].value = AutoNumeric.getNumber($(`#crudForm [name="nominal_job[]"]`)[index])
				})
				$('#input-modal-form').find(`[name="nominal_biaya[]"`).each((index, element) => {
					data.filter((row) => row.name === 'nominal_biaya[]')[index].value = AutoNumeric.getNumber($(`#crudForm [name="nominal_biaya[]"]`)[index])
				})


				handleSelectedRow(serializeToJson(data), lookupModal, element)
			});

	}

	function serializeToJson(data) {
		let jobEmklArray = [];
		let nominalArray = [];
		let biayaArray = [];
		let nominalBiayaArray = [];
		let keteranganArray = [];
		let containerArray = [];
		let jenisorderArray = [];
		let tujuanArray = [];

		// Pisahkan berdasarkan nama "job_emkl[]" dan "nominal[]"
		data.forEach(item => {
			console.log('item', item)
			if (item.name === "job_emkl[]") {
				jobEmklArray.push(item.value);
			} else if (item.name === "container_job[]") {
				containerArray.push(item.value);
			} else if (item.name === "jenisorder_job[]") {
				jenisorderArray.push(item.value);
			} else if (item.name === "tujuan_job[]") {
				tujuanArray.push(item.value);
			} else if (item.name === "nominal_job[]") {
				nominalArray.push(item.value);
			} else if (item.name === "biaya_emkl[]") {
				biayaArray.push(item.value);
			} else if (item.name === "nominal_biaya[]") {
				nominalBiayaArray.push(item.value);
			} else if (item.name === "keterangan_biaya[]") {
				keteranganArray.push(item.value);
			}
		});

		// Gabungkan kembali berdasarkan indeks yang sama
		let result;
		if (jobEmklArray.length) {
			result = jobEmklArray.map((job, index) => {
				if(job.length > 0) {
					return {
						"job_emkl": job,
						"container_job": containerArray[index],
						"jenisorder_job": jenisorderArray[index],
						"tujuan_job": tujuanArray[index],
						"nominal": nominalArray[index]
					};
				}
			});

		} else {
			result = biayaArray.map((biaya, index) => {
				return {
					"biaya_emkl": biaya,
					"nominal_biaya": nominalBiayaArray[index],
					"keterangan_biaya": keteranganArray[index]
				};
			});

		}

		return result;
	}

	function handleSelectedRow(data, lookupModal, element) {
		if (id !== null) {
			lookupModal.modal("hide");
			//ambil nilai,submit
			settings.onSelectRow(data, element);
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



	return this;
};
$.fn.pgdoInput = function (options) {
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
		element.hide()
        // element.wrap('<div class="input-group"></div>').after(`
		// 	${
		// 		settings.onClear
		// 			? `<button type="button" class="btn position-absolute button-clear text-secondary" style="right: 34px; z-index: 99;"><i class="fa fa-times-circle" style="font-size: 15px; margin-top:2px; color:red"></i></button>`
		// 			: ``
		// 	}

		// 	<div class="input-group-append">
		// 		<button class="btn btn-easyui lookup-toggler" type="button">
		// 			<i class="far fa-window-maximize text-easyui-dark" style="font-size: 12.25px"></i>
		// 		</button>
		// 	</div>
		// `);
		element.wrap('<div class="input-group"></div>').after(`<div class="input-app-data"><button class="btn btn-success lookup-toggler" type="button">${settings.title}</button></div>`);
        let inserteddata = element.closest('td').find(`[name="rincian_result[]"]`).val()

		element
			.siblings(".input-app-data")
			.find(".lookup-toggler")
			.click(async function () {
				activateLookup(element, inserteddata);
			});

		element.siblings(".button-clear").click(function () {
			handleOnClear(element);
		});

		element.on("input", function (event) {
			delay(function () {
				activateLookup(element, inserteddata);
			}, 500);
		});

		element.on("keydown", function (event) {
			if (event.keyCode === 115) {
				activateLookup(element, inserteddata);
			}
		});
	});

	async function activateLookup(element, searchValue = null) {
		settings.beforeProcess();
		settings.onShowLookup();
        searchValue = element.closest('td').find(`[name="rincian_result[]"]`).val()


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
                  <button type="button" class="btn btn-success savemodal-input"  aria-label="save">
                  Save
                  </button>
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
		console.log(searchValue);
		getModalInput(settings.fileName, settings.postData ,searchValue ?? null).then((response) => {
			lookupModal.find(".modal-body").html('')
			lookupModal.find(".modal-body").html(response);

		});

		lookupModal.on("hidden.bs.modal", function () {
			lookupModal.html('');
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
		$(document)
			.find(lookupModal)
			.on("click", ".savemodal-input",function () {
				let data = $('#input-modal-form').serializeArray()
				$('#input-modal-form').find(`[name="rinciandetail_qty[]"`).each((index, element) => {
					data.filter((row) => row.name === 'rinciandetail_qty[]')[index].value = AutoNumeric.getNumber($(`#crudForm [name="rinciandetail_qty[]"]`)[index])
				})
				handleSelectedRow(serializeToJson(data), lookupModal, element)
			});

	}

	function serializeToJson(data) {
		let penerimaanStokHeaderArray = [];
		let penerimaanStokHeaderIdArray = [];
		let qtyArray = [];
		let keteranganArray = [];
		// Pisahkan berdasarkan nama "job_emkl[]" dan "nominal[]"
		data.forEach(item => {
			if (item.name === "penerimaanheaderrincian[]") {
				penerimaanStokHeaderArray.push(item.value);
			} else if (item.name === "rincian_penerimaanstoknobukti_id[]") {
				penerimaanStokHeaderIdArray.push(item.value);
			} else if (item.name === "rinciandetail_qty[]") {
				qtyArray.push(item.value);
			} else if (item.name === "rincian_keterangan[]") {
				keteranganArray.push(item.value);
			}
		});

		// Gabungkan kembali berdasarkan indeks yang sama
		let result;
		let sumQty =0;
		if (penerimaanStokHeaderArray.length) {
			result = penerimaanStokHeaderArray.map((job, index) => {
				if(job.length > 0) {
                    sumQty +=qtyArray[index];
					return {
						"rincian_penerimaanStokHeader": job,
						"rincian_penerimaanStokHeaderId": penerimaanStokHeaderIdArray[index],
						"rincian_qty": qtyArray[index],
						"rincian_keterangan": keteranganArray[index]
					};
				}
			});

		}
        // console.log({qtyArray,sumQty});
		return {
            result:result,
            sum:sumQty
        };
	}

	function handleSelectedRow(data, lookupModal, element) {
		if (id !== null) {
			lookupModal.modal("hide");
			//ambil nilai,submit
			settings.onSelectRow(data, element);
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



	return this;
};
