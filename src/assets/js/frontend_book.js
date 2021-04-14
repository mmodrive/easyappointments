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

window.FrontendBook = window.FrontendBook || {};

/**
 * Frontend Book
 *
 * This module contains functions that implement the book appointment page functionality. Once the
 * initialize() method is called the page is fully functional and can serve the appointment booking
 * process.
 *
 * @module FrontendBook
 */
(function (exports) {

    'use strict';

    /**
     * Contains terms and conditions consent.
     *
     * @type {Object}
     */
    var termsAndConditionsConsent;

    /**
     * Contains privacy policy consent.
     *
     * @type {Object}
     */
    var privacyPolicyConsent;

    /**
     * Determines the functionality of the page.
     *
     * @type {Boolean}
     */
    exports.manageMode = false;

    /**
     * This method initializes the book appointment page.
     *
     * @param {Boolean} bindEventHandlers (OPTIONAL) Determines whether the default
     * event handlers will be bound to the dom elements.
     * @param {Boolean} manageMode (OPTIONAL) Determines whether the customer is going
     * to make  changes to an existing appointment rather than booking a new one.
     */
    exports.initialize = function (bindEventHandlers, manageMode) {
        bindEventHandlers = bindEventHandlers || true;
        manageMode = manageMode || false;

        if (window.console === undefined) {
            window.console = function () {
            }; // IE compatibility
        }
        
        if (GlobalVariables.displayCookieNotice) {
            cookieconsent.initialise({
                palette: {
                    popup: {
                        background: '#ffffffbd',
                        text: '#666666'
                    },
                    button: {
                        background: '#3DD481',
                        text: '#ffffff'
                    }
                },
                content: {
                    message: EALang.website_using_cookies_to_ensure_best_experience,
                    dismiss: 'OK'
                },
            });

            $('.cc-link').replaceWith(
                $('<a/>', {
                    'data-toggle': 'modal',
                    'data-target': '#cookie-notice-modal',
                    'href': '#',
                    'class': 'cc-link',
                    'text': $('.cc-link').text()
                })
            );
        }

        FrontendBook.manageMode = manageMode;

        // Initialize page's components (tooltips, datepickers etc).
        $('.book-step').qtip({
            position: {
                my: 'top center',
                at: 'bottom center'
            },
            style: {
                classes: 'qtip-green qtip-shadow custom-qtip'
            }
        });

        $('#select-date').datepicker({
            dateFormat: 'dd-mm-yy',
            firstDay: 0,
            minDate: 0,
            defaultDate: Date.today(),

            dayNames: [
                EALang.sunday, EALang.monday, EALang.tuesday, EALang.wednesday,
                EALang.thursday, EALang.friday, EALang.saturday],
            dayNamesShort: [EALang.sunday.substr(0, 3), EALang.monday.substr(0, 3),
                EALang.tuesday.substr(0, 3), EALang.wednesday.substr(0, 3),
                EALang.thursday.substr(0, 3), EALang.friday.substr(0, 3),
                EALang.saturday.substr(0, 3)],
            dayNamesMin: [EALang.sunday.substr(0, 2), EALang.monday.substr(0, 2),
                EALang.tuesday.substr(0, 2), EALang.wednesday.substr(0, 2),
                EALang.thursday.substr(0, 2), EALang.friday.substr(0, 2),
                EALang.saturday.substr(0, 2)],
            monthNames: [EALang.january, EALang.february, EALang.march, EALang.april,
                EALang.may, EALang.june, EALang.july, EALang.august, EALang.september,
                EALang.october, EALang.november, EALang.december],
            prevText: EALang.previous,
            nextText: EALang.next,
            currentText: EALang.now,
            closeText: EALang.close,

            onSelect: function (dateText, instance) {
                FrontendBookApi.getAvailableHours($(this).datepicker('getDate').toString('yyyy-MM-dd'));
                FrontendBook.updateConfirmFrame();
            },

            onChangeMonthYear: function (year, month, instance) {
                var currentDate = new Date(year, month - 1, 1);
                FrontendBookApi.getUnavailableDates($('#select-provider').val(), $('#select-service').val(),
                    currentDate.toString('yyyy-MM-dd'));
            }
        });

        $('#pet_dob').datepicker({
            dateFormat: 'dd-mm-yy',
            firstDay: 0,
            changeYear: true,
            changeMonth: true,
            yearRange: "-30:+0",

            dayNames: [
                EALang.sunday, EALang.monday, EALang.tuesday, EALang.wednesday,
                EALang.thursday, EALang.friday, EALang.saturday],
            dayNamesShort: [EALang.sunday.substr(0, 3), EALang.monday.substr(0, 3),
                EALang.tuesday.substr(0, 3), EALang.wednesday.substr(0, 3),
                EALang.thursday.substr(0, 3), EALang.friday.substr(0, 3),
                EALang.saturday.substr(0, 3)],
            dayNamesMin: [EALang.sunday.substr(0, 2), EALang.monday.substr(0, 2),
                EALang.tuesday.substr(0, 2), EALang.wednesday.substr(0, 2),
                EALang.thursday.substr(0, 2), EALang.friday.substr(0, 2),
                EALang.saturday.substr(0, 2)],
            monthNames: [EALang.january, EALang.february, EALang.march, EALang.april,
                EALang.may, EALang.june, EALang.july, EALang.august, EALang.september,
                EALang.october, EALang.november, EALang.december],
            prevText: EALang.previous,
            nextText: EALang.next,
            currentText: EALang.now,
            closeText: EALang.close,
        });

        // Bind the event handlers (might not be necessary every time we use this class).
        if (bindEventHandlers) {
            _bindEventHandlers();
        }

        // If the manage mode is true, the appointments data should be loaded by default.
        if (FrontendBook.manageMode) {
            _applyAppointmentData(GlobalVariables.appointmentData,
                GlobalVariables.providerData, GlobalVariables.customerData);
        } else {
            var $selectProvider = $('#select-provider');
            var $selectService = $('#select-service');

            // Check if a specific service was selected (via URL parameter).
            var selectedServiceId = GeneralFunctions.getUrlParameter(location.href, 'service') ||
                                    GeneralFunctions.getUrlParameter(location.href, 'sid');

            if (selectedServiceId && $selectService.find('option[value="' + selectedServiceId + '"]').length > 0) {
                $selectService.val(selectedServiceId);
            }

            $selectService.trigger('change'); // Load the available hours.

            // Check if a specific provider was selected. 
            var selectedProviderId = GeneralFunctions.getUrlParameter(location.href, 'provider') ||
                                    GeneralFunctions.getUrlParameter(location.href, 'pid');

            if (selectedProviderId && $selectProvider.find('option[value="' + selectedProviderId + '"]').length === 0) {
                // Select a service of this provider in order to make the provider available in the select box. 
                for (var index in GlobalVariables.availableProviders) {
                    var provider = GlobalVariables.availableProviders[index];

                    if (provider.id === selectedProviderId && provider.services.length > 0) {
                        $selectService
                            .val(provider.services[0])
                            .trigger('change');
                    }
                }
            }

            if (selectedProviderId && $selectProvider.find('option[value="' + selectedProviderId + '"]').length > 0) {
                $selectProvider
                    .val(selectedProviderId)
                    .trigger('change');
            }

        }
    };

    /**
     * This method binds the necessary event handlers for the book appointments page.
     */
    function _bindEventHandlers() {
        /**
         * Event: Selected Provider "Changed"
         *
         * Whenever the provider changes the available appointment date - time periods must be updated.
         */
        $('#select-provider').change(function () {
            FrontendBookApi.getUnavailableDates($(this).val(), $('#select-service').val(),
                $('#select-date').datepicker('getDate').toString('yyyy-MM-dd'));
            FrontendBook.updateConfirmFrame();
        });

        /**
         * Event: Selected Service "Changed"
         *
         * When the user clicks on a service, its available providers should
         * become visible.
         */
        $('#select-service').change(function () {
            var currServiceId = $('#select-service').val();
            $('#select-provider').empty();

            $.each(GlobalVariables.availableProviders.sort(function(a,b) {
                    var x = a.first_name + ' ' + a.last_name;
                    var y = b.first_name + ' ' + b.last_name;
                    if (x < y) {return -1;}
                    if (x > y) {return 1;}
                    return 0;
                }), 
                function (indexProvider, provider) {
                $.each(provider.services, function (indexService, serviceId) {
                    // If the current provider is able to provide the selected service,
                    // add him to the listbox.
                    if (serviceId == currServiceId) {
                        var optionHtml = '<option value="' + provider.id + '">'
                            + provider.first_name + ' ' + provider.last_name
                            + '</option>';
                        $('#select-provider').append(optionHtml);
                    }
                });
            });

            // Add the "Any Provider" entry.
            if ($('#select-provider option').length >= 1) {
                $('#select-provider').prepend(new Option('- ' + EALang.any_provider + ' -', 'any-provider'));
                $('#select-provider').val('any-provider');
            }
            
            FrontendBookApi.getUnavailableDates($('#select-provider').val(), $(this).val(),
            $('#select-date').datepicker('getDate').toString('yyyy-MM-dd'));
            FrontendBook.updateConfirmFrame();
            _updateServiceDescription($('#select-service').val(), $('#service-description'));

            $.each(GlobalVariables.availableServices, function (index, service) {
                if (service.id == currServiceId && service.id_users_default_provider) {
                    $('#select-provider').val(service.id_users_default_provider).change();
                    return false;
                }
            });

            if ($('#select-provider option').length <= 2)
                $('#select-provider').closest('div.form-group').hide();
            else
                $('#select-provider').closest('div.form-group').show();
        });

        /**
         * Event: Next Step Button "Clicked"
         *
         * This handler is triggered every time the user pressed the "next" button on the book wizard.
         * Some special tasks might be performed, depending the current wizard step.
         */
        $('.button-next').click(function () {
            // If we are on the first step and there is not provider selected do not continue
            // with the next step.
            if ($(this).attr('data-step_index') === '1') {
                if ($('#select-provider').val() == null)
                    return;

                var pets_option = 'N'; // Default value of NO
                $.each(GlobalVariables.availableServices, function (index, service) {
                    if (service.id == $('#select-service').val()) {
                        pets_option = service.pets_option;
                    }
                });
                if( pets_option == 'N' ){
                    $('#step-5').addClass('disabled-step');
                    $('#pet-details').hide();
                }
                else{
                    $('#step-5').removeClass('disabled-step');
                    $('#pet-details').show();
                }
            }

            // If we are on the 2nd tab then the user should have an appointment hour
            // selected.
            if ($(this).attr('data-step_index') === '2') {
                if ($('.selected-hour').length == 0) {
                    if ($('#select-hour-prompt').length == 0) {
                        $('#available-hours').append('<br><br>'
                            + '<span id="select-hour-prompt" class="text-danger">'
                            + EALang.appointment_hour_missing
                            + '</span>');
                    }
                    return;
                }
            }

            // If we are on the 3rd tab then we will need to validate the user's
            // input before proceeding to the next step.
            if ($(this).attr('data-step_index') === '3') {
                if (!_validateLoginForm()) {
                    return; // Validation failed, do not continue.
                } 
                // button-next-3 is Login, button-reset-3 is Reset
                if( this.id == 'button-next-3' ){
                    //attempt to login and retrieve customer data
                    if ( $('#login_success').val() == '0' ){
                        $(this).fadeOut('fast', function(){ $(this).fadeIn('fast');} );
                        FrontendBookApi.loginCustomer( $('#email').val(), $('#password').val());
                        return;
                    }
                }
            }

            // If we are on the 4th tab then we will need to validate the user's
            // input before proceeding to the next step.
            if ($(this).attr('data-step_index') === '4') {
                if (!_validateCustomerForm()) {
                    return; // Validation failed, do not continue.
                } else {
                    FrontendBook.updateConfirmFrame();

                    var $acceptToTermsAndConditions = $('#accept-to-terms-and-conditions');
                    if ($acceptToTermsAndConditions.length && $acceptToTermsAndConditions.prop('checked') === true) {
                        var newTermsAndConditionsConsent = {
                            first_name: $('#first-name').val(),
                            last_name: $('#last-name').val(),
                            email: $('#email').val(),
                            type: 'terms-and-conditions'
                        };

                        if (JSON.stringify(newTermsAndConditionsConsent) !== JSON.stringify(termsAndConditionsConsent)) {
                            termsAndConditionsConsent = newTermsAndConditionsConsent;
                            FrontendBookApi.saveConsent(termsAndConditionsConsent);
                        }
                    }

                    var $acceptToPrivacyPolicy = $('#accept-to-privacy-policy');
                    if ($acceptToPrivacyPolicy.length && $acceptToPrivacyPolicy.prop('checked') === true) {
                        var newPrivacyPolicyConsent = {
                            first_name: $('#first-name').val(),
                            last_name: $('#last-name').val(),
                            email: $('#email').val(),
                            type: 'privacy-policy'
                        };

                        if (JSON.stringify(newPrivacyPolicyConsent) !== JSON.stringify(privacyPolicyConsent)) {
                            privacyPolicyConsent = newPrivacyPolicyConsent;
                            FrontendBookApi.saveConsent(privacyPolicyConsent);
                        }
                    }
                }
            }

            // If we are on the 5th tab then we will need to validate the user's
            // input before proceeding to the next step.
            if ($(this).attr('data-step_index') === '5') {
                if (!_validatePetForm()) {
                    return; // Validation failed, do not continue.
                } else {
                    FrontendBook.updateConfirmFrame();
                }
            }

            // Display the next step tab (uses jquery animation effect).
            var nextTabIndex = parseInt($(this).data('step_index')) + 1;
            while( $('#step-' + nextTabIndex).hasClass('disabled-step') )
                nextTabIndex++;

            $(this).parents().eq(1).hide('fade', function () {
                $('.active-step').removeClass('active-step');
                $('#step-' + nextTabIndex).addClass('active-step');
                $('#wizard-frame-' + nextTabIndex).show('fade');
            });
        });

        /**
         * Event: Back Step Button "Clicked"
         *
         * This handler is triggered every time the user pressed the "back" button on the
         * book wizard.
         */
        $('.button-back').click(function () {
            var prevTabIndex = parseInt($(this).attr('data-step_index')) - 1;
            while( $('#step-' + prevTabIndex).hasClass('disabled-step') )
                prevTabIndex--;

            $(this).parents().eq(1).hide('fade', function () {
                $('.active-step').removeClass('active-step');
                $('#step-' + prevTabIndex).addClass('active-step');
                $('#wizard-frame-' + prevTabIndex).show('fade');
            });
        });

        /**
         * Event: Available Hour "Click"
         *
         * Triggered whenever the user clicks on an available hour
         * for his appointment.
         */
        $('#available-hours').on('click', '.available-hour', function () {
            $('.selected-hour').removeClass('selected-hour');
            $(this).addClass('selected-hour');
            FrontendBook.updateConfirmFrame();
        });

        if (FrontendBook.manageMode) {
            /**
             * Event: Cancel Appointment Button "Click"
             *
             * When the user clicks the "Cancel" button this form is going to be submitted. We need
             * the user to confirm this action because once the appointment is cancelled, it will be
             * delete from the database.
             *
             * @param {jQuery.Event} event
             */
            $('#cancel-appointment').click(function (event) {
                var buttons = [
                    {
                        text: 'OK',
                        click: function () {
                            if ($('#cancel-reason').val() === '') {
                                $('#cancel-reason').css('border', '2px solid red');
                                return;
                            }
                            $('#cancel-appointment-form textarea').val($('#cancel-reason').val());
                            $('#cancel-appointment-form').submit();
                        }
                    },
                    {
                        text: EALang.cancel,
                        click: function () {
                            $('#message_box').dialog('close');
                        }
                    }
                ];

                GeneralFunctions.displayMessageBox(EALang.cancel_appointment_title,
                    EALang.write_appointment_removal_reason, buttons);

                $('#message_box').append('<textarea id="cancel-reason" rows="3"></textarea>');
                $('#cancel-reason').css('width', '100%');
                return false;
            });

            $('#delete-personal-information').on('click', function () {
                var buttons = [
                    {
                        text: EALang.delete,
                        click: function () {
                            FrontendBookApi.deletePersonalInformation(GlobalVariables.customerToken);
                        }
                    },
                    {
                        text: EALang.cancel,
                        click: function () {
                            $('#message_box').dialog('close');
                        }
                    }
                ];

                GeneralFunctions.displayMessageBox(EALang.delete_personal_information,
                    EALang.delete_personal_information_prompt, buttons);
            });
        }

        /**
         * Event: Book Appointment Form "Submit"
         *
         * Before the form is submitted to the server we need to make sure that
         * in the meantime the selected appointment date/time wasn't reserved by
         * another customer or event.
         *
         * @param {jQuery.Event} event
         */
        $('#book-appointment-submit').click(function (event) {
            FrontendBookApi.registerAppointment();
        });

        /**
         * Event: Refresh captcha image.
         *
         * @param {jQuery.Event} event
         */
        $('.captcha-title small').click(function (event) {
            $('.captcha-image').attr('src', GlobalVariables.baseUrl + '/index.php/captcha?' + Date.now());
        });


        $('#select-date').on('mousedown', '.ui-datepicker-calendar td', function (event) {
            setTimeout(function () {
                FrontendBookApi.applyPreviousUnavailableDates(); // New jQuery UI version will replace the td elements.
            }, 300); // There is no draw event unfortunately.
        })

        $('#pet_id').change(function (event) {
            var values = $(this).find('option:selected').data('pet');
            if( values )
                $.each(values, function (key, value) {
                    if(key == 'dob')
                        $('.form-control[id="pet_' + key + '"]').datepicker('setDate',
                            Date.parseExact(value, 'yyyy-MM-dd HH:mm:ss'));
                    else
                        $('.form-control[id="pet_' + key + '"]').val(value);
                });
            else
                $('.form-control[id^="pet_"]:not([id="pet_id"])').val('');
        });
    }

     /**
     * This function validates the customer's data input. The user cannot continue
     * without passing all the validation checks.
     *
     * @return {Boolean} Returns the validation result.
     */
    function _validateLoginForm() {
        $('#wizard-frame-3 .has-error').removeClass('has-error');
        $('#wizard-frame-3 label.text-danger').removeClass('text-danger');

        try {
            // Validate required fields.
            var missingRequiredField = false;
            $('#wizard-frame-3 .required').each(function () {
                if ($(this).val() == '') {
                    $(this).parents('.form-group').addClass('has-error');
                    missingRequiredField = true;
                }
            });
            if (missingRequiredField) {
                throw EALang.fields_are_required;
            }

            // Validate email address.
            if (!GeneralFunctions.validateEmail($('#email').val())) {
                $('#email').parents('.form-group').addClass('has-error');
                throw EALang.invalid_email;
            }

            return true;
        } catch (exc) {
            $('#form-3-message').text(exc);
            return false;
        }
    }

    /**
     * This function validates the customer's data input. The user cannot continue
     * without passing all the validation checks.
     *
     * @return {Boolean} Returns the validation result.
     */
    function _validateCustomerForm() {
        $('#wizard-frame-4 .has-error').removeClass('has-error');
        $('#wizard-frame-4 label.text-danger').removeClass('text-danger');

        try {
            // Validate required fields.
            var missingRequiredField = false;
            $('#wizard-frame-4 .required').each(function () {
                if ($(this).val() == '') {
                    $(this).parents('.form-group').addClass('has-error');
                    missingRequiredField = true;
                }
            });
            if (missingRequiredField) {
                throw EALang.fields_are_required;
            }

            var $acceptToTermsAndConditions = $('#accept-to-terms-and-conditions');
            if ($acceptToTermsAndConditions.length && !$acceptToTermsAndConditions.prop('checked')) {
                $acceptToTermsAndConditions.parents('label').addClass('text-danger');
                throw EALang.fields_are_required;
            }

            var $acceptToPrivacyPolicy = $('#accept-to-privacy-policy');
            if ($acceptToPrivacyPolicy.length && !$acceptToPrivacyPolicy.prop('checked')) {
                $acceptToPrivacyPolicy.parents('label').addClass('text-danger');
                throw EALang.fields_are_required;
            }

            return true;
        } catch (exc) {
            $('#form-4-message').text(exc);
            return false;
        }
    }

    /**
     * This function validates the customer's data input. The user cannot continue
     * without passing all the validation checks.
     *
     * @return {Boolean} Returns the validation result.
     */
    function _validatePetForm() {
        $('#wizard-frame-5 .has-error').removeClass('has-error');
        $('#wizard-frame-5 label.text-danger').removeClass('text-danger');

        try {
            // Validate required fields.
            var missingRequiredField = false;
            $('#wizard-frame-5 .required').each(function () {
                if ($(this).val() == '') {
                    $(this).closest('.form-group').addClass('has-error');
                    missingRequiredField = true;
                }
            });
            if (missingRequiredField) {
                throw EALang.fields_are_required;
            }

            // Validate email addresses.
            if ($('#pet_vet_email').val() && !GeneralFunctions.validateEmail($('#pet_vet_email').val())) {
                $('#pet_vet_email').closest('.form-group').addClass('has-error');
                throw EALang.invalid_email;
            }
            if ($('#pet_therapist_email').val() && !GeneralFunctions.validateEmail($('#pet_therapist_email').val())) {
                $('#pet_therapist_email').closest('.form-group').addClass('has-error');
                throw EALang.invalid_email;
            }

            return true;
        } catch (exc) {
            $('#form-5-message').text(exc);
            return false;
        }
    }

    /**
     * Every time this function is executed, it updates the confirmation page with the latest
     * customer settings and input for the appointment booking.
     */
    exports.updateConfirmFrame = function () {
        if ($('.selected-hour').text() === '') {
            return;
        }

        // Appointment Details
        var selectedDate = $('#select-date').datepicker('getDate');

        if (selectedDate !== null) {
            selectedDate = GeneralFunctions.formatDate(selectedDate, GlobalVariables.dateFormat);
        }

        var selServiceId = $('#select-service').val();
        var servicePrice;
        var serviceCurrency;

        $.each(GlobalVariables.availableServices, function (index, service) {
            if (service.id == selServiceId) {
                servicePrice = service.price;
                serviceCurrency = service.currency;
                return false; // break loop
            }
        });

        var html =
            "<h4>" +
            $("#select-service option:selected").text() +
            "</h4>" +
            "<p>" +
            '<strong class="text-primary">' +
            $("#select-provider option:selected").text() +
            "<br>" +
            selectedDate +
            " " +
            $(".selected-hour").text() +
            "<br>" +
            (serviceCurrency.includes("{price}")
                ? serviceCurrency.replace("{price}", servicePrice)
                : servicePrice + " " + serviceCurrency) +
            "</strong>" +
            "</p>";

        $('#appointment-details').html(html);

        // Customer Details
        var firstName = GeneralFunctions.escapeHtml($('#first-name').val());
        var lastName = GeneralFunctions.escapeHtml($('#last-name').val());
        var phoneNumber = GeneralFunctions.escapeHtml($('#phone-number').val());
        var email = GeneralFunctions.escapeHtml($('#email').val());
        var address = GeneralFunctions.escapeHtml($('#address').val());
        var city = GeneralFunctions.escapeHtml($('#city').val());
        var zipCode = GeneralFunctions.escapeHtml($('#zip-code').val());

        html =
            '<h4>' + firstName + ' ' + lastName + '</h4>' +
            '<p>' +
            EALang.phone + ': ' + phoneNumber +
            '<br/>' +
            EALang.email + ': ' + email +
            '<br/>' +
            EALang.address + ': ' + address +
            '<br/>' +
            EALang.city + ': ' + city +
            '<br/>' +
            EALang.zip_code + ': ' + zipCode +
            '</p>';

        $('#customer-details').html(html);

        // Pet Details
        var pet_name = GeneralFunctions.escapeHtml($('#pet_name').val());
        var pet_breed = GeneralFunctions.escapeHtml($('#pet_breed').val());
        var pet_colours = GeneralFunctions.escapeHtml($('#pet_colours').val());
        var pet_sex = GeneralFunctions.escapeHtml($('#pet_sex option:selected').text());
        var pet_dob = '';
        if($('#pet_dob').datepicker('getDate'))
            pet_dob = GeneralFunctions.formatDate($('#pet_dob').datepicker('getDate'), GlobalVariables.dateFormat);
        var pet_nature = GeneralFunctions.escapeHtml($('#pet_nature option:selected').text());
        var pet_attachment = $('#pet_attachment').get(0).files.length;

        html =
            '<h4>' + pet_name + '</h4>' +
            '<p>' +
            EALang.pet_breed + ': ' + pet_breed +
            '<br/>' +
            EALang.pet_colours + ': ' + pet_colours +
            '<br/>' +
            EALang.pet_sex + ': ' + pet_sex +
            '<br/>' +
            EALang.pet_dob + ': ' + pet_dob +
            '<br/>' +
            EALang.pet_nature + ': ' + pet_nature +
            '<br/>' +
            EALang.attachment + ': ' + pet_attachment +
            '</p>';

        $('#pet-details').html(html);

        // Update appointment form data for submission to server when the user confirms
        // the appointment.
        var postData = {};

        postData.customer = {
            last_name: $('#last-name').val(),
            first_name: $('#first-name').val(),
            email: $('#email').val(),
            phone_number: $('#phone-number').val(),
            address: $('#address').val(),
            city: $('#city').val(),
            zip_code: $('#zip-code').val(),
            settings: {
                password: $('#password').val()
            }
        };

        postData.appointment = {
            start_datetime: $('#select-date').datepicker('getDate').toString('yyyy-MM-dd')
            + ' ' + Date.parse($('.selected-hour').text()).toString('HH:mm') + ':00',
            end_datetime: _calcEndDatetime(),
            notes: $('#notes').val(),
            is_unavailable: false,
            id_users_provider: $('#select-provider').val(),
            id_services: $('#select-service').val()
        };

        if( !$('#step-5').hasClass('disabled-step') ){
            postData.pet = {};
            $('.form-control[id^="pet_"]').each(function () {
                if ($(this).val() != ''){ 
                    if($(this).is(':input[type="file"]'))
                        postData.pet[this.id.substr(4)] = this.id;
                    else if(this.id == 'pet_dob')
                        postData.pet[this.id.substr(4)] = $(this).datepicker('getDate').toString('yyyy-MM-dd');
                    else if(this.id == 'pet_id' && $(this).val() == 'new'){}                        
                    else
                        postData.pet[this.id.substr(4)] = $(this).val();
                }
            });
        }

        postData.manage_mode = FrontendBook.manageMode;

        if (FrontendBook.manageMode) {
            postData.appointment.id = GlobalVariables.appointmentData.id;
            postData.customer.id = GlobalVariables.customerData.id;
        }
        $('input[name="csrfToken"]').val(GlobalVariables.csrfToken);
        $('input[name="post_data"]').val(JSON.stringify(postData));
    };

    /**
     * This method calculates the end datetime of the current appointment.
     * End datetime is depending on the service and start datetime fields.
     *
     * @return {String} Returns the end datetime in string format.
     */
    function _calcEndDatetime() {
        // Find selected service duration.
        var selServiceDuration = undefined;

        $.each(GlobalVariables.availableServices, function (index, service) {
            if (service.id == $('#select-service').val()) {
                selServiceDuration = service.duration;
                return false; // Stop searching ...
            }
        });

        // Add the duration to the start datetime.
        var startDatetime = $('#select-date').datepicker('getDate').toString('dd-MM-yyyy')
            + ' ' + Date.parse($('.selected-hour').text()).toString('HH:mm');
        startDatetime = Date.parseExact(startDatetime, 'dd-MM-yyyy HH:mm');
        var endDatetime = undefined;

        if (selServiceDuration !== undefined && startDatetime !== null) {
            endDatetime = startDatetime.add({'minutes': parseInt(selServiceDuration)});
        } else {
            endDatetime = new Date();
        }

        return endDatetime.toString('yyyy-MM-dd HH:mm:ss');
    }

    /**
     * This method applies the appointment's data to the wizard so
     * that the user can start making changes on an existing record.
     *
     * @param {Object} customer Selected customer's data.
     *
     */
    exports.applyCustomerData = function (customer) {
        try {
            // Apply Customer's Data
            $('#last-name').val(customer.last_name);
            $('#first-name').val(customer.first_name);
            $('#email').val(customer.email);
            $('#phone-number').val(customer.phone_number);
            $('#address').val(customer.address);
            $('#city').val(customer.city);
            $('#zip-code').val(customer.zip_code);

            var pet_select = $('#pet_id');
            pet_select.find('option:nth-child(n+2)').remove();
            $.each(customer.pets, function(iPet, pet){
                pet_select.append($('<option>', { 
                    value: pet.id,
                    text : pet.name,
                    'data-pet': JSON.stringify(pet)
                }));
            } );
            if( pet_select.find('option').length <= 1 ){
                pet_select.val('new');
                pet_select.closest('.form-group').hide();
            }
            else{
                if( pet_select.find('option').length == 2 )
                    pet_select.val(pet_select.find('option:nth-child(2)').val());
                else
                    pet_select.val('new');
                pet_select.closest('.form-group').show();
            }

            pet_select.change();

            return true;
        } catch (exc) {
            return false;
        }
    }

    /**
     * This method applies the appointment's data to the wizard so
     * that the user can start making changes on an existing record.
     *
     * @param {Object} appointment Selected appointment's data.
     * @param {Object} provider Selected provider's data.
     * @param {Object} customer Selected customer's data.
     *
     * @return {Boolean} Returns the operation result.
     */
    function _applyAppointmentData(appointment, provider, customer) {
        try {
            // Select Service & Provider
            $('#select-service').val(appointment.id_services).trigger('change');
            $('#select-provider').val(appointment.id_users_provider);

            // Set Appointment Date
            $('#select-date').datepicker('setDate',
                Date.parseExact(appointment.start_datetime, 'yyyy-MM-dd HH:mm:ss'));
            FrontendBookApi.getAvailableHours($('#select-date').val());

            // Apply Customer's Data
            FrontendBook.applyCustomerData(customer);
            var appointmentNotes = (appointment.notes !== null)
                ? appointment.notes : '';
            $('#notes').val(appointmentNotes);

            FrontendBook.updateConfirmFrame();

            return true;
        } catch (exc) {
            return false;
        }
    }

    /**
     * This method updates a div's html content with a brief description of the
     * user selected service (only if available in db). This is useful for the
     * customers upon selecting the correct service.
     *
     * @param {Number} serviceId The selected service record id.
     * @param {Object} $div The destination div jquery object (e.g. provide $('#div-id')
     * object as value).
     */
    function _updateServiceDescription(serviceId, $div) {
        var html = '';

        $.each(GlobalVariables.availableServices, function (index, service) {
            if (service.id == serviceId) { // Just found the service.
                html = '<strong>' + service.name + ' </strong>';

                if (service.description != '' && service.description != null) {
                    html += '<br>' + service.description + '<br>';
                }

                if (service.duration != '' && service.duration != null) {
                    html += '[' + EALang.duration + ' ' + service.duration + ' ' + EALang.minutes + ']';
                }

                if (service.price != '' && service.price != null) {
                    html +=
                        "[" +
                        EALang.price +
                        " " +
                        (service.currency.includes("{price}")
                            ? service.currency.replace("{price}", service.price)
                            : service.price + " " + service.currency) +
                        "]";
                }

                html += '<br>';

                return false;
            }
        });

        $div.html(html);

        if (html != '') {
            $div.show();
        } else {
            $div.hide();
        }
    }

})(window.FrontendBook);
