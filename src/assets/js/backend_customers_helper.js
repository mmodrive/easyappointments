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

(function () {

    'use strict';

    /**
     * CustomersHelper Class
     *
     * This class contains the methods that are used in the backend customers page.
     *
     * @class CustomersHelper
     */
    function CustomersHelper() {
        this.filterResults = {};
    }

    /**
     * Binds the default event handlers of the backend customers page.
     */
    CustomersHelper.prototype.bindEventHandlers = function () {
        var instance = this;

        /**
         * Event: Filter Customers Form "Submit"
         */
        $("#filter-customers form").submit(function (event) {
            var key = $("#filter-customers .key").val();
            $("#filter-customers .selected").removeClass("selected");
            instance.resetForm();
            instance.filter(key);
            return false;
        });

        /**
         * Event: Filter Customers Clear Button "Click"
         */
        $("#filter-customers .clear").click(function () {
            $("#filter-customers .key").val("");
            instance.filter("");
            instance.resetForm();
        });

        /**
         * Event: Filter Entry "Click"
         *
         * Display the customer data of the selected row.
         */
        $(document).on("click", ".entry", function () {
            if ($("#filter-customers .filter").prop("disabled")) {
                return; // Do nothing when user edits a customer record.
            }

            var customerId = $(this).attr("data-id");
            var customer = {};
            $.each(instance.filterResults, function (index, item) {
                if (item.id == customerId) {
                    customer = item;
                    return false;
                }
            });

            instance.display(customer);
            $("#filter-customers .selected").removeClass("selected");
            $(this).addClass("selected");
            $("#edit-customer, #delete-customer, #merge-customer").prop(
                "disabled",
                false
            );
        });

        /**
         * Event: Appointment Row "Click"
         *
         * Display appointment data of the selected row.
         */
        $(document).on("click", ".appointment-row", function () {
            $("#customer-appointments .selected").removeClass("selected");
            $(this).addClass("selected");

            var customerId = $("#filter-customers .selected").attr("data-id");
            var appointmentId = $(this).attr("data-id");
            var appointment = {};

            $.each(instance.filterResults, function (index, c) {
                if (c.id === customerId) {
                    $.each(c.appointments, function (index, a) {
                        if (a.id == appointmentId) {
                            appointment = a;
                            return false;
                        }
                    });
                    return false;
                }
            });

            instance.displayAppointment(appointment);
        });

        $(document).on("click", ".pet-row", function () {
            $("#customer-pets .selected").removeClass("selected");
            $(this).addClass("selected");

            var customerId = $("#filter-customers .selected").attr("data-id");
            var petId = $(this).attr("data-id");
            var pet = {};

            $.each(instance.filterResults, function (index, c) {
                if (c.id === customerId) {
                    $.each(c.pets, function (index, p) {
                        if (p.id == petId) {
                            pet = p;
                            return false;
                        }
                    });
                    return false;
                }
            });

            instance.displayPet(pet);

            $("#view-soap_report").prop("disabled", false);
        });

        $("#view-soap_report").click(function () {
            var $dialog = $("#soap_report");
            $dialog.modal("show");
            window.BackendSOAPReport.load(
                $("#customer-pets .selected").attr("data-id")
            );
        });

        /**
         * Event: Add Customer Button "Click"
         */
        $("#add-customer").click(function () {
            instance.resetForm();
            $("#add-edit-delete-group").hide();
            $("#save-cancel-group").show();
            $(".record-details")
                .find("input, textarea")
                .prop("readonly", false)
                .prop("disabled", false);

            $("#filter-customers button").prop("disabled", true);
            $("#filter-customers .results").css("color", "#AAA");
        });

        /**
         * Event: Edit Customer Button "Click"
         */
        $("#edit-customer").click(function () {
            $(".record-details")
                .find("input, textarea")
                .prop("readonly", false)
                .prop("disabled", false);
            $("#add-edit-delete-group").hide();
            $("#save-cancel-group").show();

            $("#filter-customers button").prop("disabled", true);
            $("#filter-customers .results").css("color", "#AAA");
        });

        /**
         * Event: Cancel Customer Add/Edit Operation Button "Click"
         */
        $("#cancel-customer").click(function () {
            var id = $("#customer-id").val();
            instance.resetForm();
            if (id != "") {
                instance.select(id, true);
            }
        });

        /**
         * Event: Save Add/Edit Customer Operation "Click"
         */
        $("#save-customer").click(function () {
            var customer = {
                first_name: $("#first-name").val(),
                last_name: $("#last-name").val(),
                email: $("#email").val(),
                phone_number: $("#phone-number").val(),
                address: $("#address").val(),
                city: $("#city").val(),
                zip_code: $("#zip-code").val(),
                disc_qualify: $("#disc_qualify").is(":checked"),
                notes: $("#notes").val(),
            };

            if ($("#customer-id").val() != "") {
                customer.id = $("#customer-id").val();
            }

            if ($("#pet-details").attr("data-id") != "") {
                customer.pet = {
                    id: $("#pet-details").attr("data-id"),
                    active: $("#pet_active").is(":checked"),
                };
            }

            if (!instance.validate()) {
                return;
            }

            instance.save(customer);
        });

        /**
         * Event: Delete Customer Button "Click"
         */
        $("#delete-customer").click(function () {
            var customerId = $("#customer-id").val();
            var buttons = [
                {
                    text: EALang.delete,
                    click: function () {
                        instance.delete(customerId);
                        $("#message_box").dialog("close");
                    },
                },
                {
                    text: EALang.cancel,
                    click: function () {
                        $("#message_box").dialog("close");
                    },
                },
            ];

            GeneralFunctions.displayMessageBox(
                EALang.delete_customer,
                EALang.delete_record_prompt,
                buttons
            );
        });

        $("#merge-customer").click(function () {
            var customerId = $("#customer-id").val();

            function GetFullName() {
                return $("#first-name").val() + " " + $("#last-name").val();
            }

            if ($("#merge-customer").data("customer-id-from")) {
                if (
                    customerId == $("#merge-customer").data("customer-id-from")
                ) {
                    alert("Please select another customer to merge with.");
                    return;
                }
                var buttons = [
                    {
                        text: EALang.merge,
                        click: function () {
                            instance.merge(
                                $("#merge-customer").data("customer-id-from"),
                                customerId
                            );
                            $("#message_box").dialog("close");
                        },
                    },
                    {
                        text: EALang.cancel,
                        click: function () {
                            $("#merge-customer").removeData("customer-id-from");
                            $("#message_box").dialog("close");
                        },
                    },
                ];

                GeneralFunctions.displayMessageBox(
                    EALang.merge_customer,
                    `Are you sure you want to merge ${$("#merge-customer").data(
                        "customer-id-from-name"
                    )} and ${GetFullName()}? This action cannot be undone.`,
                    buttons
                );
            } else {
                $("#merge-customer").data("customer-id-from", customerId);
                $("#merge-customer").data(
                    "customer-id-from-name",
                    GetFullName()
                );
            }
        });
    };

    /**
     * Save a customer record to the database (via ajax post).
     *
     * @param {Object} customer Contains the customer data.
     */
    CustomersHelper.prototype.save = function (customer) {
        var postUrl =
            GlobalVariables.baseUrl +
            "/index.php/backend_api/ajax_save_customer";
        var postData = {
            csrfToken: GlobalVariables.csrfToken,
            customer: JSON.stringify(customer),
        };

        $.post(
            postUrl,
            postData,
            function (response) {
                if (!GeneralFunctions.handleAjaxExceptions(response)) {
                    return;
                }

                Backend.displayNotification(EALang.customer_saved);
                this.resetForm();
                $("#filter-customers .key").val("");
                this.filter("", response.id, true);
            }.bind(this),
            "json"
        ).fail(GeneralFunctions.ajaxFailureHandler);
    };

    /**
     * Delete a customer record from database.
     *
     * @param {Number} id Record id to be deleted.
     */
    CustomersHelper.prototype.delete = function (id) {
        var postUrl =
            GlobalVariables.baseUrl +
            "/index.php/backend_api/ajax_delete_customer";
        var postData = {
            csrfToken: GlobalVariables.csrfToken,
            customer_id: id,
        };

        $.post(
            postUrl,
            postData,
            function (response) {
                if (!GeneralFunctions.handleAjaxExceptions(response)) {
                    return;
                }

                Backend.displayNotification(EALang.customer_deleted);
                this.resetForm();
                this.filter($("#filter-customers .key").val());
            }.bind(this),
            "json"
        ).fail(GeneralFunctions.ajaxFailureHandler);
    };

    /**
     * Merge two customers from database.
     *
     * @param {Number} from_id Record id to be merged from.
     * @param {Number} to_id Record id to be merge to.
     */
    CustomersHelper.prototype.merge = function (from_id, to_id) {
        var postUrl =
            GlobalVariables.baseUrl +
            "/index.php/backend_api/ajax_merge_customer";
        var postData = {
            csrfToken: GlobalVariables.csrfToken,
            from_id: from_id,
            to_id: to_id,
        };

        $.post(
            postUrl,
            postData,
            function (response) {
                if (!GeneralFunctions.handleAjaxExceptions(response)) {
                    return;
                }

                Backend.displayNotification(EALang.customer_merged);
                this.resetForm();
                this.filter($("#filter-customers .key").val());
            }.bind(this),
            "json"
        ).fail(GeneralFunctions.ajaxFailureHandler);
    };

    /**
     * Validate customer data before save (insert or update).
     */
    CustomersHelper.prototype.validate = function () {
        $("#form-message").removeClass("alert-danger").hide();
        $(".has-error").removeClass("has-error");

        try {
            // Validate required fields.
            var missingRequired = false;

            $("#customers-page .required").each(function () {
                if ($(this).val() == "") {
                    $(this).closest(".form-group").addClass("has-error");
                    missingRequired = true;
                }
            });

            if (missingRequired) {
                throw EALang.fields_are_required;
            }

            // Validate email address.
            if (!GeneralFunctions.validateEmail($("#email").val())) {
                $("#email").closest(".form-group").addClass("has-error");
                throw EALang.invalid_email;
            }

            return true;
        } catch (message) {
            $("#form-message").addClass("alert-danger").text(message).show();
            return false;
        }
    };

    /**
     * Bring the customer form back to its initial state.
     */
    CustomersHelper.prototype.resetForm = function () {
        $(".record-details").find("input, textarea").val("");
        $(".record-details")
            .find("input, textarea")
            .prop("readonly", true)
            .prop("disabled", true);

        $("#customer-appointments").empty();
        $("#appointment-details").toggleClass("hidden", true).empty();
        $("#edit-customer, #delete-customer, #merge-customer").prop(
            "disabled",
            true
        );
        $("#add-edit-delete-group").show();
        $("#save-cancel-group").hide();

        $(".record-details .has-error").removeClass("has-error");
        $(".record-details #form-message").hide();

        $("#filter-customers button").prop("disabled", false);
        $("#filter-customers .selected").removeClass("selected");
        $("#filter-customers .results").css("color", "");

        $("#customer-pets").empty();
        $("#pet-details").toggleClass("hidden", true).attr("data-id", "");
        $("#pet-summary").empty();
    };

    /**
     * Display a customer record into the form.
     *
     * @param {Object} customer Contains the customer record data.
     */
    CustomersHelper.prototype.display = function (customer) {
        $("#customer-id").val(customer.id);
        $("#first-name").val(customer.first_name);
        $("#last-name").val(customer.last_name);
        $("#email").val(customer.email);
        $("#phone-number").val(customer.phone_number);
        $("#address").val(customer.address);
        $("#city").val(customer.city);
        $("#zip-code").val(customer.zip_code);
        $("#disc_qualify").prop(
            "checked",
            customer.disc_qualify == 1 ? true : false
        );
        $("#notes").val(customer.notes);

        $("#customer-appointments").empty();
        $.each(customer.appointments, function (index, appointment) {
            if (
                GlobalVariables.user.role_slug === Backend.DB_SLUG_PROVIDER &&
                parseInt(appointment.id_users_provider) !==
                    GlobalVariables.user.id
            ) {
                return true; // continue
            }

            if (
                GlobalVariables.user.role_slug === Backend.DB_SLUG_SECRETARY &&
                GlobalVariables.secretaryProviders.indexOf(
                    appointment.id_users_provider
                ) === -1
            ) {
                return true; // continue
            }

            var start = GeneralFunctions.formatDate(
                Date.parse(appointment.start_datetime),
                GlobalVariables.dateFormat,
                true
            );
            var end = GeneralFunctions.formatDate(
                Date.parse(appointment.end_datetime),
                GlobalVariables.dateFormat,
                true
            );
            var html =
                '<div class="appointment-row" data-id="' +
                appointment.id +
                '">' +
                start +
                " - " +
                end +
                "<br>" +
                appointment.service.name +
                ", " +
                appointment.provider.first_name +
                " " +
                appointment.provider.last_name +
                (appointment.pet ? "<br>" + appointment.pet.title : "") +
                "</div>";
            $("#customer-appointments").append(html);
        });

        $("#appointment-details").empty();

        $("#customer-pets").empty();
        $.each(customer.pets, function (index, pet) {
            var html =
                '<div class="pet-row" data-id="' +
                pet.id +
                '">' +
                pet.name +
                " - " +
                pet.age +
                "<br>" +
                pet.breed +
                ", " +
                pet.colours +
                "</div>";
            $("#customer-pets").append(html);
        });
        $("#pet-summary").empty();
        $("#pet-details").attr("data-id", "");

        $("#pet_history").find("tbody").empty();
    };

    /**
     * Filter customer records.
     *
     * @param {String} key This key string is used to filter the customer records.
     * @param {Number} selectId Optional, if set then after the filter operation the record with the given
     * ID will be selected (but not displayed).
     * @param {Boolean} display Optional (false), if true then the selected record will be displayed on the form.
     */
    CustomersHelper.prototype.filter = function (key, selectId, display) {
        display = display || false;

        var postUrl =
            GlobalVariables.baseUrl +
            "/index.php/backend_api/ajax_filter_customers";
        var postData = {
            csrfToken: GlobalVariables.csrfToken,
            key: key,
        };

        $.post(
            postUrl,
            postData,
            function (response) {
                if (!GeneralFunctions.handleAjaxExceptions(response)) {
                    return;
                }

                this.filterResults = response;

                $("#filter-customers .results").html("");
                $.each(
                    response,
                    function (index, customer) {
                        var html = this.getFilterHtml(customer);
                        $("#filter-customers .results").append(html);
                    }.bind(this)
                );
                if (response.length == 0) {
                    $("#filter-customers .results").html(
                        "<em>" + EALang.no_records_found + "</em>"
                    );
                }

                if (selectId != undefined) {
                    this.select(selectId, display);
                }
            }.bind(this),
            "json"
        ).fail(GeneralFunctions.ajaxFailureHandler);
    };

    /**
     * Get the filter results row HTML code.
     *
     * @param {Object} customer Contains the customer data.
     *
     * @return {String} Returns the record HTML code.
     */
    CustomersHelper.prototype.getFilterHtml = function (customer) {
        var name = customer.last_name.toUpperCase() + " " + customer.first_name;
        var info = customer.email;
        info =
            customer.phone_number != "" && customer.phone_number != null
                ? info + ", " + customer.phone_number
                : info;

        var html =
            '<div class="entry" data-id="' +
            customer.id +
            '">' +
            "<strong>" +
            name +
            "</strong><br>" +
            info +
            "</div><hr>";

        return html;
    };

    /**
     * Select a specific record from the current filter results.
     *
     * If the customer id does not exist in the list then no record will be selected.
     *
     * @param {Number} id The record id to be selected from the filter results.
     * @param {Boolean} display Optional (false), if true then the method will display the record
     * on the form.
     */
    CustomersHelper.prototype.select = function (id, display) {
        display = display || false;

        $("#filter-customers .selected").removeClass("selected");

        $("#filter-customers .entry").each(function () {
            if ($(this).attr("data-id") == id) {
                $(this).addClass("selected");
                return false;
            }
        });

        if (display) {
            $.each(
                this.filterResults,
                function (index, customer) {
                    if (customer.id == id) {
                        this.display(customer);
                        $(
                            "#edit-customer, #delete-customer, #merge-customer"
                        ).prop("disabled", false);
                        return false;
                    }
                }.bind(this)
            );
        }
    };

    /**
     * Display appointment details on customers backend page.
     *
     * @param {Object} appointment Appointment data
     */
    CustomersHelper.prototype.displayAppointment = function (appointment) {
        var start = GeneralFunctions.formatDate(
            Date.parse(appointment.start_datetime),
            GlobalVariables.dateFormat,
            true
        );
        var end = GeneralFunctions.formatDate(
            Date.parse(appointment.end_datetime),
            GlobalVariables.dateFormat,
            true
        );

        var html =
            "<div>" +
            "<strong>" +
            appointment.service.name +
            "</strong><br>" +
            appointment.provider.first_name +
            " " +
            appointment.provider.last_name +
            "<br>" +
            start +
            " - " +
            end +
            "<br>" +
            "</div>";

        $("#appointment-details").html(html).removeClass("hidden");
    };

    /**
     * Display pet details on customers backend page.
     *
     * @param {Object} pet Pet data
     */
    CustomersHelper.prototype.displayPet = function (pet) {
        var dob = GeneralFunctions.formatDate(
            Date.parse(pet.dob),
            GlobalVariables.dateFormat,
            false
        );

        var html =
            "<div>" +
            "<strong>" +
            pet.name +
            ", " +
            pet.age +
            "</strong><br>" +
            pet.breed +
            ", " +
            pet.colours +
            ", " +
            dob +
            "<br>" +
            pet.sex_name +
            ", " +
            pet.nature_name +
            (pet.pathology ? "<br>" + pet.pathology : "") +
            "<br>" +
            "</div>";

        $("#pet-details").attr("data-id", pet.id).removeClass("hidden");
        $("#pet-summary").html(html);
        $("#pet_active").prop("checked", pet.active == 1 ? true : false);

        var history_table = $("#pet_history");
        history_table.find("tbody").empty();
        if (pet && pet.appointments)
            $.each(pet.appointments, function (i, app) {
                var tr = $("<tr></tr>").appendTo(history_table.find("tbody"));
                tr.append(
                    "<td>" +
                        (app.start_datetime
                            ? GeneralFunctions.formatDate(
                                  app.start_datetime,
                                  GlobalVariables.dateFormat,
                                  false
                              )
                            : "") +
                        "</td>"
                );
                tr.append("<td>" + (app.service_name ?? "") + "</td>");
                tr.append("<td>" + (app.provider_name ?? "") + "</td>");
                tr.append("<td>" + (app.depth ?? "") + "</td>");
                tr.append("<td>" + (app.speed ?? "") + "</td>");
                tr.append("<td>" + (app.time ?? "") + "</td>");
                tr.append(
                    "<td>" +
                        GeneralFunctions.HtmlAbbreviate(app.observ_previous) +
                        "</td>"
                );
                tr.append(
                    "<td>" +
                        GeneralFunctions.HtmlAbbreviate(app.observ_current) +
                        "</td>"
                );
            });

        $("#pet_history").removeClass("hidden");
    };

    window.CustomersHelper = CustomersHelper;
})();
