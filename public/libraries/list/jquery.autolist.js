/* global jQuery */
(function ($) {
    "use strict";

    $.fn.autolist = function (url, options) {

        // url is required
        if (!url || (typeof url !== "string")) {
            throw "jquery-autolist: must provide the JSON API url (string).";
        }

        // override defaults with options
        var settings = $.extend({
            query: "q",
            minLength: 3,
            delay: 500,
            trimValue: true,
            getItems: function (response) {
                return response;
            },
            getName: function (item) {
                return item;
            }
        }, options);

        this.filter("input").each(function () {
            var input = $(this);

            // datalist
            var list = input.attr("list");
            if (!list) {
                return; //-->
            }
            list = $("#" + list);
            if (!list.length) {
                return; //-->
            }
            list = $(list[0]);

            var pendingTimeout = null;
            var pendingRequest = null;
            var lastVal = null;

            input.on("input", function () {
                var val = $(this).val();
                if (val && settings.trimValue) {
                    val = val.trim();
                }

                if (val === lastVal) {
                    return;  //-->
                }

                // abort pending XHRs
                if (pendingRequest) {
                    pendingRequest.abort();
                    pendingRequest = null;
                }

                // cancel peding timeouts
                if (pendingTimeout) {
                    clearTimeout(pendingTimeout);
                    pendingTimeout = null;
                }

                if (!val || (val.length < settings.minLength)) {
                    list.empty(); // no suggestions below minLength
                    return; //-->
                }

                // lastVal is the value we care about, all pending request for
                // previousvalues should either be aborted or not update the
                // suggestions list
                lastVal = val;

                pendingTimeout = setTimeout(function () {
                    var queryVal = lastVal;
                    var q = {};
                    q[settings.query] = val;
                    pendingRequest = $.get(url, q, function (response) {
                        // response to aborted/old request
                        if (queryVal !== lastVal) {
                            return; //-->
                        }

                        // reset pending values
                        pendingTimeout = pendingRequest = lastVal = null;

                        list.empty();
                        var items = settings.getItems(response),
                            len = items.length,
                            i = 0,
                            opt;
                        while (i < len) {
                            opt = $("<option></option>").attr("value", 
                                settings.getName(items[i]));
                            list.append(opt);
                            i = i + 1;
                        }
                    }, "json");

                    pendingRequest.fail(function() {
                        if (queryVal === lastVal) {
                            // reset pending values
                            pendingTimeout = pendingRequest = lastVal = null;
                        }
                    });
                }, settings.delay);
            });
        });

        // allow chaining
        return this;
    };

}(jQuery));