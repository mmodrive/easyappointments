<?php defined('BASEPATH') OR exit('No direct script access allowed');

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

use \EA\Engine\Types\Text;
use \EA\Engine\Types\Email;
use \EA\Engine\Types\Url;
use \EA\Engine\Types\NonEmptyText as NonEmptyText;

/**
 * Backend API Controller
 *
 * Contains all the backend AJAX callbacks.
 *
 * @package Controllers
 */
class Backend_api extends CI_Controller {
    /**
     * @var array
     */
    protected $privileges;

    /**
     * Class Constructor
     */
    public function __construct()
    {
        parent::__construct();

        // All the methods in this class must be accessible through a POST request.
        if (strtoupper($_SERVER['REQUEST_METHOD']) !== 'POST')
        {
            $this->security->csrf_show_error();
        }

        $this->load->library('session');
        $this->load->model('roles_model');

        if ($this->session->userdata('role_slug'))
        {
            $this->privileges = $this->roles_model->get_privileges($this->session->userdata('role_slug'));
        }

        // Set user's selected language.
        if ($this->session->userdata('language'))
        {
            $this->config->set_item('language', $this->session->userdata('language'));
            $this->lang->load('translations', $this->session->userdata('language'));
        }
        else
        {
            $this->lang->load('translations', $this->config->item('language')); // default
        }
    }

    /**
     * Get Calendar Events
     *
     * This method will return all the calendar events within a specified period.
     */
    public function ajax_get_calendar_events()
    {
        try
        {
            $this->output->set_content_type('application/json');
            $this->load->model('appointments_model');
            $this->load->model('customers_model');
            $this->load->model('services_model');
            $this->load->model('providers_model');

            $startDate = $this->input->post('startDate') . ' 00:00:00';
            $endDate = $this->input->post('endDate') . ' 23:59:59';

            $response = [
                'appointments' => $this->appointments_model->get_batch([
                    'is_unavailable' => FALSE,
                    'start_datetime >=' => $startDate,
                    'end_datetime <=' => $endDate
                ]),
                'unavailabilities' => $this->appointments_model->get_batch([
                    'is_unavailable' => TRUE,
                    'start_datetime >=' => $startDate,
                    'end_datetime <=' => $endDate
                ])
            ];

            foreach ($response['appointments'] as &$appointment)
            {
                $appointment['provider'] = $this->providers_model->get_row($appointment['id_users_provider']);
                $appointment['service'] = $this->services_model->get_row($appointment['id_services']);
                $appointment['customer'] = $this->customers_model->get_row($appointment['id_users_customer']);
            }

            $userId = $this->session->userdata('user_id');
            $roleSlug = $this->session->userdata('role_slug');

            // If the current user is a provider he must only see his own appointments. 
            if ($roleSlug === DB_SLUG_PROVIDER)
            {
                foreach ($response['appointments'] as $index => $appointment)
                {
                    if ((int)$appointment['id_users_provider'] !== (int)$userId)
                    {
                        unset($response['appointments'][$index]);
                    }
                }

                foreach ($response['unavailabilities'] as $index => $unavailability)
                {
                    if ((int)$unavailability['id_users_provider'] !== (int)$userId)
                    {
                        unset($response['unavailabilities'][$index]);
                    }
                }
            }

            // If the current user is a secretary he must only see the appointments of his providers.
            if ($roleSlug === DB_SLUG_SECRETARY)
            {
                $this->load->model('secretaries_model');
                $providers = $this->secretaries_model->get_row($userId)['providers'];
                foreach ($response['appointments'] as $index => $appointment)
                {
                    if ( ! in_array((int)$appointment['id_users_provider'], $providers))
                    {
                        unset($response['appointments'][$index]);
                    }
                }

                foreach ($response['unavailabilities'] as $index => $unavailability)
                {
                    if ( ! in_array((int)$unavailability['id_users_provider'], $providers))
                    {
                        unset($response['unavailabilities'][$index]);
                    }
                }
            }

            $this->output->set_output(json_encode($response));
        }
        catch (Exception $exc)
        {
            $this->output->set_output(json_encode([
                'exceptions' => [exceptionToJavaScript($exc)]
            ]));
        }
    }

    /**
     * [AJAX] Get the registered appointments for the given date period and record.
     *
     * This method returns the database appointments and unavailable periods for the
     * user selected date period and record type (provider or service).
     *
     * Required POST Parameters:
     *
     * - int $_POST['record_id'] Selected record id.
     * - string $_POST['filter_type'] Could be either FILTER_TYPE_PROVIDER or FILTER_TYPE_SERVICE.
     * - string $_POST['start_date'] The user selected start date.
     * - string $_POST['end_date'] The user selected end date.
     */
    public function ajax_get_calendar_appointments()
    {
        try
        {
            if ($this->privileges[PRIV_APPOINTMENTS]['view'] == FALSE)
            {
                throw new Exception('You do not have the required privileges for this task.');
            }

            if ( ! $this->input->post('filter_type'))
            {
                $this->output
                    ->set_content_type('application/json')
                    ->set_output(json_encode(['appointments' => []]));
                return;
            }

            $this->load->model('appointments_model');
            $this->load->model('providers_model');
            $this->load->model('services_model');
            $this->load->model('customers_model');
            $this->load->model('pets_model');

            if ($this->input->post('filter_type') == FILTER_TYPE_PROVIDER)
            {
                $where_id = 'id_users_provider';
            }
            else
            {
                $where_id = 'id_services';
            }

            // Get appointments
            $record_id = $this->db->escape($_POST['record_id']);
            $start_date = $this->db->escape($_POST['start_date']);
            $end_date = $this->db->escape(date('Y-m-d', strtotime($_POST['end_date'] . ' +1 day')));

            $where_clause = $where_id . ' = ' . $record_id . '
                AND ((start_datetime > ' . $start_date . ' AND start_datetime < ' . $end_date . ') 
                or (end_datetime > ' . $start_date . ' AND end_datetime < ' . $end_date . ') 
                or (start_datetime <= ' . $start_date . ' AND end_datetime >= ' . $end_date . ')) 
                AND is_unavailable = 0
            ';

            $response['appointments'] = $this->appointments_model->get_batch($where_clause);

            foreach ($response['appointments'] as &$appointment)
            {
                $appointment['provider'] = $this->providers_model->get_row($appointment['id_users_provider']);
                $appointment['service'] = $this->services_model->get_row($appointment['id_services']);
                $appointment['customer'] = $this->customers_model->get_row($appointment['id_users_customer'], TRUE);
                if( isset($appointment['id_pets']) )
                    $appointment['pet'] = $this->pets_model->get_row($appointment['id_pets']);
            }

            // Get unavailable periods (only for provider).
            if ($this->input->post('filter_type') == FILTER_TYPE_PROVIDER)
            {
                $where_clause = $where_id . ' = ' . $record_id . '
                    AND ((start_datetime > ' . $start_date . ' AND start_datetime < ' . $end_date . ') 
                    or (end_datetime > ' . $start_date . ' AND end_datetime < ' . $end_date . ') 
                    or (start_datetime <= ' . $start_date . ' AND end_datetime >= ' . $end_date . ')) 
                    AND is_unavailable = 1
                ';

                $response['unavailables'] = $this->appointments_model->get_batch($where_clause);
            }

            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode($response));
        }
        catch (Exception $exc)
        {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(['exceptions' => [exceptionToJavaScript($exc)]]));
        }
    }

    /**
     * [AJAX] Save appointment changes that are made from the backend calendar page.
     *
     * Required POST Parameters:
     *
     * - array $_POST['appointment_data'] (OPTIONAL) Array with the appointment data.
     * - array $_POST['customer_data'] (OPTIONAL) Array with the customer data.
     */
    public function ajax_save_appointment()
    {
        try
        {
            $this->load->model('appointments_model');
            $this->load->model('providers_model');
            $this->load->model('services_model');
            $this->load->model('customers_model');
            $this->load->model('pets_model');
            $this->load->model('settings_model');
            $is_existing_customer = TRUE;
            $notify_fields_changed = FALSE;

            // :: SAVE CUSTOMER CHANGES TO DATABASE
            if ($this->input->post('customer_data'))
            {
                $customer = json_decode($this->input->post('customer_data'), TRUE);

                $REQUIRED_PRIV = ( ! isset($customer['id']))
                    ? $this->privileges[PRIV_CUSTOMERS]['add']
                    : $this->privileges[PRIV_CUSTOMERS]['edit'];
                if ($REQUIRED_PRIV == FALSE)
                {
                    throw new Exception('You do not have the required privileges for this task.');
                }

                $is_existing_customer = $this->customers_model->exists($customer);
                if ( !$is_existing_customer || count(array_intersect( ["email"], $this->customers_model->get_changing_fields($customer))) > 0 )
                    $notify_fields_changed = TRUE;
                $customer['id'] = $this->customers_model->add($customer);
            }

            // :: SAVE PET CHANGES TO DATABASE
            $pet = null;
            if ($this->input->post('pet_data'))
            {
                $pet = json_decode($this->input->post('pet_data'), TRUE);

                $REQUIRED_PRIV = ( ! isset($customer['id']))
                    ? $this->privileges[PRIV_CUSTOMERS]['add']
                    : $this->privileges[PRIV_CUSTOMERS]['edit'];
                if ($REQUIRED_PRIV == FALSE)
                {
                    throw new Exception('You do not have the required privileges for this task.');
                }

                if(!isset($pet['id_users']))
                    $pet['id_users'] = $customer['id'];

                $pet['id'] = $this->pets_model->add($pet);
            }

            // :: SAVE APPOINTMENT CHANGES TO DATABASE
            if ($this->input->post('appointment_data'))
            {
                $appointment = json_decode($this->input->post('appointment_data', FALSE), TRUE);

                $REQUIRED_PRIV = ( ! isset($appointment['id']))
                    ? $this->privileges[PRIV_APPOINTMENTS]['add']
                    : $this->privileges[PRIV_APPOINTMENTS]['edit'];
                if ($REQUIRED_PRIV == FALSE)
                {
                    throw new Exception('You do not have the required privileges for this task.');
                }

                $manage_mode = isset($appointment['id']);
                // If the appointment does not contain the customer record id, then it
                // means that is is going to be inserted. Get the customer's record id.
                if ( ! isset($appointment['id_users_customer']))
                {
                    $appointment['id_users_customer'] = $customer['id'];
                }
                if ( ! isset($appointment['id_pets']) && isset($pet))
                {
                    $appointment['id_pets'] = $pet['id'];
                }

                $is_existing_appointment = $this->appointments_model->exists($appointment);
                if ( !$is_existing_appointment || count(array_intersect( ["start_datetime", "end_datetime", "id_services", "id_users_provider"], $this->appointments_model->get_changing_fields($appointment))) > 0 )
                    $notify_fields_changed = TRUE;

                $appointment['id'] = $this->appointments_model->add($appointment);
            }

            $appointment = $this->appointments_model->get_row($appointment['id']);
            $provider = $this->providers_model->get_row($appointment['id_users_provider']);
            $customer = $this->customers_model->get_row($appointment['id_users_customer']);
            if(isset($appointment['id_pets']))
                $pet = $this->pets_model->get_row($appointment['id_pets']);
            $service = $this->services_model->get_row($appointment['id_services']);

            $company_settings = [
                'company_name' => $this->settings_model->get_setting('company_name'),
                'company_link' => $this->settings_model->get_setting('company_link'),
                'company_email' => $this->settings_model->get_setting('company_email'),
                'date_format' => $this->settings_model->get_setting('date_format'),
                'time_format' => $this->settings_model->get_setting('time_format')
            ];

            // :: SYNC APPOINTMENT CHANGES WITH GOOGLE CALENDAR
            try
            {
                $google_sync = $this->providers_model->get_setting('google_sync',
                    $appointment['id_users_provider']);

                if ($google_sync == TRUE)
                {
                    $google_token = json_decode($this->providers_model->get_setting('google_token',
                        $appointment['id_users_provider']));

                    $this->load->library('Google_sync');
                    $this->google_sync->refresh_token($google_token->refresh_token);

                    if ($appointment['id_google_calendar'] == NULL)
                    {
                        $google_event = $this->google_sync->add_appointment($appointment, $provider,
                            $service, $customer, $company_settings);
                        $appointment['id_google_calendar'] = $google_event->id;
                        $this->appointments_model->add($appointment); // Store google calendar id.
                    }
                    else
                    {
                        $this->google_sync->update_appointment($appointment, $provider,
                            $service, $customer, $company_settings);
                    }
                }
            }
            catch (Exception $exc)
            {
                $warnings[] = exceptionToJavaScript($exc);
            }

            // :: SEND EMAIL NOTIFICATIONS TO PROVIDER AND CUSTOMER
            try
            {
                $this->config->load('email');
                $this->load->model('settings_model');

                $email = new \EA\Engine\Notifications\Email($this, $this->config->config);

                $send_provider = $this->providers_model
                    ->get_setting('notifications', $provider['id']);

                if ( ! $manage_mode)
                    $notification_type = 'email_appointment_new';
                else
                    $notification_type = 'email_appointment_change';

                $send_customer = $this->settings_model->get_setting('customer_notifications');

                $this->load->library('ics_file');
                $ics_stream = $this->ics_file->get_stream($appointment, $service, $provider, $customer);

                // TEMPORARY SERVICE FILTER
                if ($service['id'] == 7) {
                    if ((bool)$send_customer === TRUE && $notify_fields_changed) {
                        $notification = $this->settings_model->getNotification(
                            $notification_type,
                            $appointment,
                            $provider,
                            $service,
                            $customer,
                            $pet,
                            TRUE
                        );
                        $email->sendEmail($notification, new Email($customer['email']), new Text($ics_stream));
                    } {
                        if (!$is_existing_customer) {
                            $notification = $this->settings_model->getNotification(
                                'email_customer_registration',
                                $appointment,
                                $provider,
                                $service,
                                $customer,
                                $pet,
                                TRUE
                            );
                            $email->sendEmail($notification, new Email($customer['email']));
                        }

                        $notification = $this->settings_model->getNotification(
                            $notification_type,
                            $appointment,
                            $provider,
                            $service,
                            $customer,
                            $pet,
                            TRUE
                        );
                        $email->sendEmail($notification, new Email($customer['email']), new Text($ics_stream));
                    }

                    if ($send_provider == TRUE && $notify_fields_changed) {
                        $notification = $this->settings_model->getNotification(
                            $notification_type,
                            $appointment,
                            $provider,
                            $service,
                            $customer,
                            $pet,
                            FALSE
                        );
                        $email->sendEmail($notification, new Email($provider['email']), new Text($ics_stream));
                    }

                    // Notify all staff of new customer registration
                    if (!$is_existing_customer) {
                        $notification = $this->settings_model->getNotification(
                            'email_customer_registration',
                            $appointment,
                            $provider,
                            $service,
                            $customer,
                            $pet,
                            TRUE
                        );

                        $addresses = $this->settings_model->get_new_customer_notification_emails();

                        foreach ($addresses as $key => $value) {
                            $email->sendEmail($notification, new Email($value['email']));
                        }
                    }
                }
            }
            catch (Exception $exc)
            {
                $warnings[] = exceptionToJavaScript($exc);
            }

            if ( ! isset($warnings))
            {
                $this->output
                    ->set_content_type('application/json')
                    ->set_output(json_encode(AJAX_SUCCESS));
            }
            else
            {
                $this->output
                    ->set_content_type('application/json')
                    ->set_output(json_encode(['warnings' => $warnings]));
            }
        }
        catch (Exception $exc)
        {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(['exceptions' => [exceptionToJavaScript($exc)]]));
        }
    }

    /**
     * [AJAX] Delete appointment from the database.
     *
     * This method deletes an existing appointment from the database. Once this action is finished it cannot be undone.
     * Notification emails are send to both provider and customer and the delete action is executed to the Google
     * Calendar account of the provider, if the "google_sync" setting is enabled.
     *
     * Required POST Parameters:
     *
     * - int $_POST['appointment_id'] The appointment id to be deleted.
     */
    public function ajax_delete_appointment()
    {
        try
        {
            if ($this->privileges[PRIV_APPOINTMENTS]['delete'] == FALSE)
            {
                throw new Exception('You do not have the required privileges for this task.');
            }

            if ( ! $this->input->post('appointment_id'))
            {
                throw new Exception('No appointment id provided.');
            }

            // :: STORE APPOINTMENT DATA FOR LATER USE IN THIS METHOD
            $this->load->model('appointments_model');
            $this->load->model('providers_model');
            $this->load->model('customers_model');
            $this->load->model('services_model');
            $this->load->model('settings_model');

            $appointment = $this->appointments_model->get_row($this->input->post('appointment_id'));
            $provider = $this->providers_model->get_row($appointment['id_users_provider']);
            $customer = $this->customers_model->get_row($appointment['id_users_customer']);
            $service = $this->services_model->get_row($appointment['id_services']);

            $company_settings = [
                'company_name' => $this->settings_model->get_setting('company_name'),
                'company_email' => $this->settings_model->get_setting('company_email'),
                'company_link' => $this->settings_model->get_setting('company_link'),
                'date_format' => $this->settings_model->get_setting('date_format'),
                'time_format' => $this->settings_model->get_setting('time_format')
            ];

            // :: DELETE APPOINTMENT RECORD FROM DATABASE
            $this->appointments_model->delete($this->input->post('appointment_id'));

            // :: SYNC DELETE WITH GOOGLE CALENDAR
            if ($appointment['id_google_calendar'] != NULL)
            {
                try
                {
                    $google_sync = $this->providers_model->get_setting('google_sync', $provider['id']);

                    if ($google_sync == TRUE)
                    {
                        $google_token = json_decode($this->providers_model
                            ->get_setting('google_token', $provider['id']));
                        $this->load->library('Google_sync');
                        $this->google_sync->refresh_token($google_token->refresh_token);
                        $this->google_sync->delete_appointment($provider, $appointment['id_google_calendar']);
                    }
                }
                catch (Exception $exc)
                {
                    $warnings[] = exceptionToJavaScript($exc);
                }
            }

            // :: SEND NOTIFICATION EMAILS TO PROVIDER AND CUSTOMER
            try
            {
                $this->config->load('email');
                $email = new \EA\Engine\Notifications\Email($this, $this->config->config);

                $send_provider = $this->providers_model
                    ->get_setting('notifications', $provider['id']);

                if ((bool)$send_provider === TRUE)
                {
                    $email->sendDeleteAppointment($appointment, $provider,
                        $service, $customer, $company_settings, new Email($provider['email']),
                        new Text($this->input->post('delete_reason')));
                }

                $send_customer = $this->settings_model->get_setting('customer_notifications');
                // TEMPORARY SERVICE FILTER
                if ($service['id'] == 7) {
                    if ((bool)$send_customer === TRUE) {
                        $email->sendDeleteAppointment(
                            $appointment,
                            $provider,
                            $service,
                            $customer,
                            $company_settings,
                            new Email($customer['email']),
                            new Text($this->input->post('delete_reason'))
                        );
                    }
                }
            }
            catch (Exception $exc)
            {
                $warnings[] = exceptionToJavaScript($exc);
            }

            // :: SEND RESPONSE TO CLIENT BROWSER
            if ( ! isset($warnings))
            {
                $this->output
                    ->set_content_type('application/json')
                    ->set_output(json_encode(AJAX_SUCCESS));
            }
            else
            {
                $this->output
                    ->set_content_type('application/json')
                    ->set_output(json_encode(['warnings' => $warnings]));
            }
        }
        catch (Exception $exc)
        {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(['exceptions' => [exceptionToJavaScript($exc)]]));
        }
    }

    /**
     * [AJAX] Disable a providers sync setting.
     *
     * This method deletes the "google_sync" and "google_token" settings from the database. After that the provider's
     * appointments will be no longer synced with google calendar.
     *
     * Required POST Parameters:
     *
     * - string $_POST['provider_id'] The selected provider record id.
     */
    public function ajax_disable_provider_sync()
    {
        try
        {
            if ( ! $this->input->post('provider_id'))
            {
                throw new Exception('Provider id not specified.');
            }

            if ($this->privileges[PRIV_USERS]['edit'] == FALSE
                && $this->session->userdata('user_id') != $this->input->post('provider_id'))
            {
                throw new Exception('You do not have the required privileges for this task.');
            }

            $this->load->model('providers_model');
            $this->load->model('appointments_model');
            $this->providers_model->set_setting('google_sync', FALSE, $this->input->post('provider_id'));
            $this->providers_model->set_setting('google_token', NULL, $this->input->post('provider_id'));
            $this->appointments_model->clear_google_sync_ids($this->input->post('provider_id'));

            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(AJAX_SUCCESS));
        }
        catch (Exception $exc)
        {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(['exceptions' => [exceptionToJavaScript($exc)]]));
        }
    }

    /**
     * [AJAX] Filter the customer records with the given key string.
     *
     * Required POST Parameters:
     *
     * - string $_POST['key'] The filter key string.
     *
     * Outputs the search results.
     */
    public function ajax_filter_customers()
    {
        try
        {
            if ($this->privileges[PRIV_CUSTOMERS]['view'] == FALSE)
            {
                throw new Exception('You do not have the required privileges for this task.');
            }

            $this->load->model('appointments_model');
            $this->load->model('services_model');
            $this->load->model('providers_model');
            $this->load->model('customers_model');

            $key = $this->db->escape_str($this->input->post('key'));

            $where_clause =
                '(first_name LIKE "%' . $key . '%" OR ' .
                'last_name  LIKE "%' . $key . '%" OR ' .
                'email LIKE "%' . $key . '%" OR ' .
                'phone_number LIKE "%' . $key . '%" OR ' .
                'address LIKE "%' . $key . '%" OR ' .
                'city LIKE "%' . $key . '%" OR ' .
                'zip_code LIKE "%' . $key . '%" OR ' .
                'notes LIKE "%' . $key . '%")';

            $customers = $this->customers_model->get_batch($where_clause);

            foreach ($customers as &$customer)
            {
                $appointments = $this->appointments_model
                    ->get_batch(['id_users_customer' => $customer['id']]);

                foreach ($appointments as &$appointment)
                {
                    $appointment['service'] = $this->services_model->get_row($appointment['id_services']);
                    $appointment['provider'] = $this->providers_model->get_row($appointment['id_users_provider']);
                }

                $customer['appointments'] = $appointments;
            }

            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode($customers));
        }
        catch (Exception $exc)
        {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(['exceptions' => [exceptionToJavaScript($exc)]]));
        }
    }

    /**
     * [AJAX] Insert of update unavailable time period to database.
     *
     * Required POST Parameters:
     *
     * - array $_POST['unavailable'] JSON encoded array that contains the unavailable period data.
     */
    public function ajax_save_unavailable()
    {
        try
        {
            // Check privileges
            $unavailable = json_decode($this->input->post('unavailable'), TRUE);

            $REQUIRED_PRIV = ( ! isset($unavailable['id']))
                ? $this->privileges[PRIV_APPOINTMENTS]['add']
                : $this->privileges[PRIV_APPOINTMENTS]['edit'];
            if ($REQUIRED_PRIV == FALSE)
            {
                throw new Exception('You do not have the required privileges for this task.');
            }

            $this->load->model('appointments_model');
            $this->load->model('providers_model');

            $provider = $this->providers_model->get_row($unavailable['id_users_provider']);

            // Add appointment
            $unavailable['id'] = $this->appointments_model->add_unavailable($unavailable);
            $unavailable = $this->appointments_model->get_row($unavailable['id']); // fetch all inserted data

            // Google Sync
            try
            {
                $google_sync = $this->providers_model->get_setting('google_sync',
                    $unavailable['id_users_provider']);

                if ($google_sync)
                {
                    $google_token = json_decode($this->providers_model->get_setting('google_token',
                        $unavailable['id_users_provider']));

                    $this->load->library('google_sync');
                    $this->google_sync->refresh_token($google_token->refresh_token);

                    if ($unavailable['id_google_calendar'] == NULL)
                    {
                        $google_event = $this->google_sync->add_unavailable($provider, $unavailable);
                        $unavailable['id_google_calendar'] = $google_event->id;
                        $this->appointments_model->add_unavailable($unavailable);
                    }
                    else
                    {
                        $google_event = $this->google_sync->update_unavailable($provider, $unavailable);
                    }
                }
            }
            catch (Exception $exc)
            {
                $warnings[] = $exc;
            }

            if (isset($warnings))
            {
                $this->output
                    ->set_content_type('application/json')
                    ->set_output(json_encode(['warnings' => $warnings]));
            }
            else
            {
                $this->output
                    ->set_content_type('application/json')
                    ->set_output(json_encode(AJAX_SUCCESS));
            }

        }
        catch (Exception $exc)
        {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(['exceptions' => [exceptionToJavaScript($exc)]]));
        }
    }

    /**
     * [AJAX] Delete an unavailable time period from database.
     *
     * Required POST Parameters:
     *
     * - int $_POST['unavailable_id'] Record id to be deleted.
     */
    public function ajax_delete_unavailable()
    {
        try
        {
            if ($this->privileges[PRIV_APPOINTMENTS]['delete'] == FALSE)
            {
                throw new Exception('You do not have the required privileges for this task.');
            }

            $this->load->model('appointments_model');
            $this->load->model('providers_model');

            $unavailable = $this->appointments_model->get_row($this->input->post('unavailable_id'));
            $provider = $this->providers_model->get_row($unavailable['id_users_provider']);

            // Delete unavailable
            $this->appointments_model->delete_unavailable($unavailable['id']);

            // Google Sync
            try
            {
                $google_sync = $this->providers_model->get_setting('google_sync', $provider['id']);
                if ($google_sync == TRUE)
                {
                    $google_token = json_decode($this->providers_model->get_setting('google_token', $provider['id']));
                    $this->load->library('google_sync');
                    $this->google_sync->refresh_token($google_token->refresh_token);
                    $this->google_sync->delete_unavailable($provider, $unavailable['id_google_calendar']);
                }
            }
            catch (Exception $exc)
            {
                $warnings[] = $exc;
            }

            if (isset($warnings))
            {
                $this->output
                    ->set_content_type('application/json')
                    ->set_output(json_encode(['warnings' => $warnings]));
            }
            else
            {
                $this->output
                    ->set_content_type('application/json')
                    ->set_output(json_encode(AJAX_SUCCESS));
            }

        }
        catch (Exception $exc)
        {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(['exceptions' => [exceptionToJavaScript($exc)]]));
        }
    }

    /**
     * [AJAX] Save (insert or update) a customer record.
     *
     * Require POST Parameters:
     *
     * - array $_POST['customer'] JSON encoded array that contains the customer's data.
     */
    public function ajax_save_customer()
    {
        try
        {
            $this->load->model('customers_model');
            $customer = json_decode($this->input->post('customer'), TRUE);

            $REQUIRED_PRIV = ( ! isset($customer['id']))
                ? $this->privileges[PRIV_CUSTOMERS]['add']
                : $this->privileges[PRIV_CUSTOMERS]['edit'];
            if ($REQUIRED_PRIV == FALSE)
            {
                throw new Exception('You do not have the required privileges for this task.');
            }

            $customer_id = $this->customers_model->add($customer);
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'status' => AJAX_SUCCESS,
                    'id' => $customer_id
                ]));
        }
        catch (Exception $exc)
        {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(['exceptions' => [exceptionToJavaScript($exc)]]));
        }
    }

    /**
     * [AJAX] Delete customer from database.
     *
     * Required POST Parameters:
     *
     * - int $_POST['customer_id'] Customer record id to be deleted.
     */
    public function ajax_delete_customer()
    {
        try
        {
            if ($this->privileges[PRIV_CUSTOMERS]['delete'] == FALSE)
            {
                throw new Exception('You do not have the required privileges for this task.');
            }

            $this->load->model('customers_model');
            $this->customers_model->delete($this->input->post('customer_id'));
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(AJAX_SUCCESS));
        }
        catch (Exception $exc)
        {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(['exceptions' => [exceptionToJavaScript($exc)]]));
        }
    }

    public function ajax_merge_customer()
    {
        try
        {
            if ($this->privileges[PRIV_CUSTOMERS]['edit'] == FALSE)
            {
                throw new Exception('You do not have the required privileges for this task.');
            }

            $this->load->model('customers_model');
            $this->customers_model->merge(
                $this->input->post('from_id'),
                $this->input->post('to_id')
            );
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(AJAX_SUCCESS));
        }
        catch (Exception $exc)
        {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(['exceptions' => [exceptionToJavaScript($exc)]]));
        }
    }

    /**
     * [AJAX] Save (insert or update) service record.
     *
     * Required POST Parameters:
     *
     * - array $_POST['service'] Contains the service data (json encoded).
     */
    public function ajax_save_service()
    {
        try
        {
            $this->load->model('services_model');
            $service = json_decode($this->input->post('service', FALSE), TRUE);

            $REQUIRED_PRIV = ( ! isset($service['id']))
                ? $this->privileges[PRIV_SERVICES]['add']
                : $this->privileges[PRIV_SERVICES]['edit'];
            if ($REQUIRED_PRIV == FALSE)
            {
                throw new Exception('You do not have the required privileges for this task.');
            }

            $service_id = $this->services_model->add($service);
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'status' => AJAX_SUCCESS,
                    'id' => $service_id
                ]));
        }
        catch (Exception $exc)
        {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(['exceptions' => [exceptionToJavaScript($exc)]]));
        }
    }

    /**
     * [AJAX] Delete service record from database.
     *
     * Required POST Parameters:
     *
     * - int $_POST['service_id'] Record id to be deleted.
     */
    public function ajax_delete_service()
    {
        try
        {
            if ($this->privileges[PRIV_SERVICES]['delete'] == FALSE)
            {
                throw new Exception('You do not have the required privileges for this task.');
            }

            $this->load->model('services_model');
            $result = $this->services_model->delete($this->input->post('service_id'));

            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode($result ? AJAX_SUCCESS : AJAX_FAILURE));
        }
        catch (Exception $exc)
        {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(['exceptions' => [exceptionToJavaScript($exc)]]));
        }
    }

    /**
     * [AJAX] Filter service records by given key string.
     *
     * Required POST Parameters:
     *
     * - string $_POST['key'] Key string used to filter the records.
     *
     * Outputs a JSON encoded array back to client.
     */
    public function ajax_filter_services()
    {
        try
        {
            if ($this->privileges[PRIV_SERVICES]['view'] == FALSE)
            {
                throw new Exception('You do not have the required privileges for this task.');
            }

            $this->load->model('services_model');
            $key = $this->db->escape_str($this->input->post('key'));
            $where =
                '(name LIKE "%' . $key . '%" OR duration LIKE "%' . $key . '%" OR ' .
                'price LIKE "%' . $key . '%" OR currency LIKE "%' . $key . '%" OR ' .
                'description LIKE "%' . $key . '%")';
            $services = $this->services_model->get_batch($where);
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode($services));
        }
        catch (Exception $exc)
        {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(['exceptions' => [exceptionToJavaScript($exc)]]));
        }
    }

    /**
     * [AJAX] Save (insert or update) category record.
     *
     * Required POST Parameters:
     *
     * - array $_POST['category'] Json encoded array with the category data. If an ID value is provided then the
     * category is going to be updated instead of inserted.
     */
    public function ajax_save_service_category()
    {
        try
        {
            $this->load->model('services_model');
            $category = json_decode($this->input->post('category'), TRUE);

            $REQUIRED_PRIV = ( ! isset($category['id']))
                ? $this->privileges[PRIV_SERVICES]['add']
                : $this->privileges[PRIV_SERVICES]['edit'];
            if ($REQUIRED_PRIV == FALSE)
            {
                throw new Exception('You do not have the required privileges for this task.');
            }

            $category_id = $this->services_model->add_category($category);

            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'status' => AJAX_SUCCESS,
                    'id' => $category_id
                ]));
        }
        catch (Exception $exc)
        {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(['exceptions' => [exceptionToJavaScript($exc)]]));
        }
    }

    /**
     * [AJAX] Delete category record from database.
     *
     * - int $_POST['category_id'] Record id to be deleted.
     */
    public function ajax_delete_service_category()
    {
        try
        {
            if ($this->privileges[PRIV_SERVICES]['delete'] == FALSE)
            {
                throw new Exception('You do not have the required privileges for this task.');
            }

            $this->load->model('services_model');
            $result = $this->services_model->delete_category($this->input->post('category_id'));

            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode($result ? AJAX_SUCCESS : AJAX_FAILURE));
        }
        catch (Exception $exc)
        {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(['exceptions' => [exceptionToJavaScript($exc)]]));
        }
    }

    /**
     * [AJAX] Filter services categories with key string.
     *
     * Required POST Parameters:
     *
     * - string $_POST['key'] The key string used to filter the records.
     *
     * Outputs a JSON encoded array back to client with the category records.
     */
    public function ajax_filter_service_categories()
    {
        try
        {
            if ($this->privileges[PRIV_SERVICES]['view'] == FALSE)
            {
                throw new Exception('You do not have the required privileges for this task.');
            }

            $this->load->model('services_model');
            $key = $this->db->escape_str($this->input->post('key'));
            $where = '(name LIKE "%' . $key . '%" OR description LIKE "%' . $key . '%")';
            $categories = $this->services_model->get_all_categories($where);
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode($categories));
        }
        catch (Exception $exc)
        {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(['exceptions' => [exceptionToJavaScript($exc)]]));
        }
    }

    /**
     * [AJAX] Filter admin records with string key.
     *
     * Required POST Parameters:
     *
     * - string $_POST['key'] The key string used to filter the records.
     *
     * Outputs a JSON encoded array back to client with the admin records.
     */
    public function ajax_filter_admins()
    {
        try
        {
            if ($this->privileges[PRIV_USERS]['view'] == FALSE)
            {
                throw new Exception('You do not have the required privileges for this task.');
            }

            $this->load->model('admins_model');
            $key = $this->db->escape_str($this->input->post('key'));
            $where =
                '(first_name LIKE "%' . $key . '%" OR last_name LIKE "%' . $key . '%" ' .
                'OR email LIKE "%' . $key . '%" OR mobile_number LIKE "%' . $key . '%" ' .
                'OR phone_number LIKE "%' . $key . '%" OR address LIKE "%' . $key . '%" ' .
                'OR city LIKE "%' . $key . '%" OR state LIKE "%' . $key . '%" ' .
                'OR zip_code LIKE "%' . $key . '%" OR notes LIKE "%' . $key . '%")';
            $admins = $this->admins_model->get_batch($where);
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode($admins));
        }
        catch (Exception $exc)
        {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(['exceptions' => [exceptionToJavaScript($exc)]]));
        }
    }

    /**
     * [AJAX] Save (insert or update) admin record into database.
     *
     * Required POST Parameters:
     *
     * - array $_POST['admin'] A json encoded array that contains the admin data. If an 'id'
     * value is provided then the record is going to be updated.
     *
     * Outputs an array with the operation status and the record id that was saved into the database.
     */
    public function ajax_save_admin()
    {
        try
        {
            $this->load->model('admins_model');
            $admin = json_decode($this->input->post('admin'), TRUE);

            $REQUIRED_PRIV = ( ! isset($admin['id']))
                ? $this->privileges[PRIV_USERS]['add']
                : $this->privileges[PRIV_USERS]['edit'];
            if ($REQUIRED_PRIV == FALSE)
            {
                throw new Exception('You do not have the required privileges for this task.');
            }

            $admin_id = $this->admins_model->add($admin);

            $response = [
                'status' => AJAX_SUCCESS,
                'id' => $admin_id
            ];

            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode($response));
        }
        catch (Exception $exc)
        {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(['exceptions' => [exceptionToJavaScript($exc)]]));
        }
    }

    /**
     * [AJAX] Delete an admin record from the database.
     *
     * Required POST Parameters:
     *
     * - int $_POST['admin_id'] The id of the record to be deleted.
     *
     * Outputs the operation result constant (AJAX_SUCCESS or AJAX_FAILURE).
     */
    public function ajax_delete_admin()
    {
        try
        {
            if ($this->privileges[PRIV_USERS]['delete'] == FALSE)
            {
                throw new Exception('You do not have the required privileges for this task.');
            }

            $this->load->model('admins_model');
            $result = $this->admins_model->delete($this->input->post('admin_id'));
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode($result ? AJAX_SUCCESS : AJAX_FAILURE));
        }
        catch (Exception $exc)
        {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(['exceptions' => [exceptionToJavaScript($exc)]]));
        }
    }

    /**
     * [AJAX] Filter provider records with string key.
     *
     * Required POST Parameters:
     *
     * - string $_POST['key'] The key string used to filter the records.
     *
     * Outputs a JSON encoded array back to client with the provider records.
     */
    public function ajax_filter_providers()
    {
        try
        {
            if ($this->privileges[PRIV_USERS]['view'] == FALSE)
            {
                throw new Exception('You do not have the required privileges for this task.');
            }

            $this->load->model('providers_model');
            $key = $this->db->escape_str($this->input->post('key'));
            $where =
                '(first_name LIKE "%' . $key . '%" OR last_name LIKE "%' . $key . '%" ' .
                'OR email LIKE "%' . $key . '%" OR mobile_number LIKE "%' . $key . '%" ' .
                'OR phone_number LIKE "%' . $key . '%" OR address LIKE "%' . $key . '%" ' .
                'OR city LIKE "%' . $key . '%" OR state LIKE "%' . $key . '%" ' .
                'OR zip_code LIKE "%' . $key . '%" OR notes LIKE "%' . $key . '%")';
            $providers = $this->providers_model->get_batch($where);
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode($providers));
        }
        catch (Exception $exc)
        {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(['exceptions' => [exceptionToJavaScript($exc)]]));
        }
    }

    /**
     * [AJAX] Save (insert or update) a provider record into database.
     *
     * Required POST Parameters:
     *
     * - array $_POST['provider'] A json encoded array that contains the provider data. If an 'id'
     * value is provided then the record is going to be updated.
     *
     * Outputs the success constant 'AJAX_SUCCESS' so javascript knows that everything completed successfully.
     */
    public function ajax_save_provider()
    {
        try
        {
            $this->load->model('providers_model');
            $provider = json_decode($this->input->post('provider'), TRUE);

            $REQUIRED_PRIV = ( ! isset($provider['id']))
                ? $this->privileges[PRIV_USERS]['add']
                : $this->privileges[PRIV_USERS]['edit'];
            if ($REQUIRED_PRIV == FALSE)
            {
                throw new Exception('You do not have the required privileges for this task.');
            }

            if ( ! isset($provider['settings']['working_plan']))
            {
                $this->load->model('settings_model');
                $provider['settings']['working_plan'] = $this->settings_model
                    ->get_setting('company_working_plan');
            }

            $provider_id = $this->providers_model->add($provider);


            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'status' => AJAX_SUCCESS,
                    'id' => $provider_id
                ]));
        }
        catch (Exception $exc)
        {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(['exceptions' => [exceptionToJavaScript($exc)]]));
        }
    }

    /**
     * [AJAX] Delete a provider record from the database.
     *
     * Required POST Parameters:
     *
     * - int $_POST['provider_id'] The id of the record to be deleted.
     *
     * Outputs the operation result constant (AJAX_SUCCESS or AJAX_FAILURE).
     */
    public function ajax_delete_provider()
    {
        try
        {
            if ($this->privileges[PRIV_USERS]['delete'] == FALSE)
            {
                throw new Exception('You do not have the required privileges for this task.');
            }

            $this->load->model('providers_model');
            $result = $this->providers_model->delete($this->input->post('provider_id'));
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode($result ? AJAX_SUCCESS : AJAX_FAILURE));
        }
        catch (Exception $exc)
        {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(['exceptions' => [exceptionToJavaScript($exc)]]));
        }
    }

    /**
     * [AJAX] Filter secretary records with string key.
     *
     * Required POST Parameters:
     *
     * - string $_POST['key'] The key string used to filter the records.
     *
     * Outputs a JSON encoded array back to client with the secretary records.
     */
    public function ajax_filter_secretaries()
    {
        try
        {
            if ($this->privileges[PRIV_USERS]['view'] == FALSE)
            {
                throw new Exception('You do not have the required privileges for this task.');
            }

            $this->load->model('secretaries_model');
            $key = $this->db->escape_str($this->input->post('key'));
            $where =
                '(first_name LIKE "%' . $key . '%" OR last_name LIKE "%' . $key . '%" ' .
                'OR email LIKE "%' . $key . '%" OR mobile_number LIKE "%' . $key . '%" ' .
                'OR phone_number LIKE "%' . $key . '%" OR address LIKE "%' . $key . '%" ' .
                'OR city LIKE "%' . $key . '%" OR state LIKE "%' . $key . '%" ' .
                'OR zip_code LIKE "%' . $key . '%" OR notes LIKE "%' . $key . '%")';
            $secretaries = $this->secretaries_model->get_batch($where);
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode($secretaries));
        }
        catch (Exception $exc)
        {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(['exceptions' => [exceptionToJavaScript($exc)]]));
        }
    }

    /**
     * [AJAX] Save (insert or update) a secretary record into database.
     *
     * Required POST Parameters:
     *
     * - array $_POST['secretary'] A json encoded array that contains the secretary data.
     * If an 'id' value is provided then the record is going to be updated.
     *
     * Outputs the success constant 'AJAX_SUCCESS' so JavaScript knows that everything completed successfully.
     */
    public function ajax_save_secretary()
    {
        try
        {
            $this->load->model('secretaries_model');
            $secretary = json_decode($this->input->post('secretary'), TRUE);

            $REQUIRED_PRIV = ( ! isset($secretary['id']))
                ? $this->privileges[PRIV_USERS]['add']
                : $this->privileges[PRIV_USERS]['edit'];
            if ($REQUIRED_PRIV == FALSE)
            {
                throw new Exception('You do not have the required privileges for this task.');
            }

            $secretary_id = $this->secretaries_model->add($secretary);

            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'status' => AJAX_SUCCESS,
                    'id' => $secretary_id
                ]));
        }
        catch (Exception $exc)
        {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(['exceptions' => [exceptionToJavaScript($exc)]]));
        }
    }

    /**
     * [AJAX] Delete a secretary record from the database.
     *
     * Required POST Parameters:
     *
     * - int $_POST['secretary_id'] The id of the record to be deleted.
     *
     * Outputs the operation result constant (AJAX_SUCCESS or AJAX_FAILURE).
     */
    public function ajax_delete_secretary()
    {
        try
        {
            if ($this->privileges[PRIV_USERS]['delete'] == FALSE)
            {
                throw new Exception('You do not have the required privileges for this task.');
            }

            $this->load->model('secretaries_model');
            $result = $this->secretaries_model->delete($this->input->post('secretary_id'));

            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode($result ? AJAX_SUCCESS : AJAX_FAILURE));
        }
        catch (Exception $exc)
        {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(['exceptions' => [exceptionToJavaScript($exc)]]));
        }
    }

    /**
     * [AJAX] Save a setting or multiple settings in the database.
     *
     * This method is used to store settings in the database. It can be either system or user settings, one or many.
     * Use the $_POST variables accordingly.
     *
     * Required POST Parameters:
     *
     * - array $_POST['settings'] Contains an array with settings.
     * - bool $_POST['type'] Determines the settings type, can be either SETTINGS_SYSTEM or SETTINGS_USER.
     */
    public function ajax_save_settings()
    {
        try
        {
            if ($this->input->post('type') == SETTINGS_SYSTEM)
            {
                if ($this->privileges[PRIV_SYSTEM_SETTINGS]['edit'] == FALSE)
                {
                    throw new Exception('You do not have the required privileges for this task.');
                }
                $this->load->model('settings_model');
                $settings = json_decode($this->input->post('settings', FALSE), TRUE);
                $this->settings_model->save_settings($settings);
            }
            else
            {
                if ($this->input->post('type') == SETTINGS_USER)
                {
                    if ($this->privileges[PRIV_USER_SETTINGS]['edit'] == FALSE)
                    {
                        throw new Exception('You do not have the required privileges for this task.');
                    }
                    $this->load->model('user_model');
                    $this->user_model->save_settings(json_decode($this->input->post('settings'), TRUE));
                }
            }

            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(AJAX_SUCCESS));
        }
        catch (Exception $exc)
        {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(['exceptions' => [exceptionToJavaScript($exc)]]));
        }
    }

    public function ajax_save_user_calendar_selections()
    {
        try
        {
            $this->load->model('user_model');
            $this->user_model->save_user_selections($this->input->post('selections'));

            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(AJAX_SUCCESS));
        }
        catch (Exception $exc)
        {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(['exceptions' => [exceptionToJavaScript($exc)]]));
        }
    }

    /**
     * [AJAX] This method checks whether the username already exists in the database.
     *
     * Required POST Parameters:
     *
     * - string $_POST['username'] Record's username to validate.
     * - bool $_POST['record_exists'] Whether the record already exists in database.
     */
    public function ajax_validate_username()
    {
        try
        {
            // We will only use the function in the admins_model because it is sufficient
            // for the rest user types for now (providers, secretaries).
            $this->load->model('admins_model');
            $is_valid = $this->admins_model->validate_username($this->input->post('username'),
                $this->input->post('user_id'));
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode($is_valid));
        }
        catch (Exception $exc)
        {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(['exceptions' => [exceptionToJavaScript($exc)]]));
        }
    }

    /**
     * [AJAX] Change system language for current user.
     *
     * The language setting is stored in session data and retrieved every time the user visits any of the system pages.
     *
     * Required POST Parameters:
     *
     * - string $_POST['language'] Selected language name.
     */
    public function ajax_change_language()
    {
        try
        {
            // Check if language exists in the available languages.
            $found = FALSE;
            foreach ($this->config->item('available_languages') as $lang)
            {
                if ($lang == $this->input->post('language'))
                {
                    $found = TRUE;
                    break;
                }
            }

            if ( ! $found)
            {
                throw new Exception('Translations for the given language does not exist (' . $this->input->post('language') . ').');
            }

            $this->session->set_userdata('language', $this->input->post('language'));
            $this->config->set_item('language', $this->input->post('language'));

            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(AJAX_SUCCESS));

        }
        catch (Exception $exc)
        {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(['exceptions' => [exceptionToJavaScript($exc)]]));
        }
    }

    /**
     * This method will return a list of the available google calendars.
     *
     * The user will need to select a specific calendar from this list to sync his appointments with. Google access must
     * be already granted for the specific provider.
     *
     * Required POST Parameters:
     *
     * - string $_POST['provider_id'] Provider record id.
     */
    public function ajax_get_google_calendars()
    {
        try
        {
            $this->load->library('google_sync');
            $this->load->model('providers_model');

            if ( ! $this->input->post('provider_id'))
            {
                throw new Exception('Provider id is required in order to fetch the google calendars.');
            }

            // Check if selected provider has sync enabled.
            $google_sync = $this->providers_model->get_setting('google_sync', $this->input->post('provider_id'));
            if ($google_sync)
            {
                $google_token = json_decode($this->providers_model->get_setting('google_token',
                    $this->input->post('provider_id')));
                $this->google_sync->refresh_token($google_token->refresh_token);
                $calendars = $this->google_sync->get_google_calendars();
                $this->output
                    ->set_content_type('application/json')
                    ->set_output(json_encode($calendars));
            }
            else
            {
                $this->output
                    ->set_content_type('application/json')
                    ->set_output(json_encode(AJAX_FAILURE));
            }
        }
        catch (Exception $exc)
        {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(['exceptions' => [exceptionToJavaScript($exc)]]));
        }
    }

    /**
     * Select a specific google calendar for a provider.
     *
     * All the appointments will be synced with this particular calendar.
     *
     * Required POST Parameters:
     *
     * - int $_POST['provider_id'] Provider record id.
     * - string $_POST['calendar_id'] Google calendar's id.
     */
    public function ajax_select_google_calendar()
    {
        try
        {
            if ($this->privileges[PRIV_USERS]['edit'] == FALSE
                && $this->session->userdata('user_id') != $this->input->post('provider_id'))
            {
                throw new Exception('You do not have the required privileges for this task.');
            }

            $this->load->model('providers_model');
            $result = $this->providers_model->set_setting('google_calendar', $this->input->post('calendar_id'),
                $this->input->post('provider_id'));

            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode($result ? AJAX_SUCCESS : AJAX_FAILURE));
        }
        catch (Exception $exc)
        {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(['exceptions' => [exceptionToJavaScript($exc)]]));
        }
    }

    /**
     * [AJAX] Load a SOAP Report record.
     *
     * Require POST Parameters:
     *
     * - array $_POST['pet_id'] JSON encoded array that contains the customer's data.
     */
    public function ajax_load_soap_reports()
    {
        try
        {
            $this->load->model('soap_reports_model');
            $pet_id = $this->input->post('pet_id');

            $REQUIRED_PRIV = ( ! isset($customer['id']))
                ? $this->privileges[PRIV_APPOINTMENTS]['add']
                : $this->privileges[PRIV_APPOINTMENTS]['edit'];
            if ($REQUIRED_PRIV == FALSE)
            {
                throw new Exception('You do not have the required privileges for this task.');
            }

            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'status' => AJAX_SUCCESS,
                    'reports' => $this->soap_reports_model->get_reports($pet_id),
                ]));
        }
        catch (Exception $exc)
        {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(['exceptions' => [exceptionToJavaScript($exc)]]));
        }
    }

        /**
     * [AJAX] Save (insert or update) a SOAP Report record.
     *
     * Require POST Parameters:
     *
     * - array $_POST['report'] JSON encoded array that contains the customer's data.
     */
    public function ajax_save_soap_report()
    {
        try
        {
            $this->load->model('soap_reports_model');
            $report = json_decode($this->input->post('report', FALSE), TRUE);

            $REQUIRED_PRIV = ( ! isset($customer['id']))
                ? $this->privileges[PRIV_APPOINTMENTS]['add']
                : $this->privileges[PRIV_APPOINTMENTS]['edit'];
            if ($REQUIRED_PRIV == FALSE)
            {
                throw new Exception('You do not have the required privileges for this task.');
            }

            $report_id = $this->soap_reports_model->add($report);
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'status' => AJAX_SUCCESS,
                    'id' => $report_id,
                    'reports' => $this->soap_reports_model->get_reports($report['id_pets']),
                ]));
        }
        catch (Exception $exc)
        {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(['exceptions' => [exceptionToJavaScript($exc)]]));
        }
    }

    /**
     * [AJAX] Delete SOAP Report from database.
     *
     * Required POST Parameters:
     *
     * - int $_POST['id'] Customer record id to be deleted.
     */
    public function ajax_delete_soap_report()
    {
        try
        {
            if ($this->privileges[PRIV_APPOINTMENTS]['delete'] == FALSE)
            {
                throw new Exception('You do not have the required privileges for this task.');
            }

            $this->load->model('soap_reports_model');
            $report = $this->soap_reports_model->get_row($this->input->post('id'));
            $this->soap_reports_model->delete($this->input->post('id'));
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'status' => AJAX_SUCCESS,
                    'reports' => $this->soap_reports_model->get_reports($report['id_pets']),
                ]));
        }
        catch (Exception $exc)
        {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(['exceptions' => [exceptionToJavaScript($exc)]]));
        }
    }

    public function ajax_test_sms()
    {
        try
        {
            if ($this->privileges[PRIV_USERS]['edit'] == FALSE
                && $this->session->userdata('user_id') != $this->input->post('provider_id'))
            {
                throw new Exception('You do not have the required privileges for this task.');
            }

            $this->load->model('settings_model');
            $this->load->model('appointments_model');

            $config = [
                'sms_sender' => $this->settings_model->get_setting('sms_sender'),
                'sms_username' => $this->settings_model->get_setting('sms_username'),
                'sms_password' => $this->settings_model->get_setting('sms_password'),
                ];

            $sms = new \EA\Engine\Notifications\SMS($this, $config);

            $appointment = $this->appointments_model->get_sample_appointment();

            if($appointment){
                $notification_type = 'sms_reminder';

                $notification = $this->settings_model->getNotification(
                    $notification_type,
                    $appointment->appointment,
                    $appointment->provider,
                    $appointment->service,
                    $appointment->customer,
                    $appointment->pet,
                    TRUE);
                
                $sms->sendText($notification, new NonEmptyText($this->input->post('phone_number')));

                $this->output
                    ->set_content_type('application/json')
                    ->set_output(json_encode(AJAX_SUCCESS));
            }
            else
                throw new Exception('No Appointments Found To Demonstrate!');
            }
        catch (Exception $exc)
        {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(['exceptions' => [exceptionToJavaScript($exc)]]));
        }
    }
}
