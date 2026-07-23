<?php
require_once 'stimulsoft/helper.php';
?>

<!DOCTYPE html>
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<title>Report Rayon</title>

	<!-- Report Office2013 style -->
	<link href="css/stimulsoft.viewer.office2013.whiteteal.css" rel="stylesheet">

	<!-- Stimusloft Reports.JS -->
<script src="scripts/jquery.min.js" type="text/javascript"></script>
    <script>
        jQuery.extend({
            getValues: function(url) {
                var result = null;
                $.ajax({
                    url: url,
                    type: 'get',
                    dataType: 'json',
                    async: false,
                    success: function(data) {
                        result = data;
                    }
                });
               return result;
            }
        });
        function filter(data,item){
            var margin=$.grep(data, function( n, i ) {
              return n.parameterid===item;
            })[0];
            margin = margin==undefined?"":parseFloat(margin.parametertext);
            return margin;
        }
        var base_url = window.location.origin + '/' + window.location.pathname.split ('/') [1] + '/';
        var results = $.getValues(base_url+"extension/getMargin/RPTMEMBERRAYON");
        var defaultMargin =  $.getValues(base_url+"extension/getMargin/DEFAULT");
        var datamargin="";
        datamargin+= filter(results,'TOP')==""?filter(defaultMargin,'TOP')+"cm ":filter(results,'TOP')+"cm ";
        datamargin+= filter(results,'RIGHT')==""?filter(defaultMargin,'RIGHT')+"cm ":filter(results,'RIGHT')+"cm ";
        datamargin+= filter(results,'BOTTOM')==""?filter(defaultMargin,'BOTTOM')+"cm ":filter(results,'BOTTOM')+"cm ";
        datamargin+= filter(results,'LEFT')==""?filter(defaultMargin,'LEFT')+"cm ":filter(results,'LEFT')+"cm ";
    </script>
	<script src="scripts/stimulsoft.reports.js" type="text/javascript"></script>
	<script src="scripts/stimulsoft.viewer.js" type="text/javascript"></script>
	<?php
        $options = StiHelper::createOptions();
        $options->handler = "handler.php";
        $options->timeout = 30;
        StiHelper::initialize($options);
    ?>
	<script type="text/javascript">
		Stimulsoft.Base.StiLicense.loadFromFile("stimulsoft/license.php");
		$(document).keydown(function(e) {
            // ESCAPE key pressed
            if (e.keyCode == 27) {
                window.close();
            }
        });
		var options = new Stimulsoft.Viewer.StiViewerOptions();
		options.appearance.fullScreenMode = true;
        options.toolbar.showSendEmailButton = false;
        options.toolbar.showPrintButton = false;
        options.toolbar.showViewModeButton = false;
        var viewer = new Stimulsoft.Viewer.StiViewer(options, "StiViewer", false);
        //costum componen
        StiJsViewer.prototype.InitializePrintMenu = function() {

            var A = [];
            A.push(this.Item("PrintWitPreview", this.collections.loc.PrintWithPreview, "PrintWithPreview.png", "PrintWithPreview"));
            var t = this.VerticalMenu("printMenu", this.controls.toolbar.controls.Print, "Down", A);
            t.action = function(A) {
                t.changeVisibleState(!1), t.jsObject.postPrint(A.key)
            }
        }

		// Process SQL data source
		viewer.onBeginProcessData = function (event, callback) {
            event.connection= "rayon";
            console.log(event);
			<?php StiHelper::createHandler(); ?>
		}
		viewer.onBeginExportReport = function (args) {
			// args.fileName = "MyReportName";
		}
		viewer.onPrintReport = function(event){
			console.log(event);
		}

		// var result = (sender as StiReport).PrinterSettings.PrintDialogResult;
		// if (result  == DialogResult.OK || result  == DialogResult.None)
		// {
		// 	console.log("Print Selesai");
		// }

		// Send exported report to server side
		/*viewer.onEndExportReport = function (event) {
			event.preventDefault = true; // Prevent client default event handler (save the exported report as a file)
			<?php StiHelper::createHandler(); ?>
		}*/

		// Send exported report to Email
		viewer.onEmailReport = function (event) {
			<?php StiHelper::createHandler(); ?>
		}
		// Load and show report
		var report = new Stimulsoft.Report.StiReport();
//		report.loadFile("reports/SimpleList.mrt");
		report.loadFile("reports/rptmember_rayon.mrt");
		function getUrlVars() {
            var vars = {};
            var parts = window.location.href.replace(/[?&]+([^=&]+)=([^&]*)/gi,
                function (m, key, value) {
                    vars[key] = value;
            });
            return vars;
        }
                report.pages.getByIndex(0).margins.left = filter(results,'LEFT')==""?filter(defaultMargin,'LEFT'):filter(results,'LEFT');
        report.pages.getByIndex(0).margins.top = filter(results,'TOP')==""?filter(defaultMargin,'TOP'):filter(results,'TOP');
        report.pages.getByIndex(0).margins.right = filter(results,'RIGHT')==""?filter(defaultMargin,'RIGHT'):filter(results,'RIGHT');
        report.pages.getByIndex(0).margins.bottom = filter(results,'BOTTOM')==""?filter(defaultMargin,'BOTTOM'):filter(results,'BOTTOM');
        var vars = getUrlVars();
        var base_url = window.location.origin + '/' + window.location.pathname.split ('/') [1] + '/';
        
        report.dictionary.variables.getByName("link").valueObject = base_url+"uploads/medium_";
        report.dictionary.variables.list.forEach(function(item, i, arr) {
            if (typeof vars[item.name] != "undefined") item.valueObject = vars[item.name];
        });
        viewer.report = report;
        viewer.renderHtml("viewerContent");
        // pdf
        function saveReportPdf() {
            var pdfSettings = new Stimulsoft.Report.Export.StiPdfExportSettings();
            var pdfService = new Stimulsoft.Report.Export.StiPdfExportService();
            var stream = new Stimulsoft.System.IO.MemoryStream();
            report.renderAsync(function () {
                pdfService.exportToAsync(function () {
                    var data = stream.toArray();
                    var blob = new Blob([new Uint8Array(data)], { type: "application/pdf" });
                    if (window.navigator && window.navigator.msSaveOrOpenBlob) {
                        var fileName = (report.reportAlias == null || report.reportAlias.trim().length == 0) ? report.reportName : report.reportAlias;
                        window.navigator.msSaveOrOpenBlob(blob, fileName + ".pdf");
                    }
                    else {
                        var fileUrl = URL.createObjectURL(blob);
                        window.open(fileUrl);
                        exportPdf.isEnabled=true
                    }
                }, report, stream, pdfSettings);
            }, false);
        }
        var exportPdf = viewer.jsObject.SmallButton("exportButton","Print To PDF","emptyImage");
        exportPdf.image.src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAFfSURBVHjaYvxob7yXgYHBiYE8sI8FppnvwBmwyM8Fs8AYxP976zrD783rGX5tXgeXB4FPDiYwphMTsnEgCfaENDj/a1osA0dxFZwPMhhJMxigGACyBaQIF2CxssUUQ3cBMuCetZjhR28biovQASMwEP8zUAK2bdv2n1ywY9PG/3AvvHz5mqBl4uKiCM6/fwzqG1chwgBFkgjwY2IXg9CdGwxkueDnwtkMvzauQY0FYlzw/80rhu9dLQx/Th1DRKPK9g0M/zTVGV5z8uK09f+H9wy/d20D2/z/6xcGFgsbBlY3L4b9n74iovG/mATDPy1dhn/iUsAUxQ+J46ePGDjevWH4c+IIhC8ixsBZUgU2AAS2b9+O8ALjqxcMzCCM5oI/MKeaWTFwNnYwMHJy4U6JuABbYBgDR24JMOEzYSblZ6bWt6ROH1XDGmiMTAz3XbwYnqvpMjDs3IlNyQ6AAAMA+4a3P3zhm5cAAAAASUVORK5CYII=";
        exportPdf.action = function(){
            exportPdf.isEnabled=false;
            saveReportPdf();
        }

        // end pdf

        var userButton = viewer.jsObject.SmallButton("userButton", "Close", "emptyImage");
        var printButton = viewer.jsObject.SmallButton("printButton", "Print", "emptyImage");
        printButton.image.src = "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAACtSURBVHjaYiwqKmJAA/8Z8ANGZA4LNhW9vb1YdRYXF2OIMTFQCCg2AOQFeyDuAmIzkNP9mneCMVYg4Mawqdb9P9Qrp4C4DGTACiCWgKkBKiDWcjOQXhZkzdgCiQCQGPhApEosPAViaTIT0lOQASlAPB85MEHAt2kHisrNdR7oml+A9IIMAKmUhNkMtOU/Dg0w1zEiu4QFl9+IcAGmAcgm49KAHg4sxOY6XAAgwABqSjFfY2wW+AAAAABJRU5ErkJggg==";
        userButton.action = function () {
            window.open('','_parent','');window.close();
        }
        printButton.action = function(){
            viewer.jsObject.postPrint("PrintWithoutPreview");
        }
        var toolbarTable = viewer.jsObject.controls.toolbar.firstChild.firstChild;
        var buttonsTable = toolbarTable.rows[0].firstChild.firstChild;

        var exportPdfCell = buttonsTable.rows[0].insertCell(0);
         exportPdfCell.className = "stiJsViewerClearAllStyles";
        exportPdfCell.appendChild(exportPdf);

        var printButtonCell = buttonsTable.rows[0].insertCell(0);
        var userButtonCell = buttonsTable.rows[0].insertCell(0);

        // printButtonCell.className = "stiJsViewerClearAllStyles";
        // printButtonCell.appendChild(printButton);
        userButtonCell.className = "stiJsViewerClearAllStyles";
        userButtonCell.appendChild(userButton);

	</script>
	</head>
<body>
	<div id="viewerContent"></div>
</body>
</html>
