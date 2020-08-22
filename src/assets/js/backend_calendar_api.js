/* ----------------------------------------------------------------------------
 * Easy!Appointments - Open Source Web Scheduler
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) 2013 - 2018, Alex Tselegidis
 * @license     http://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        http://easyappointments.org
 * @since       v1.2.0
 * ---------------------------------------------------------------------------- */

/**
 * Backend Calendar API
 *
 * This module implements the AJAX requests for the calendar page.
 *
 * @module BackendCalendarApi
 */
window.BackendCalendarApi = window.BackendCalendarApi || {};

(function (exports) {

    'use strict';

    /**
     * Save Appointment
     *
     * This method stores the changes of an already registered appointment into the database, via an ajax call.
     *
     * @param {Object} appointment Contain the new appointment data. The ID of the appointment MUST be
     * already included. The rest values must follow the database structure.
     * @param {Object} customer Optional, contains the customer data.
     * @param {Function} successCallback Optional, if defined, this function is going to be executed on post success.
     * @param {Function} errorCallback Optional, if defined, this function is going to be executed on post failure.
     */
    exports.saveAppointment = function (appointment, customer, pet, successCallback, errorCallback) {
        var url = GlobalVariables.baseUrl + '/index.php/backend_api/ajax_save_appointment';
        var postData = new FormData();
        postData.append("csrfToken", GlobalVariables.csrfToken);
        postData.append("appointment_data", JSON.stringify(appointment));

        if (customer !== undefined) {
            postData.append("customer_data", JSON.stringify(customer));
        }

        if (pet !== undefined && pet != null) {
            if( pet["attachment"] ){
                var files_el = $('#' + pet["attachment"]).get(0);
                $.each(files_el.files,function(iFile,file){
                        postData.append(pet["attachment"] + '['+iFile+']', file);
                        });
                pet["attachment"] = files_el.files.length;
            }
            postData.append("pet_data", JSON.stringify(pet));
        }

        $.ajax({
            url: url,
            type: 'POST',
            data: postData,
            dataType: 'json',
            processData: false,
            contentType: false,
        })
            .done(function (response) {
                if (successCallback !== undefined) {
                    successCallback(response);
                }
            })
            .fail(function (jqXHR, textStatus, errorThrown) {
                if (errorCallback !== undefined) {
                    errorCallback();
                }
                else
                    console.log(textStatus + " " + errorThrown + " " + jqXHR.responseText);
            });
    };

    /**
     * Save unavailable period to database.
     *
     * @param {Object} unavailable Contains the unavailable period data.
     * @param {Function} successCallback The ajax success callback function.
     * @param {Function} errorCallback The ajax failure callback function.
     */
    exports.saveUnavailable = function (unavailable, successCallback, errorCallback) {
        var postUrl = GlobalVariables.baseUrl + '/index.php/backend_api/ajax_save_unavailable';
        var postData = {
            csrfToken: GlobalVariables.csrfToken,
            unavailable: JSON.stringify(unavailable)
        };

        $.ajax({
            type: 'POST',
            url: postUrl,
            data: postData,
            success: successCallback,
            error: errorCallback
        });
    };

    exports.getProviderService = function (provider_id) {
        var provider = GlobalVariables.availableProviders.find(function (provider) {
            return provider.id == provider_id;
        });

        var default_service = GlobalVariables.availableServices.find(function (service) {
            return provider.id == service.id_users_default_provider;
        });
        var first_service = GlobalVariables.availableServices.find(function (service) {
            return provider.services.indexOf(service.id) !== -1;
        });

        return {
            provider: provider ? provider.id : null, 
            default_service: default_service ? default_service.id : null,
            first_service: first_service ? first_service.id : null,
            any_service: default_service ? default_service.id : (first_service ? first_service.id : null),
        };
    }


})(window.BackendCalendarApi); 
