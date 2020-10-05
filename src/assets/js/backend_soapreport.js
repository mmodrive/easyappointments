/* ----------------------------------------------------------------------------
 * Easy!Appointments - Open Source Web Scheduler
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) 2013 - 2018, Alex Tselegidis
 * @license     http://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        http://easyappointments.org
 * @since       v1.0.0
 * ---------------------------------------------------------------------------- */

window.BackendSOAPReport = window.BackendSOAPReport || {};

/**
 * Backend Customers
 *
 * Backend Customers javascript namespace. Contains the main functionality of the backend customers
 * page. If you need to use this namespace in a different page, do not bind the default event handlers
 * during initialization.
 *
 * @module BackendCustomers
 */
(function (exports) {
    ("use strict");

    var dateFormat;
    var $report;

    function _bindEventHandlers() {
        $report.find(".edit-report, .add-report").click(function () {
            _saveReport();

            _resetForm();
        });

        $report.find(".cancel-report").click(function () {
            _resetForm();
        });

        $report.find(".cancel-soap").click(function () {
            var originalState = $report
                .find("#soap_report_form #id")
                .data("state");
            var unchanged = true;
            $report
                .find("#soap_report_form .form-control:visible")
                .each(function (i, el) {
                    return (unchanged = originalState
                        ? originalState[el.id] == $(el).val()
                        : $(el).val() == "");
                });
            if (
                unchanged ||
                !confirm(
                    "Changes NOT saved. Click OK to continue editing or cancel to discard."
                )
            )
                $("#soap_report").modal("hide");
        });
    }

    function _resetForm() {
        $report
            .find(
                "#soap_report_form .form-control:visible, #soap_report_form #id"
            )
            .val("");
        $report.find("#soap_report_form #id").removeData("state");
        $report.find("#soap_report_form .edit-report").hide();
        $report.find("#soap_report_form .add-report").show();
        $report
            .find(".modal-message")
            .addClass("hidden")
            .removeClass("alert-danger alert-success");
    }

    function _loadReports(reports) {
        $report.find(".reports tbody").empty();

        $.each(reports, function (i, value) {
            value.date = GeneralFunctions.formatDate(
                Date.parseExact(value.date, "yyyy-MM-dd HH:mm:ss"),
                GlobalVariables.dateFormat,
                false
            );
            var tr = `<tr data-id="${value.id}">
                <td class="">${value.date}</td>
                <td class="">${value.subjective}</td>
                <td class="">${value.objective}</td>
                <td class="">${value.assessment}</td>
                <td class="">${value.plan}</td>
                <td>
                    <button type="button" class="btn btn-default btn-sm edit-report" title="${EALang.edit}">
                        <span class="glyphicon glyphicon-pencil"></span>
                    </button>
                    <button type="button" class="btn btn-default btn-sm delete-report" title="${EALang.delete}">
                        <span class="glyphicon glyphicon-remove"></span>
                    </button>
                </td>
                </tr>`;
            $report
                .find(".reports tbody")
                .append(tr)
                .find("tr[data-id=" + value.id + "]")
                .data("state", value);
        });

        $report.find(".reports tbody .edit-report").click(function () {
            var state = $(this).closest("tr").data("state");
            $.each(state, function (key, value) {
                $report
                    .find("#soap_report_form #" + key + ".form-control")
                    .val(value);
            });
            $report
                .find("#soap_report_form #id")
                .val($(this).closest("tr").data("id"))
                .data("state", state);
            $report.find("#soap_report_form .edit-report").show();
            $report.find("#soap_report_form .add-report").hide();
        });

        $report.find(".reports tbody .delete-report").click(function () {
            if (
                confirm(
                    "Are you sure you want to delete this report? This action is not reversible!"
                )
            ) {
                var url =
                    GlobalVariables.baseUrl +
                    "/index.php/backend_api/ajax_delete_soap_report";
                var postData = {
                    csrfToken: GlobalVariables.csrfToken,
                    id: $(this).closest("tr").data("id"),
                };

                var successCallback = function (response) {
                    if (!_handleAjaxExceptions(response)) return false;

                    // Display success message to the user.
                    $report.find(".modal-message").text(EALang.report_deleted);
                    $report
                        .find(".modal-message")
                        .addClass("alert-success")
                        .removeClass("alert-danger hidden");
                    $report.find(".modal-body").scrollTop(0);

                    _loadReports(response.reports);
                };

                $.ajax({
                    url: url,
                    type: "POST",
                    data: postData,
                    dataType: "json",
                })
                    .done(function (response) {
                        successCallback(response);
                    })
                    .fail(_errorCallback);
            }
        });
    }

    function _saveReport() {
        // Before doing anything the appointment data need to be validated.
        if (!_validateSOAPReportForm()) {
            return;
        }

        var report = {};
        $("#soap_report_form .form-control").each(function () {
            if ($(this).val() != "") {
                if (this.id == "date")
                    report[this.id] = $(this)
                        .datepicker("getDate")
                        .toString("yyyy-MM-dd");
                // else if(this.id == 'id'){}
                else report[this.id] = $(this).val();
            }
        });

        var url =
            GlobalVariables.baseUrl +
            "/index.php/backend_api/ajax_save_soap_report";
        var postData = {
            csrfToken: GlobalVariables.csrfToken,
            report: JSON.stringify(report),
        };

        // Define success callback.
        var successCallback = function (response) {
            if (!_handleAjaxExceptions(response)) return false;

            // Display success message to the user.
            $report.find(".modal-message").text(EALang.report_saved);
            $report
                .find(".modal-message")
                .addClass("alert-success")
                .removeClass("alert-danger hidden");
            $report.find(".modal-body").scrollTop(0);

            _loadReports(response.reports);
        };

        $.ajax({
            url: url,
            type: "POST",
            data: postData,
            dataType: "json",
        })
            .done(function (response) {
                successCallback(response);
            })
            .fail(_errorCallback);
    }

    function _handleAjaxExceptions(response) {
        if (!GeneralFunctions.handleAjaxExceptions(response)) {
            $report
                .find(".modal-message")
                .text(EALang.unexpected_issues_occurred);
            $report
                .find(".modal-message")
                .addClass("alert-danger")
                .removeClass("hidden");
            return false;
        }
        return true;
    }

    function _errorCallback(jqXHR, textStatus, errorThrown) {
        $report.find(".modal-message").text(EALang.service_communication_error);
        $report
            .find(".modal-message")
            .addClass("alert-danger")
            .removeClass("hidden");
        $report.find(".modal-body").scrollTop(0);
        console.log(textStatus + " " + errorThrown + " " + jqXHR.responseText);
    }

    function _validateSOAPReportForm() {
        // Reset previous validation css formatting.
        $report.find(".has-error").removeClass("has-error");
        $report.find(".modal-message").addClass("hidden");

        try {
            // Check required fields.
            var missingRequiredField = false;

            $report.find(".required:visible").each(function () {
                if ($(this).val() == "" || $(this).val() == null) {
                    $(this).closest(".form-group").addClass("has-error");
                    missingRequiredField = true;
                }
            });

            if (missingRequiredField) {
                throw EALang.fields_are_required;
            }

            return true;
        } catch (exc) {
            $report
                .find(".modal-message")
                .addClass("alert-danger")
                .text(exc)
                .removeClass("hidden");
            return false;
        }
    }

    exports.load = function (pet_id) {
        $report.find("#id_pets").val(pet_id);

        var url =
            GlobalVariables.baseUrl +
            "/index.php/backend_api/ajax_load_soap_reports";
        var postData = {
            csrfToken: GlobalVariables.csrfToken,
            pet_id: pet_id,
        };

        // Define success callback.
        var successCallback = function (response) {
            if (!_handleAjaxExceptions(response)) return false;

            _loadReports(response.reports);

            _resetForm();
        };

        $.ajax({
            url: url,
            type: "POST",
            data: postData,
            dataType: "json",
        })
            .done(function (response) {
                successCallback(response);
            })
            .fail(_errorCallback);
    };

    exports.initialize = function () {
        $report = $("#soap_report");

        switch (GlobalVariables.dateFormat) {
            case "DMY":
                dateFormat = "dd/mm/yy";
                break;
            case "MDY":
                dateFormat = "mm/dd/yy";
                break;
            case "YMD":
                dateFormat = "yy/mm/dd";
                break;
            default:
                throw new Error("Invalid GlobalVariables.dateFormat value.");
        }

        $report.find("#soap_report_form #date").datepicker({
            dateFormat: dateFormat,
            timeFormat:
                GlobalVariables.timeFormat === "regular" ? "h:mm TT" : "HH:mm",
            changeYear: true,
            changeMonth: true,
            yearRange: "-10:+0",

            // Translation
            dayNames: [
                EALang.sunday,
                EALang.monday,
                EALang.tuesday,
                EALang.wednesday,
                EALang.thursday,
                EALang.friday,
                EALang.saturday,
            ],
            dayNamesShort: [
                EALang.sunday.substr(0, 3),
                EALang.monday.substr(0, 3),
                EALang.tuesday.substr(0, 3),
                EALang.wednesday.substr(0, 3),
                EALang.thursday.substr(0, 3),
                EALang.friday.substr(0, 3),
                EALang.saturday.substr(0, 3),
            ],
            dayNamesMin: [
                EALang.sunday.substr(0, 2),
                EALang.monday.substr(0, 2),
                EALang.tuesday.substr(0, 2),
                EALang.wednesday.substr(0, 2),
                EALang.thursday.substr(0, 2),
                EALang.friday.substr(0, 2),
                EALang.saturday.substr(0, 2),
            ],
            monthNames: [
                EALang.january,
                EALang.february,
                EALang.march,
                EALang.april,
                EALang.may,
                EALang.june,
                EALang.july,
                EALang.august,
                EALang.september,
                EALang.october,
                EALang.november,
                EALang.december,
            ],
            prevText: EALang.previous,
            nextText: EALang.next,
            currentText: EALang.now,
            closeText: EALang.close,
            firstDay: 0,
        });

        _bindEventHandlers();
    };
})(window.BackendSOAPReport);
