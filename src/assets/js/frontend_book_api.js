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

window.FrontendBookApi = window.FrontendBookApi || {};

/**
 * Frontend Book API
 *
 * This module serves as the API consumer for the booking wizard of the app.
 *
 * @module FrontendBookApi
 */
(function (exports) {

    'use strict';

    var unavailableDatesBackup;
    var selectedDateStringBackup;
    var processingUnavailabilities = false;

    /**
     * Get Available Hours
     *
     * This function makes an AJAX call and returns the available hours for the selected service,
     * provider and date.
     *
     * @param {String} selDate The selected date of which the available hours we need to receive.
     */
    exports.getAvailableHours = function (selDate) {
        $('#available-hours').empty();

        // Find the selected service duration (it is going to be send within the "postData" object).
        var selServiceDuration = 15; // Default value of duration (in minutes).
        $.each(GlobalVariables.availableServices, function (index, service) {
            if (service.id == $('#select-service').val()) {
                selServiceDuration = service.duration;
            }
        });

        // If the manage mode is true then the appointment's start date should return as available too.
        var appointmentId = FrontendBook.manageMode ? GlobalVariables.appointmentData.id : undefined;

        // Make ajax post request and get the available hours.
        var postUrl = GlobalVariables.baseUrl + '/index.php/appointments/ajax_get_available_hours';
        var postData = {
            csrfToken: GlobalVariables.csrfToken,
            service_id: $('#select-service').val(),
            provider_id: $('#select-preffered-provider').val(),
            selected_date: selDate,
            service_duration: selServiceDuration,
            manage_mode: FrontendBook.manageMode,
            appointment_id: appointmentId
        };

        $.post(postUrl, postData, function (response) {
            if (!GeneralFunctions.handleAjaxExceptions(response)) {
                return;
            }

            // if( Object.keys(response).length == 1 )
            //     $('#select-provider').val(Object.keys(response)[0]);
            
            var allAvailableHours = new Object();
            $.each(response, function (provider_id, availableHours) {
                $.each(availableHours, function (index, availableHour) {
                    if( availableHour in allAvailableHours )
                        allAvailableHours[availableHour].push(provider_id);
                    else
                        allAvailableHours[availableHour] = [provider_id];
                });
            });

            allAvailableHours = Object.keys(allAvailableHours).sort().reduce((r, k) => (r[k] = allAvailableHours[k], r), {});

            var currColumn = 1;
            var hour_count = 0;
            var timeFormat = GlobalVariables.timeFormat === 'regular' ? 'h:mm tt' : 'HH:mm';

            $.each(allAvailableHours, function (availableHour, provider_ids) {
                hour_count++;

                if (hour_count == 1) 
                    $('#available-hours').html('<div style="width:80px; float:left;"></div>');

                if ( hour_count % 10 == 0) {
                    currColumn++;
                    $('#available-hours').append('<div style="width:80px; float:left;"></div>');
                }

                var hourspan = $('<span class="available-hour">' + Date.parse(availableHour).toString(timeFormat) + '</span>');
                hourspan.attr('data-provider_ids', JSON.stringify(provider_ids));
                $('#available-hours div:eq(' + (currColumn - 1) + ')').append(hourspan).append('<br/>');
            });
            
            if(hour_count > 0){
                if (FrontendBook.manageMode) {
                    // Set the appointment's start time as the default selection.
                    $('.available-hour').filter(function () {
                        return $(this).text() === Date.parseExact(
                            GlobalVariables.appointmentData.start_datetime,
                            'yyyy-MM-dd HH:mm:ss').toString(timeFormat);
                    }).click();
                } else {
                    // Set the first available hour as the default selection.
                    $('.available-hour:eq(0)').click();
                }
    
                FrontendBook.updateConfirmFrame();
            } else {
                $('#available-hours').text(EALang.no_available_hours);
            }
        }, 'json').fail(GeneralFunctions.ajaxFailureHandler);
    };

    exports.loginCustomer = function(email, password) {
        var postUrl = GlobalVariables.baseUrl + '/index.php/appointments/ajax_check_customer_login';
        var postData = {
            'csrfToken': GlobalVariables.csrfToken,
            'email': email,
            'password': password
        };

        var $layer = $('<div/>');

        $.ajax({
            url: postUrl,
            method: 'post',
            data: postData,
            dataType: 'json',
            beforeSend: function (jqxhr, settings) {
                $layer
                    .appendTo('body')
                    .css({
                        background: 'white',
                        position: 'fixed',
                        top: '0',
                        left: '0',
                        height: '100vh',
                        width: '100vw',
                        opacity: '0.5'
                    });
            }
        })
        .done(function (response) {
            if (!GeneralFunctions.handleAjaxExceptions(response)) {
                return;
            }


            if (response != GlobalVariables.LOGIN_FAILURE) {
                FrontendBook.applyCustomerData(response);
                $('#login_success').val('1');
                $('#button-next-3').click();
                $('#login_success').val('0');
            } else {
                $('#login_success').val('0');
                $('#password').val('');
                $('#form-3-message').text(EALang['login_failed']);
            }
        })
        .always(function () {
            $layer.remove();
        });
    };

    /**
     * Register an appointment to the database.
     *
     * This method will make an ajax call to the appointments controller that will register
     * the appointment to the database.
     */
    exports.registerAppointment = function () {
        var $captchaText = $('.captcha-text');

        if ($captchaText.length > 0) {
            $captchaText.closest('.form-group').removeClass('has-error');
            if ($captchaText.val() === '') {
                $captchaText.closest('.form-group').addClass('has-error');
                return;
            }
        }

        var postData = new FormData();
        var formData = jQuery.parseJSON($('input[name="post_data"]').val());
        if( formData["pet"] && formData["pet"]["attachment"] ){
            var files_el = $('#' + formData["pet"]["attachment"]).get(0);
            $.each(files_el.files,function(iFile,file){
                    postData.append(formData["pet"]["attachment"] + '['+iFile+']', file);
                    });
            formData["pet"]["attachment"] = files_el.files.length;
        }
        // $.each(formData, function(key, value){
        //     fd.append(key, value);
        // })
        postData.append("csrfToken", GlobalVariables.csrfToken);
        postData.append("post_data", JSON.stringify(formData));

        if ($captchaText.length > 0) {
            postData.append("captcha", $captchaText.val());
        }

        if (GlobalVariables.manageMode) {
            postData.append("exclude_appointment_id", GlobalVariables.appointmentData.id);
        }

        var postUrl = GlobalVariables.baseUrl + '/index.php/appointments/ajax_register_appointment';
        var $layer = $('<div/>');

        $.ajax({
            url: postUrl,
            method: 'post',
            data: postData,
            //dataType: 'json',
            processData: false,
            contentType: false,
            beforeSend: function (jqxhr, settings) {
                $layer
                    .appendTo('body')
                    .css({
                        background: 'white',
                        position: 'fixed',
                        top: '0',
                        left: '0',
                        height: '100vh',
                        width: '100vw',
                        opacity: '0.5'
                    });
            }
        })
            .done(function (response) {
                if (!GeneralFunctions.handleAjaxExceptions(response)) {
                    $('.captcha-title small').trigger('click');
                    return false;
                }

                if (response.captcha_verification === false) {
                    $('#captcha-hint')
                        .text(EALang.captcha_is_wrong)
                        .fadeTo(400, 1);

                    setTimeout(function () {
                        $('#captcha-hint').fadeTo(400, 0);
                    }, 3000);

                    $('.captcha-title small').trigger('click');

                    $captchaText.closest('.form-group').addClass('has-error');

                    return false;
                }

                if( response.appointment_id )
                    window.location.href = GlobalVariables.baseUrl
                        + '/index.php/appointments/book_success/' + response.appointment_id;
                else
                    console.log(response);
            })
            .fail(function (jqxhr, textStatus, errorThrown) {
                $('.captcha-title small').trigger('click');
                GeneralFunctions.ajaxFailureHandler(jqxhr, textStatus, errorThrown);
            })
            .always(function () {
                $layer.remove();
            });
    };

    /**
     * Get the unavailable dates of a provider.
     *
     * This method will fetch the unavailable dates of the selected provider and service and then it will
     * select the first available date (if any). It uses the "FrontendBookApi.getAvailableHours" method to
     * fetch the appointment* hours of the selected date.
     *
     * @param {Number} providerId The selected provider ID.
     * @param {Number} serviceId The selected service ID.
     * @param {String} selectedDateString Y-m-d value of the selected date.
     */
    exports.getUnavailableDates = function (providerId, serviceId, selectedDateString) {
        if (processingUnavailabilities) {
            return;
        }

        var appointmentId = FrontendBook.manageMode ? GlobalVariables.appointmentData.id : undefined;

        var url = GlobalVariables.baseUrl + '/index.php/appointments/ajax_get_unavailable_dates';
        var data = {
            provider_id: providerId,
            service_id: serviceId,
            selected_date: encodeURIComponent(selectedDateString),
            csrfToken: GlobalVariables.csrfToken,
            manage_mode: FrontendBook.manageMode,
            appointment_id: appointmentId
        };

        $.ajax({
            url: url,
            type: 'GET',
            data: data,
            dataType: 'json'
        })
            .done(function (response) {
                unavailableDatesBackup = response;
                selectedDateStringBackup = selectedDateString;
                _applyUnavailableDates(response, selectedDateString, true);
            })
            .fail(GeneralFunctions.ajaxFailureHandler);
    };

    exports.applyPreviousUnavailableDates = function () {
        _applyUnavailableDates(unavailableDatesBackup, selectedDateStringBackup);
    };

    function _applyUnavailableDates(unavailableDates, selectedDateString, setDate) {
        setDate = setDate || false;

        processingUnavailabilities = true;

        // Select first enabled date.
        var selectedDate = Date.parse(selectedDateString);
        var numberOfDays = new Date(selectedDate.getFullYear(), selectedDate.getMonth() + 1, 0).getDate();

        if (setDate && !GlobalVariables.manageMode) {
            for (var i = 1; i <= numberOfDays; i++) {
                var currentDate = new Date(selectedDate.getFullYear(), selectedDate.getMonth(), i);
                if (unavailableDates.indexOf(currentDate.toString('yyyy-MM-dd')) === -1) {
                    $('#select-date').datepicker('setDate', currentDate);
                    FrontendBookApi.getAvailableHours(currentDate.toString('yyyy-MM-dd'));
                    break;
                }
            }
        }

        // If all the days are unavailable then hide the appointments hours.
        if (unavailableDates.length === numberOfDays) {
            $('#available-hours').text(EALang.no_available_hours);
        }

        // Grey out unavailable dates.
        $('#select-date .ui-datepicker-calendar td:not(.ui-datepicker-other-month)').each(function (index, td) {
            selectedDate.set({day: index + 1});
            if ($.inArray(selectedDate.toString('yyyy-MM-dd'), unavailableDates) != -1) {
                $(td).addClass('ui-datepicker-unselectable ui-state-disabled');
            }
        });

        processingUnavailabilities = false;
    }

    /**
     * Save the user's consent.
     *
     * @param {Object} consent Contains user's consents.
     */
    exports.saveConsent = function (consent) {
        var url = GlobalVariables.baseUrl + '/index.php/consents/ajax_save_consent';
        var data = {
            csrfToken: GlobalVariables.csrfToken,
            consent: consent
        };

        $.post(url, data, function (response) {
            if (!GeneralFunctions.handleAjaxExceptions(response)) {
                return;
            }
        }, 'json').fail(GeneralFunctions.ajaxFailureHandler);
    };

    /**
     * Delete personal information.
     *
     * @param {Number} customerToken Customer unique token.
     */
    exports.deletePersonalInformation = function (customerToken) {
        var url = GlobalVariables.baseUrl + '/index.php/privacy/ajax_delete_personal_information';
        var data = {
            csrfToken: GlobalVariables.csrfToken,
            customer_token: customerToken
        };

        $.post(url, data, function (response) {
            if (!GeneralFunctions.handleAjaxExceptions(response)) {
                return;
            }

            location.href = GlobalVariables.baseUrl;
        }, 'json').fail(GeneralFunctions.ajaxFailureHandler);
    };

})(window.FrontendBookApi);
