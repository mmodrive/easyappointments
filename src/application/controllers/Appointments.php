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

/**
 * Appointments Controller
 *
 * @package Controllers
 */
class Appointments extends CI_Controller {
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

        $this->load->library('session');
        $this->load->helper('installation');

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

        // Common helpers
        $this->load->helper('google_analytics');

        $this->load->model('roles_model');

        if ($this->session->userdata('role_slug'))
        {
            $this->privileges = $this->roles_model->get_privileges($this->session->userdata('role_slug'));
        }
    }

    /**
     * Default callback method of the application.
     *
     * This method creates the appointment book wizard. If an appointment hash
     * is provided then it means that the customer followed the appointment
     * manage link that was send with the book success email.
     *
     * @param string $appointment_hash DB appointment hash of an existing record (default '').
     */
    public function index($appointment_hash = '')
    {
        if ( ! is_ea_installed())
        {
            redirect('installation/index');
            return;
        }

        $this->load->model('appointments_model');
        $this->load->model('providers_model');
        $this->load->model('services_model');
        $this->load->model('customers_model');
        $this->load->model('settings_model');

        try
        {
            $available_services = $this->services_model->get_available_services();
            $available_providers = $this->providers_model->get_available_providers();
            $company_name = $this->settings_model->get_setting('company_name');
            $date_format = $this->settings_model->get_setting('date_format');
            $time_format = $this->settings_model->get_setting('time_format');
            $display_cookie_notice = $this->settings_model->get_setting('display_cookie_notice');
            $cookie_notice_content = $this->settings_model->get_setting('cookie_notice_content');
            $display_terms_and_conditions = $this->settings_model->get_setting('display_terms_and_conditions');
            $terms_and_conditions_content = $this->settings_model->get_setting('terms_and_conditions_content');
            $display_privacy_policy = $this->settings_model->get_setting('display_privacy_policy');
            $privacy_policy_content = $this->settings_model->get_setting('privacy_policy_content');

            // Remove the data that are not needed inside the $available_providers array.
            foreach ($available_providers as $index => $provider)
            {
                $stripped_data = [
                    'id' => $provider['id'],
                    'first_name' => $provider['first_name'],
                    'last_name' => $provider['last_name'],
                    'services' => $provider['services']
                ];
                $available_providers[$index] = $stripped_data;
            }

            // If an appointment hash is provided then it means that the customer
            // is trying to edit a registered appointment record.
            if ($appointment_hash !== '')
            {
                // Load the appointments data and enable the manage mode of the page.
                $manage_mode = TRUE;

                $results = $this->appointments_model->get_batch(['hash' => $appointment_hash]);

                if (count($results) === 0)
                {
                    // The requested appointment doesn't exist in the database. Display
                    // a message to the customer.
                    $view = [
                        'message_title' => $this->lang->line('appointment_not_found'),
                        'message_text' => $this->lang->line('appointment_does_not_exist_in_db'),
                        'message_icon' => base_url('assets/img/error.png')
                    ];
                    $this->load->view('appointments/message', $view);
                    return;
                }

                $appointment = $results[0];
                $provider = $this->providers_model->get_row($appointment['id_users_provider']);
                $customer = $this->customers_model->get_row($appointment['id_users_customer']);

                $customer_token = md5(uniqid(mt_rand(), true));

                $this->load->driver('cache', ['adapter' => 'file']);

                $this->cache->save('customer-token-' . $customer_token, $customer['id'], 600); // save for 10 minutes
            }
            else
            {
                // The customer is going to book a new appointment so there is no
                // need for the manage functionality to be initialized.
                $manage_mode = FALSE;
                $customer_token = FALSE;
                $appointment = [];
                $provider = [];
                $customer = [];
            }

            // Load the book appointment view.
            $view = [
                'available_services' => $available_services,
                'available_providers' => $available_providers,
                'company_name' => $company_name,
                'manage_mode' => $manage_mode,
                'customer_token' => $customer_token,
                'date_format' => $date_format,
                'time_format' => $time_format,
                'appointment_data' => $appointment,
                'provider_data' => $provider,
                'customer_data' => $customer,
                'display_cookie_notice' => $display_cookie_notice,
                'cookie_notice_content' => $cookie_notice_content,
                'display_terms_and_conditions' => $display_terms_and_conditions,
                'terms_and_conditions_content' => $terms_and_conditions_content,
                'display_privacy_policy' => $display_privacy_policy,
                'privacy_policy_content' => $privacy_policy_content,
            ];
        }
        catch (Exception $exc)
        {
            $view['exceptions'][] = $exc;
        }

        $this->load->view('appointments/book', $view);
    }

    /**
     * Cancel an existing appointment.
     *
     * This method removes an appointment from the company's schedule. In order for the appointment to be deleted, the
     * hash string must be provided. The customer can only cancel the appointment if the edit time period is not over
     * yet. Provide the $_POST['cancel_reason'] parameter to describe the cancellation reason.
     *
     * @param string $appointment_hash This is used to distinguish the appointment record.
     */
    public function cancel($appointment_hash)
    {
        try
        {
            $this->load->model('appointments_model');
            $this->load->model('providers_model');
            $this->load->model('customers_model');
            $this->load->model('services_model');
            $this->load->model('settings_model');

            // Check whether the appointment hash exists in the database.
            $records = $this->appointments_model->get_batch(['hash' => $appointment_hash]);
            if (count($records) == 0)
            {
                throw new Exception('No record matches the provided hash.');
            }

            $appointment = $records[0];
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

            // :: DELETE APPOINTMENT RECORD FROM THE DATABASE.
            if ( ! $this->appointments_model->delete($appointment['id']))
            {
                throw new Exception('Appointment could not be deleted from the database.');
            }

            // :: SYNC APPOINTMENT REMOVAL WITH GOOGLE CALENDAR
            if ($appointment['id_google_calendar'] != NULL)
            {
                try
                {
                    $google_sync = filter_var($this->providers_model
                        ->get_setting('google_sync', $appointment['id_users_provider']), FILTER_VALIDATE_BOOLEAN);

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
                    $exceptions[] = $exc;
                }
            }

            // :: SEND NOTIFICATION EMAILS TO CUSTOMER AND PROVIDER
            try
            {
                $this->config->load('email');
                $email = new \EA\Engine\Notifications\Email($this, $this->config->config);

                $send_provider = filter_var($this->providers_model
                    ->get_setting('notifications', $provider['id']), FILTER_VALIDATE_BOOLEAN);

                if ($send_provider === TRUE)
                {
                    $email->sendDeleteAppointment($appointment, $provider,
                        $service, $customer, $company_settings, new Email($provider['email']),
                        new Text($this->input->post('cancel_reason')));
                }

                $send_customer = filter_var($this->settings_model->get_setting('customer_notifications'),
                    FILTER_VALIDATE_BOOLEAN);

                if ($send_customer === TRUE)
                {
                    $email->sendDeleteAppointment($appointment, $provider,
                        $service, $customer, $company_settings, new Email($customer['email']),
                        new Text($this->input->post('cancel_reason')));
                }

            }
            catch (Exception $exc)
            {
                $exceptions[] = $exc;
            }
        }
        catch (Exception $exc)
        {
            // Display the error message to the customer.
            $exceptions[] = $exc;
        }

        $view = [
            'message_title' => $this->lang->line('appointment_cancelled_title'),
            'message_text' => $this->lang->line('appointment_cancelled'),
            'message_icon' => base_url('assets/img/success.png')
        ];

        if (isset($exceptions))
        {
            $view['exceptions'] = $exceptions;
        }

        $this->load->view('appointments/message', $view);
    }

    /**
     * GET an specific appointment book and redirect to the success screen.
     *
     * @param int $appointment_id Contains the ID of the appointment to retrieve.
     */
    public function book_success($appointment_id)
    {
        // If the appointment id doesn't exist or zero redirect to index.
        if ( ! $appointment_id)
        {
            redirect('appointments');
        }
        $this->load->model('appointments_model');
        $this->load->model('providers_model');
        $this->load->model('services_model');
        $this->load->model('settings_model');
        //retrieve the data needed in the view
        $appointment = $this->appointments_model->get_row($appointment_id);
        $provider = $this->providers_model->get_row($appointment['id_users_provider']);
        $service = $this->services_model->get_row($appointment['id_services']);
        $company_name = $this->settings_model->get_setting('company_name');
        //get the exceptions
        $exceptions = $this->session->flashdata('book_success');
        // :: LOAD THE BOOK SUCCESS VIEW
        $view = [
            'appointment_data' => $appointment,
            'provider_data' => $provider,
            'service_data' => $service,
            'company_name' => $company_name,
        ];
        if ($exceptions)
        {
            $view['exceptions'] = $exceptions;
        }
        $this->load->view('appointments/book_success', $view);
    }

    /**
     * [AJAX] Get the available appointment hours for the given date.
     *
     * This method answers to an AJAX request. It calculates the available hours for the given service, provider and
     * date.
     *
     * Required POST parameters:
     *
     * - int $_POST['service_id'] Selected service record ID.
     * - int|string $_POST['provider_id'] Selected provider record id, can also be 'any-provider'.
     * - string $_POST['selected_date'] Selected date for availabilities.
     * - int $_POST['service_duration'] Selected service duration in minutes.
     * - string $_POST['manage_mode'] Contains either 'true' or 'false' and determines the if current user
     * is managing an already booked appointment or not.
     *
     * Outputs a JSON string with the availabilities.
     *
     * @deprecated Since v1.3.0, this method will be replaced with a future release.
     */
    public function ajax_get_available_hours()
    {
        $this->load->model('providers_model');
        $this->load->model('appointments_model');
        $this->load->model('settings_model');
        $this->load->model('services_model');

        try
        {
            // Do not continue if there was no provider selected (more likely there is no provider in the system).
            if (empty($this->input->post('provider_id')))
            {
                $this->output
                    ->set_content_type('application/json')
                    ->set_output(json_encode([]));
                return;
            }

            // If manage mode is TRUE then the following we should not consider the selected
            // appointment when calculating the available time periods of the provider.
            $exclude_appointments = ($this->input->post('manage_mode') === 'true')
                ? [$this->input->post('appointment_id')]
                : [];

            // If the user has selected the "any-provider" option then we will need to search
            // for an available provider that will provide the requested service.
            if ($this->input->post('provider_id') === ANY_PROVIDER)
            {
                $provider_ids = $this->_search_providers_by_service($this->input->post('service_id'),
                    $this->input->post('selected_date'));
                if ($this->input->post('provider_id') === NULL)
                {
                    $this->output
                        ->set_content_type('application/json')
                        ->set_output(json_encode([]));
                    return;
                }
            }
            else
                $provider_ids = [$this->input->post('provider_id')];

            $data = [];
            $service = $this->services_model->get_row($this->input->post('service_id'));

            foreach ($provider_ids as $provider_id) {
                $provider = $this->providers_model->get_row($provider_id);

                $empty_periods = $this->_get_provider_available_time_periods($provider_id,
                    $this->input->post('service_id'),
                    $this->input->post('selected_date'), $exclude_appointments);

                $available_hours = $this->_calculate_available_hours($empty_periods, $this->input->post('selected_date'),
                    $this->input->post('service_duration'),
                    filter_var($this->input->post('manage_mode'), FILTER_VALIDATE_BOOLEAN),
                    $service['availabilities_type']);

                // if ($service['attendants_number'] > 1)
                // {
                    // $available_hours = $this->_get_multiple_attendants_hours($this->input->post('selected_date'), $service,
                    //     $provider);
                // }

                // If the selected date is today, remove past hours. It is important  include the timeout before
                // booking that is set in the back-office the system. Normally we might want the customer to book
                // an appointment that is at least half or one hour from now. The setting is stored in minutes.
                if (date('Y-m-d', strtotime($this->input->post('selected_date'))) === date('Y-m-d'))
                {
                    $book_advance_timeout = $this->settings_model->get_setting('book_advance_timeout');

                    foreach ($available_hours as $index => $value)
                    {
                        $available_hour = strtotime($value);
                        $current_hour = strtotime('+' . $book_advance_timeout . ' minutes', strtotime('now'));
                        if ($available_hour <= $current_hour)
                        {
                            unset($available_hours[$index]);
                        }
                    }
                }

                $available_hours = array_values($available_hours);
                sort($available_hours, SORT_STRING);
                $available_hours = array_values($available_hours);
                $data[$provider['id']] = $available_hours;
            }

            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode($data));
        }
        catch (Exception $exc)
        {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'exceptions' => [exceptionToJavaScript($exc)]
                ]));
        }
    }

    /**
     * [AJAX] Check whether the user has entered the correct login credentials.
     *
     * The session data of a logged in user are the following:
     *   - 'user_id'
     *   - 'user_email'
     *   - 'role_slug'
     *   - 'dest_url'
     */
    public function ajax_check_customer_login()
    {
        try
        {
            if ( ! $this->input->post('email') || ! $this->input->post('password'))
            {
                throw new Exception('Invalid credentials given!');
            }

            $this->load->model('customers_model');
            $customer_data = $this->customers_model->check_login($this->input->post('email'), $this->input->post('password'));

            if ($customer_data)
            {
                $this->output
                    ->set_content_type('application/json')
                    ->set_output(json_encode($customer_data));
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
     * [AJAX] Register the appointment to the database.
     *
     * Outputs a JSON string with the appointment ID.
     */
    public function ajax_register_appointment()
    {
        try
        {
            $_POST["post_data"] = json_decode($_POST["post_data"], true);
            $post_data = $this->input->post('post_data'); // alias
            $post_data['manage_mode'] = filter_var($post_data['manage_mode'], FILTER_VALIDATE_BOOLEAN);

            $this->load->model('appointments_model');
            $this->load->model('providers_model');
            $this->load->model('services_model');
            $this->load->model('customers_model');
            $this->load->model('settings_model');
            $this->load->model('pets_model');

            // Validate the CAPTCHA string.
            if ($this->settings_model->get_setting('require_captcha') === '1'
                && $this->session->userdata('captcha_phrase') !== $this->input->post('captcha'))
            {
                $this->output
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'captcha_verification' => FALSE
                    ]));
                return;
            }

            // Check appointment availability.
            if ( ! $this->_check_datetime_availability())
            {
                throw new Exception($this->lang->line('requested_hour_is_unavailable'));
            }

            $appointment = $post_data['appointment'];
            $customer = $post_data['customer'];
            $pet = $post_data['pet'] ?? NULL;
            $is_existing_customer = FALSE;

            // We retrieve the existing customer here by email to allow for the passcode recovery mode
            if ($this->customers_model->exists($customer))
            {
                $customer['id'] = $this->customers_model->find_record_id($customer);
                $is_existing_customer = TRUE;
            }

            $customer_id = $this->customers_model->add($customer);
            $appointment['id_users_customer'] = $customer_id;
            $appointment['is_unavailable'] = (int)$appointment['is_unavailable']; // needs to be type casted
            if( isset($pet) ) {
                $pet['id_users'] = $customer_id;
                //Find existing pet by customer owner and pet name 
                if ($this->pets_model->exists($pet) && ! isset($pet['id']))
                    $pet['id'] = $this->pets_model->find_record_id($pet);
                $appointment['id_pets'] = $this->pets_model->add($pet);
            }
            $appointment['id'] = $this->appointments_model->add($appointment);
            $appointment['hash'] = $this->appointments_model->get_value('hash', $appointment['id']);

            $provider = $this->providers_model->get_row($appointment['id_users_provider']);
            $service = $this->services_model->get_row($appointment['id_services']);
            if( isset($appointment['id_pets']) )
                $pet = $this->pets_model->get_row($appointment['id_pets']);

            $company_settings = [
                'company_name' => $this->settings_model->get_setting('company_name'),
                'company_link' => $this->settings_model->get_setting('company_link'),
                'company_email' => $this->settings_model->get_setting('company_email'),
                'date_format' => $this->settings_model->get_setting('date_format'),
                'time_format' => $this->settings_model->get_setting('time_format')
            ];

            // :: SYNCHRONIZE APPOINTMENT WITH PROVIDER'S GOOGLE CALENDAR
            // The provider must have previously granted access to his google calendar account
            // in order to sync the appointment.
            try
            {
                $google_sync = filter_var($this->providers_model->get_setting('google_sync',
                    $appointment['id_users_provider']), FILTER_VALIDATE_BOOLEAN);

                if ($google_sync == TRUE)
                {
                    $google_token = json_decode($this->providers_model
                        ->get_setting('google_token', $appointment['id_users_provider']));

                    $this->load->library('google_sync');
                    $this->google_sync->refresh_token($google_token->refresh_token);

                    if ($post_data['manage_mode'] === FALSE)
                    {
                        // Add appointment to Google Calendar.
                        $google_event = $this->google_sync->add_appointment($appointment, $provider,
                            $service, $customer, $company_settings);
                        $appointment['id_google_calendar'] = $google_event->id;
                        $this->appointments_model->add($appointment);
                    }
                    else
                    {
                        // Update appointment to Google Calendar.
                        $appointment['id_google_calendar'] = $this->appointments_model
                            ->get_value('id_google_calendar', $appointment['id']);

                        $this->google_sync->update_appointment($appointment, $provider,
                            $service, $customer, $company_settings);
                    }
                }
            }
            catch (Exception $exc)
            {
                log_message('error', $exc->getMessage());
                log_message('error', $exc->getTraceAsString());
            }

            // :: SEND NOTIFICATION EMAILS TO BOTH CUSTOMER AND PROVIDER
            try
            {
                $this->config->load('email');
                $this->load->model('settings_model');

                $email = new \EA\Engine\Notifications\Email($this, $this->config->config);

                if ($post_data['manage_mode'] == FALSE)
                    $notification_type = 'email_appointment_new';
                else
                    $notification_type = 'email_appointment_change';

                $send_customer = filter_var($this->settings_model->get_setting('customer_notifications'),
                    FILTER_VALIDATE_BOOLEAN);

                $this->load->library('ics_file');
                $ics_stream = $this->ics_file->get_stream($appointment, $service, $provider, $customer);

                if ($send_customer === TRUE)
                {
                    if(!$is_existing_customer){
                        $notification = $this->settings_model->getNotification(
                            'email_customer_registration',$appointment, $provider, $service, $customer, $pet, TRUE
                        );
                        $email->sendEmail($notification, new Email($customer['email']));
                    }

                    $notification = $this->settings_model->getNotification(
                        $notification_type,$appointment, $provider, $service, $customer, $pet, TRUE
                    );
                    $email->sendEmail($notification, new Email($customer['email']), new Text($ics_stream));
                }

                $send_provider = filter_var($this->providers_model->get_setting('notifications', $provider['id']),
                    FILTER_VALIDATE_BOOLEAN);

                if ($send_provider === TRUE)
                {
                    $notification = $this->settings_model->getNotification(
                        $notification_type,$appointment, $provider, $service, $customer, $pet, FALSE
                    );
                    $email->sendEmail($notification, new Email($provider['email']), new Text($ics_stream));
                }

                // Notify all staff of new customer registration
                if(!$is_existing_customer){
                    $notification = $this->settings_model->getNotification(
                        'email_customer_registration',$appointment, $provider, $service, $customer, $pet, TRUE
                    );

                    $addresses = $this->settings_model->get_new_customer_notification_emails();

                    foreach ($addresses as $key => $value) {
                        $email->sendEmail($notification, new Email($value['email']));
                    }
                }
            }
            catch (Exception $exc)
            {
                log_message('error', $exc->getMessage());
                log_message('error', $exc->getTraceAsString());
            }

            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'appointment_id' => $appointment['id']
                ]));
        }
        catch (Exception $exc)
        {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'exceptions' => [exceptionToJavaScript($exc)]
                ]));
        }
    }

    public function ajax_get_notworking_times()
    {
        try
        {
            if ($this->privileges[PRIV_APPOINTMENTS]['view'] == FALSE)
            {
                throw new Exception('You do not have the required privileges for this task.');
            }

            $response['periods'] = [];

            if ( ! $this->input->post('filter_type'))
            {
                $this->output
                    ->set_content_type('application/json')
                    ->set_output(json_encode($response));
                return;
            }

            $this->load->model('appointments_model');
            $this->load->model('providers_model');
            $this->load->model('services_model');

            $record_id = $_POST['record_id'];
            $start_date = new DateTime($_POST['start_date']);
            $end_date = new DateTime($_POST['end_date']);

            if ($this->input->post('filter_type') == FILTER_TYPE_PROVIDER)
            {
                $working_plan = json_decode($this->providers_model->get_setting('working_plan', $record_id), TRUE);
                $all_periods = [];

                for($current_date = $start_date; $current_date < $end_date; $current_date->modify('+1 day')){
                    $periods = [];
                    $periods[] = [
                        'start' => new DateTime($current_date->format('Ymd').' 00:00'),
                        'end' => new DateTime($current_date->format('Ymd').' 23:59')
                    ];

                    // Get Availabilities
                    $open_periods = $this->_get_availabilities($working_plan, $current_date);
                    
                    // Add Working Plan Openings
                    $selected_date_working_plan = $working_plan[strtolower($current_date->format('l'))];
                    if( $selected_date_working_plan )
                        $open_periods[] = [
                            'start' => new DateTime($current_date->format('Ymd').' '.$selected_date_working_plan['start']),
                            'end' => new DateTime($current_date->format('Ymd').' '.$selected_date_working_plan['end']),
                            'services' => $selected_date_working_plan["services"]];
                    
                    if (!empty($open_periods))
                    {
                        // Split the working plan to available time periods that do not contain the breaks in them.
                        foreach ($open_periods as $open_period)
                        {        
                            foreach ($periods as $key => $period)
                            {
                                $remove_current_period = FALSE;
        
                                if ($open_period['start'] > $period['start'] && $open_period['start'] < $period['end'] && $open_period['end'] > $period['start'])
                                {
                                    $periods[] = [
                                        'start' => $period['start'],
                                        'end' => $open_period['start']
                                    ];
        
                                    $remove_current_period = TRUE;
                                }
        
                                if ($open_period['start'] < $period['end'] && $open_period['end'] > $period['start'] && $open_period['end'] < $period['end'])
                                {
                                    $periods[] = [
                                        'start' => $open_period['end'],
                                        'end' => $period['end']
                                    ];
        
                                    $remove_current_period = TRUE;
                                }
        
                                if ($open_period['start'] == $period['start'] && $open_period['end'] == $period['end'])
                                {
                                    $remove_current_period = TRUE;
                                }
        
                                if ($remove_current_period)
                                {
                                    unset($periods[$key]);
                                }
                            }
                        }
                    }
                    $all_periods = array_merge($all_periods, $periods);
                }

                $response['periods'] = $all_periods;
            }
            else
            {
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
     * [AJAX] Get Unavailable Dates
     *
     * Get an array with the available dates of a specific provider, service and month of the year. Provide the
     * "provider_id", "service_id" and "selected_date" as GET parameters to the request. The "selected_date" parameter
     * must have the Y-m-d format.
     *
     * Outputs a JSON string with the unavailable dates. that are unavailable.
     *
     * @deprecated Since v1.3.0, this method will be replaced with a future release.
     */
    public function ajax_get_unavailable_dates()
    {
        try
        {
            $provider_id = $this->input->get('provider_id');
            $service_id = $this->input->get('service_id');
            $selected_date_string = $this->input->get('selected_date');
            $selected_date = new DateTime($selected_date_string);
            $number_of_days_in_month = (int)$selected_date->format('t');
            $unavailable_dates = [];
            $manage_mode = filter_var($this->input->get('manage_mode'), FILTER_VALIDATE_BOOLEAN);

            $exclude_appointments = ($_REQUEST['manage_mode'] === 'true')
                ? [$_REQUEST['appointment_id']]
                : [];

            $provider_list = ($provider_id === ANY_PROVIDER) ? $this->_search_providers_by_service($service_id) : [$provider_id] ;

            $this->load->model('providers_model');

            // Get the service record.
            $this->load->model('services_model');
            $service = $this->services_model->get_row($service_id);

            for ($i = 1; $i <= $number_of_days_in_month; $i++)
            {
                $current_date = new DateTime($selected_date->format('Y-m') . '-' . $i);

                if ($current_date < new DateTime(date('Y-m-d 00:00:00')))
                {
                    // Past dates become immediately unavailable.
                    $unavailable_dates[] = $current_date->format('Y-m-d');
                    continue;
                }

                // Finding at least one slot of availablity
                foreach ($provider_list as $curr_provider_id)
                {
                    // Get the provider record.
                    $curr_provider = $this->providers_model->get_row($curr_provider_id);
                    
                    $empty_periods = $this->_get_provider_available_time_periods($curr_provider_id,
                        $service_id,
                        $current_date->format('Y-m-d'), $exclude_appointments);

                    $available_hours = $this->_calculate_available_hours($empty_periods, $current_date->format('Y-m-d'),
                        $service['duration'], $manage_mode, $service['availabilities_type']);
                    if (! empty($available_hours)) break;
                    
                    // if ($service['attendants_number'] > 1)
                    // {
                        // $available_hours = $this->_get_multiple_attendants_hours($current_date->format('Y-m-d'), $service,
                        //     $curr_provider);
                        // if (! empty($available_hours)) break;
                    // }
                }

                // No availability amongst all the provider
                if (empty($available_hours))
                {
                    $unavailable_dates[] = $current_date->format('Y-m-d');
                }
            }

            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode($unavailable_dates));
        }
        catch (Exception $exc)
        {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'exceptions' => [exceptionToJavaScript($exc)]
                ]));
        }
    }

    /**
     * Check whether the provider is still available in the selected appointment date.
     *
     * It might be times where two or more customers select the same appointment date and time. This shouldn't be
     * allowed to happen, so one of the two customers will eventually get the preferred date and the other one will have
     * to choose for another date. Use this method just before the customer confirms the appointment details. If the
     * selected date was taken in the mean time, the customer must be prompted to select another time for his
     * appointment.
     *
     * @return bool Returns whether the selected datetime is still available.
     */
    protected function _check_datetime_availability()
    {
        $this->load->model('services_model');
        $this->load->model('appointments_model');

        $appointment = $_POST['post_data']['appointment'];

        $service_duration = $this->services_model->get_value('duration', $appointment['id_services']);

        $exclude_appointments = (isset($appointment['id'])) ? [$appointment['id']] : [];

        // $attendants_number = $this->services_model->get_value('attendants_number', $appointment['id_services']);

        // if ($attendants_number > 1)
        // {
        //     // Exclude all the appointments that will are currently registered.
        //     $exclude = $this->appointments_model->get_batch([
        //         'id_services' => $appointment['id_services'],
        //         'start_datetime' => $appointment['start_datetime']
        //     ]);

        //     if ( ! empty($exclude) && count($exclude) < $attendants_number)
        //     {
        //         foreach ($exclude as $entry)
        //         {
        //             $exclude_appointments[] = $entry['id'];
        //         }
        //     }
        // }

        if ($appointment['id_users_provider'] === ANY_PROVIDER)
        {
            $appointment['id_users_provider'] = $this->_search_any_provider($appointment['id_services'],
                date('Y-m-d', strtotime($appointment['start_datetime'])));
            $_POST['post_data']['appointment']['id_users_provider'] = $appointment['id_users_provider'];
            return TRUE; // The selected provider is always available.
        }

        $available_periods = $this->_get_provider_available_time_periods(
            $appointment['id_users_provider'], $appointment['id_services'],
            date('Y-m-d', strtotime($appointment['start_datetime'])),
            $exclude_appointments);

        $is_still_available = FALSE;

        foreach ($available_periods as $period)
        {
            $appt_start = new DateTime($appointment['start_datetime']);
            $appt_start = $appt_start->format('H:i');

            $appt_end = new DateTime($appointment['start_datetime']);
            $appt_end->add(new DateInterval('PT' . $service_duration . 'M'));
            $appt_end = $appt_end->format('H:i');

            $period_start = date('H:i', strtotime($period['start']));
            $period_end = date('H:i', strtotime($period['end']));

            if ($period_start <= $appt_start && $period_end >= $appt_end)
            {
                $is_still_available = TRUE;
                break;
            }
        }

        return $is_still_available;
    }

    /**
     * Get an array containing the free time periods (start - end) of a selected date.
     *
     * This method is very important because there are many cases where the system needs to know when a provider is
     * available for an appointment. This method will return an array that belongs to the selected date and contains
     * values that have the start and the end time of an available time period.
     *
     * @param int $provider_id Provider record ID.
     * @param int $service_id Service record ID.
     * @param string $selected_date Date to be checked (MySQL formatted string).
     * @param array $excluded_appointment_ids Array containing the IDs of the appointments that will not be taken into
     * consideration when the available time periods are calculated.
     *
     * @return array Returns an array with the available time periods of the provider.
     */
    protected function _get_provider_available_time_periods(
        $provider_id,
        $service_id,
        $selected_date,
        $excluded_appointment_ids = []
    ) {
        $this->load->model('appointments_model');
        $this->load->model('providers_model');
        $this->load->model('services_model');

        // Get the service, provider's working plan and availabilities and provider appointments.
        $working_plan = json_decode($this->providers_model->get_setting('working_plan', $provider_id), TRUE);

        $provider_appointments = $this->appointments_model->get_batch([
            'id_users_provider' => $provider_id,
        ]);

        // Sometimes it might be necessary to not take into account some appointment records in order to display what
        // the providers' available time periods would be without them.
        foreach ($excluded_appointment_ids as $excluded_appointment_id)
        {
            foreach ($provider_appointments as $index => $reserved)
            {
                if ($reserved['id'] == $excluded_appointment_id)
                {
                    unset($provider_appointments[$index]);
                }
            }
        }

        // Find the empty spaces on the plan. The first split between the plan is due to a break (if any). After that
        // every reserved appointment is considered to be a taken space in the plan.
        $selected_date_dt = new DateTime($selected_date);
        $selected_date_working_plan = $working_plan[strtolower(date('l', strtotime($selected_date)))];

        $periods = [];

        $hoursRestriction = 0;
        $allowed_window_start;
        $allowed_window_end;
        
        $availabilities = $this->_get_availabilities($working_plan, $selected_date_dt, $service_id);

        // Check if service is provided on the day
        if( !(
               $selected_date_working_plan 
            && $selected_date_working_plan['services'] 
            && in_array($service_id, $selected_date_working_plan['services'])
            || !empty($availabilities) ) )
            return $periods;

        if(!empty($selected_date_working_plan['hours_restriction']))
            $hoursRestriction = intval($selected_date_working_plan['hours_restriction']);
        elseif( $availabilities ){
            $availHoursRestriction = [];
            foreach ($availabilities as $availability)
                if (!empty($availability['hours_restriction']) && is_numeric($availability['hours_restriction']))
                    $availHoursRestriction[] = intval($availability['hours_restriction']);
            if( !empty($availHoursRestriction) )
                $hoursRestriction = min($availHoursRestriction);
        }

        // Work out day start and end times within Restriction Hours
        if( $hoursRestriction > 0 || !empty($selected_date_working_plan['hours_restriction']) )
        {
            $appointmentStarts = [];
            $appointmentEnds = [];
            $app_windows = [];

            // Filter out appointments for the selected date only and record allowed hour windows
            foreach ($provider_appointments as $provider_appointment)
            {
                $appointment_start = new DateTime($provider_appointment['start_datetime']);
                $appointment_end = new DateTime($provider_appointment['end_datetime']);
                if( $appointment_start->format('Ymd') == $selected_date_dt->format('Ymd') )
                {
                    $allowed_window_start = $appointment_start->sub(new DateInterval('PT'.$hoursRestriction.'H'));
                    $allowed_window_end = $appointment_end->add(new DateInterval('PT'.$hoursRestriction.'H'));
                    array_push($app_windows, ['start' => $allowed_window_start, 'end' => $allowed_window_end]);
                    array_push($appointmentStarts, $allowed_window_start);
                    array_push($appointmentEnds, $allowed_window_end);
                }
            }
            
            // Intersection Join days appointment windows
            do {
                $changes = 0;
                foreach ($app_windows as $o_key => $o_value) 
                {
                    for ($j=$o_key+1; $j < count($app_windows); $j++) { 
                        if (isset($app_windows[$j]) && $o_value['end'] >= $app_windows[$j]['start'] &&
                        $o_value['end'] <= $app_windows[$j]['end']) {
                            $app_windows[$o_key]['end'] = $app_windows[$j]['end'];
                            unset($app_windows[$j]);
                            $changes = 1;
                        }
                    }
                }
            } while ($changes);

            // Sort appointment windows chronologically
            usort( $app_windows, function ($a, $b) { return ($a['start'] <=> $b['start']); } );

            // Invert / Create breaks for the non-appointment windows
            if( count($app_windows) > 1 ){
                if (!isset($selected_date_working_plan['breaks']))
                $selected_date_working_plan['breaks'] = [];
                for ($i=1; $i < count($app_windows); $i++) { 
                    // Insert the non-appointment window breaks at the start as they are assumed to be largest
                    // for the following breaking algorithm to work properly
                    array_unshift($selected_date_working_plan['breaks'],[
                        'start' => $app_windows[$i-1]['end']->format('H:i'),
                        'end' =>$app_windows[$i]['start']->format('H:i')
                    ]);
                }
            }

            // Record day start and end times as per appointment windows
            if( !empty($appointmentStarts)){
                $allowed_window_start = min($appointmentStarts);
                $allowed_window_end = max($appointmentEnds);
            }
        }
        
        $day_starts = [];
        $day_ends = [];

        // If WP defined for this day
        if( isset($selected_date_working_plan['start']) ){
            // Empty services assumes all allowed in this WP
            if( !$selected_date_working_plan['services'] || in_array($service_id, $selected_date_working_plan['services']) )
                $day_starts[] = new DateTime($selected_date_dt->format('Ymd').' '.$selected_date_working_plan['start']);
            // If WP does not allow current service, we want to override and exclude the WP window
            // from the window opened by Availabilities by creating an artificial Break for later processing
            if( $selected_date_working_plan['services'] && !in_array($service_id, $selected_date_working_plan['services']) )
                $selected_date_working_plan['breaks'][] = 
                [ 'start'=> $selected_date_working_plan['start'], 
                'end' => $selected_date_working_plan['end']];
        }
        // Add all Availabilities windows
        $day_starts = array_merge($day_starts, array_column($availabilities, 'start'));
        if( isset($selected_date_working_plan['end']) )
            $day_ends[] = new DateTime($selected_date_dt->format('Ymd').' '.$selected_date_working_plan['end']);
        $day_ends = array_merge($day_ends, array_column($availabilities, 'end'));

        // If there is a working plan or availability for the day
        if (!empty($day_starts))
        {
            $day_start = min($day_starts);
            $day_end = max($day_ends);

            // Curb the day start and end times by appointment windows if any
            if( isset($allowed_window_start)){
                $today_allowed_window_start = new DateTime($allowed_window_start->format('H:i'));
                $today_allowed_window_end = new DateTime($allowed_window_end->format('H:i'));
                $day_start = max($day_start, $today_allowed_window_start);
                $day_end = min($day_end, $today_allowed_window_end);
            }
            
            $periods[] = [
                'start' => $day_start->format('H:i'),
                'end' => $day_end->format('H:i')
            ];
            
            if (isset($selected_date_working_plan['breaks']))
            {
                // Split the working plan to available time periods that do not contain the breaks in them.
                foreach ($selected_date_working_plan['breaks'] as $index => $break)
                {
                    $break_start = new DateTime($selected_date_dt->format('Ymd').' '.$break['start']);
                    $break_end = new DateTime($selected_date_dt->format('Ymd').' '.$break['end']);

                    if ($break_start < $day_start)
                    {
                        $break_start = $day_start;
                    }

                    if ($break_end > $day_end)
                    {
                        $break_end = $day_end;
                    }

                    if ($break_start >= $break_end)
                    {
                        continue;
                    }

                    foreach ($periods as $key => $period)
                    {
                        $period_start = new DateTime($selected_date_dt->format('Ymd').' '.$period['start']);
                        $period_end = new DateTime($selected_date_dt->format('Ymd').' '.$period['end']);

                        $remove_current_period = FALSE;

                        if ($break_start > $period_start && $break_start < $period_end && $break_end > $period_start)
                        {
                            $periods[] = [
                                'start' => $period_start->format('H:i'),
                                'end' => $break_start->format('H:i')
                            ];

                            $remove_current_period = TRUE;
                        }

                        if ($break_start < $period_end && $break_end > $period_start && $break_end < $period_end)
                        {
                            $periods[] = [
                                'start' => $break_end->format('H:i'),
                                'end' => $period_end->format('H:i')
                            ];

                            $remove_current_period = TRUE;
                        }

                        if ($break_start == $period_start && $break_end == $period_end)
                        {
                            $remove_current_period = TRUE;
                        }

                        if ($remove_current_period)
                        {
                            unset($periods[$key]);
                        }
                    }
                }
            }
        }

        // Break the empty periods with the reserved appointments.
        foreach ($provider_appointments as $provider_appointment)
        {
            foreach ($periods as $index => &$period)
            {
                $appointment_start = new DateTime($provider_appointment['start_datetime']);
                $appointment_end = new DateTime($provider_appointment['end_datetime']);
                $period_start = new DateTime($selected_date . ' ' . $period['start']);
                $period_end = new DateTime($selected_date . ' ' . $period['end']);

                if ($appointment_start <= $period_start && $appointment_end <= $period_end && $appointment_end <= $period_start)
                {
                    // The appointment does not belong in this time period, so we  will not change anything.
                }
                else
                {
                    if ($appointment_start < $period_start && $appointment_end <= $period_end && $appointment_end >= $period_start)
                    {
                        // The appointment starts before the period and finishes somewhere inside. We will need to break
                        // this period and leave the available part.
                        $period['start'] = $appointment_end->format('H:i');
                    }
                    else
                    {
                        if ($appointment_start >= $period_start && $appointment_end < $period_end)
                        {
                            // The appointment is inside the time period, so we will split the period into two new
                            // others.
                            unset($periods[$index]);

                            $periods[] = [
                                'start' => $period_start->format('H:i'),
                                'end' => $appointment_start->format('H:i')
                            ];

                            $periods[] = [
                                'start' => $appointment_end->format('H:i'),
                                'end' => $period_end->format('H:i')
                            ];
                        }
                        else if ($appointment_start == $period_start && $appointment_end == $period_end)
                        {
                            unset($periods[$index]); // The whole period is blocked so remove it from the available periods array.
                        }
                        else
                        {
                            if ($appointment_start >= $period_start && $appointment_end >= $period_start && $appointment_start <= $period_end)
                            {
                                // The appointment starts in the period and finishes out of it. We will need to remove
                                // the time that is taken from the appointment.
                                $period['end'] = $appointment_start->format('H:i');
                            }
                            else
                            {
                                if ($appointment_start >= $period_start && $appointment_end >= $period_end && $appointment_start >= $period_end)
                                {
                                    // The appointment does not belong in the period so do not change anything.
                                }
                                else
                                {
                                    if ($appointment_start <= $period_start && $appointment_end >= $period_end && $appointment_start <= $period_end)
                                    {
                                        // The appointment is bigger than the period, so this period needs to be removed.
                                        unset($periods[$index]);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        return array_values($periods);
    }


    /**
     *
     * @param object $working_plan Working plan of the provider.
     * @param string $date The date to be searched.
     *
     * @return object Return TRUE availabilities do no restrict the date, FALSE if not available or Availability object when one exists.
     */
    protected function _get_availabilities($working_plan, $date, $service_id = NULL)
    {
        $availabilities = [];

        // Check if there are any availabilities for the provider at all
        if (isset($working_plan['availabilities']) && !empty($working_plan['availabilities']))
        {
            $format = 'YmdG:i';
            foreach ($working_plan['availabilities'] as $index => $availability) {
                $start_time = isset($availability["ts"]) && $availability["ts"] ? $availability["ts"] : '00:00';
                $start = DateTime::createFromFormat($format, $availability["start"].$start_time);
                // $end date is ignored i.e. is taken from start
                $end_time = isset($availability["te"]) && $availability["te"] ? $availability["te"] : '23:59';
                $end = DateTime::createFromFormat($format, $availability["start"].$end_time);
                // $end = DateTime::createFromFormat($format, $availability["end"].'235959999999');
                if($start->format('Y-m-d') == $date->format('Y-m-d') &&
                    ( empty($service_id) || ($availability['services'] 
                        && in_array($service_id, $availability['services'])) ))
                    $availabilities[] = [
                        'start' => $start, 
                        'end' => $end,
                        'services' => $availability["services"]];
            }

        }
        
        return $availabilities;
    }

    /**
     *
     * @param object $working_plan Working plan of the provider.
     * @param string $date The date to be searched.
     *
     * @return object Return TRUE availabilities do no restrict the date, FALSE if not available or Availability object when one exists.
     */
    // protected function _check_workingplan_available($working_plan, $date)
    // {
    //     // Check if there are any availabilities for the provider at all
    //     if (isset($working_plan['availabilities']) && !empty($working_plan['availabilities']))
    //     {
    //         $format = 'YmdGisu';
    //         foreach ($working_plan['availabilities'] as $index => $availability) {
    //             $start = DateTime::createFromFormat($format, $availability["start"].'000000000000');
    //             // $end date is ignored i.e. is taken from start 
    //             $end = DateTime::createFromFormat($format, $availability["start"].'235959999999');
    //             // $end = DateTime::createFromFormat($format, $availability["end"].'235959999999');
    //             if(($date >= $start) && ($date <= $end))
    //             {
    //                 return $availability;
    //             }
    //         }

    //         // There are availabilities and requested date is not available
    //         return FALSE;
    //     }

    //     // No restrictions
    //     return TRUE;
    // }

    /**
     * Search for any provider that can handle the requested service.
     *
     * This method will return the database ID of the provider with the most available periods.
     *
     * @param int $service_id The requested service ID.
     * @param string $selected_date The date to be searched.
     *
     * @return int Returns the ID of the provider that can provide the service at the selected date.
     */
    protected function _search_any_provider($service_id, $selected_date)
    {
        $this->load->model('providers_model');
        $this->load->model('services_model');
        $available_providers = $this->providers_model->get_available_providers();
        $service = $this->services_model->get_row($service_id);
        $provider_id = NULL;
        $max_hours_count = 0;

        foreach ($available_providers as $provider)
        {
            foreach ($provider['services'] as $provider_service_id)
            {
                if ($provider_service_id == $service_id)
                {
                    // Check if the provider is available for the requested date.
                    $empty_periods = $this->_get_provider_available_time_periods($provider['id'], $service_id,
                        $selected_date);

                    $available_hours = $this->_calculate_available_hours($empty_periods, $selected_date,
                        $service['duration'], FALSE, $service['availabilities_type']);

                    // if ($service['attendants_number'] > 1)
                    // {
                        // $available_hours = $this->_get_multiple_attendants_hours($selected_date, $service,
                        //     $provider);
                    // }

                    if (count($available_hours) > $max_hours_count)
                    {
                        $provider_id = $provider['id'];
                        $max_hours_count = count($available_hours);
                    }
                }
            }
        }

        return $provider_id;
    }

    /**
     * Search for any provider that can handle the requested service.
     *
     * This method will return the database ID of the providers affected to the requested service.
     *
     * @param numeric $service_id The requested service ID.
     *
     * @return array Returns the ID of the provider that can provide the requested service.
     */
    protected function _search_providers_by_service($service_id, $selected_date = NULL)
    {
        $this->load->model('providers_model');
        $available_providers = $this->providers_model->get_available_providers();
        $provider_list = array();

        if (!is_null($selected_date))
            $selected_date = new DateTime($selected_date);

        foreach ($available_providers as $provider)
        {
            foreach ($provider['services'] as $provider_service_id)
            {
                if ($provider_service_id === $service_id)
                {
                    if (is_null($selected_date)){
                        // Check if the provider is affected to the selected service.
                        $provider_list[] = $provider['id'];
                    }
                    else{
                        $working_plan = json_decode($provider['settings']['working_plan'], TRUE);
                        $availabilities = $this->_get_availabilities($working_plan, $selected_date);
                        // check availabilities
                        if( $availabilities ){
                            foreach ($availabilities as $availability){
                                if (isset($availability['services'])
                                    && in_array($service_id, $availability['services'])) // If availability includes the service
                                        $provider_list[] = $provider['id'];
                            }
                        }
                        $selected_date_working_plan = $working_plan[strtolower(date_format($selected_date, 'l'))];
                        // check wp
                        if ($selected_date_working_plan 
                            && array_key_exists('services',$selected_date_working_plan) 
                            && in_array($service_id, $selected_date_working_plan['services'])) 
                            $provider_list[] = $provider['id'];
                    }
                }
            }
        }

        return $provider_list;
    }

    /**
     * Calculate the available appointment hours.
     *
     * Calculate the available appointment hours for the given date. The empty spaces
     * are broken down to 15 min and if the service fit in each quarter then a new
     * available hour is added to the "$available_hours" array.
     *
     * @param array $empty_periods Contains the empty periods as generated by the "_get_provider_available_time_periods"
     * method.
     * @param string $selected_date The selected date to be search (format )
     * @param int $service_duration The service duration is required for the hour calculation.
     * @param bool $manage_mode (optional) Whether we are currently on manage mode (editing an existing appointment).
     * @param string $availabilities_type Optional ('flexible'), the service availabilities type.
     *
     * @return array Returns an array with the available hours for the appointment.
     */
    protected function _calculate_available_hours(
        array $empty_periods,
        $selected_date,
        $service_duration,
        $manage_mode = FALSE,
        $availabilities_type = 'flexible'
    ) {
        $this->load->model('settings_model');

        $available_hours = [];

        foreach ($empty_periods as $period)
        {
            $start_hour = new DateTime($selected_date . ' ' . $period['start']);
            $end_hour = new DateTime($selected_date . ' ' . $period['end']);
            $interval = $availabilities_type === AVAILABILITIES_TYPE_FIXED ? (int)$service_duration : 15;

            $current_hour = $start_hour;
            $diff = $current_hour->diff($end_hour);

            while (($diff->h * 60 + $diff->i) >= intval($service_duration))
            {
                $available_hours[] = $current_hour->format('H:i');
                $current_hour->add(new DateInterval('PT' . $interval . 'M'));
                $diff = $current_hour->diff($end_hour);
            }
        }

        return $available_hours;
    }

    // /**
    //  * Get multiple attendants hours.
    //  *
    //  * This method will add the extra appointment hours whenever a service accepts multiple attendants.
    //  *
    //  * @param string $selected_date The selected appointment date.
    //  * @param array $service Selected service data.
    //  * @param array $provider Selected provider data.
    //  *
    //  * @return array Returns the available hours array.
    //  */
    // protected function _get_multiple_attendants_hours(
    //     $selected_date,
    //     $service,
    //     $provider
    // ) {
    //     $this->load->model('appointments_model');
    //     $this->load->model('services_model');
    //     $this->load->model('providers_model');

    //     $unavailabilities = $this->appointments_model->get_batch([
    //         'is_unavailable' => TRUE,
    //         'DATE(start_datetime)' => $selected_date,
    //         'id_users_provider' => $provider['id']
    //     ]);

    //     $working_plan = json_decode($provider['settings']['working_plan'], TRUE);
    //     $working_day = strtolower(date('l', strtotime($selected_date)));
    //     $working_hours = $working_plan[$working_day];
        
    //     $hours = [];

    //     // If provider is not working that day return empty hours
    //     if( !isset($working_plan[$working_day]) )
    //         return $hours;

    //     // If provider is not available return empty hours
    //     if( $this->_check_workingplan_available($working_plan, new DateTime($selected_date)) === FALSE )
    //         return $hours;

    //     $periods = [
    //         [
    //             'start' => new DateTime($selected_date . ' ' . $working_hours['start']),
    //             'end' => new DateTime($selected_date . ' ' . $working_hours['end'])
    //         ]
    //     ];

    //     $periods = $this->remove_breaks($selected_date, $periods, $working_hours['breaks']);
    //     $periods = $this->remove_unavailabilities($periods, $unavailabilities);

    //     $interval_value = $service['availabilities_type'] == AVAILABILITIES_TYPE_FIXED ? $service['duration'] : '15';
    //     $interval = new DateInterval('PT' . (int)$interval_value . 'M');
    //     $duration = new DateInterval('PT' . (int)$service['duration'] . 'M');

    //     foreach ($periods as $period)
    //     {
    //         $slot_start = clone $period['start'];
    //         $slot_end = clone $slot_start;
    //         $slot_end->add($duration);

    //         while ($slot_end <= $period['end'])
    //         {
    //             // Check reserved attendants for this time slot and see if current attendants fit.
    //             $appointment_attendants_number = $this->appointments_model->get_attendants_number_for_period($slot_start,
    //                 $slot_end, $service['id']);

    //             if ($appointment_attendants_number < $service['attendants_number'])
    //             {
    //                 $hours[] = $slot_start->format('H:i');
    //             }

    //             $slot_start->add($interval);
    //             $slot_end->add($interval);
    //         }
    //     }

    //     return $hours;
    // }

    /**
     * Remove breaks from available time periods.
     *
     * @param string $selected_date Selected data (Y-m-d format).
     * @param array $periods Time periods of the current date.
     * @param array $breaks Breaks array for the current date.
     *
     * @return array Returns the available time periods without the breaks.
     */
    public function remove_breaks($selected_date, $periods, $breaks)
    {
        if ( ! $breaks)
        {
            return $periods;
        }

        foreach ($breaks as $break)
        {
            $break_start = new DateTime($selected_date . ' ' . $break['start']);
            $break_end = new DateTime($selected_date . ' ' . $break['end']);

            foreach ($periods as &$period)
            {
                $period_start = $period['start'];
                $period_end = $period['end'];

                if ($break_start <= $period_start && $break_end >= $period_start && $break_end <= $period_end)
                {
                    // left
                    $period['start'] = $break_end;
                    continue;
                }

                if ($break_start >= $period_start && $break_start <= $period_end && $break_end >= $period_start && $break_end <= $period_end)
                {
                    // middle
                    $period['end'] = $break_start;
                    $periods[] = [
                        'start' => $break_end,
                        'end' => $period_end
                    ];
                    continue;
                }

                if ($break_start >= $period_start && $break_start <= $period_end && $break_end >= $period_end)
                {
                    // right
                    $period['end'] = $break_start;
                    continue;
                }

                if ($break_start <= $period_start && $break_end >= $period_end)
                {
                    // break contains period
                    $period['start'] = $break_end;
                    continue;
                }
            }
        }

        return $periods;
    }

    /**
     * Remove the unavailabilities from the available time periods of the selected date.
     *
     * @param array $periods Available time periods.
     * @param array $unavailabilities Unavailabilities of the current date.
     *
     * @return array Returns the available time periods without the unavailabilities.
     */
    public function remove_unavailabilities($periods, $unavailabilities)
    {
        foreach ($unavailabilities as $unavailability)
        {
            $unavailability_start = new DateTime($unavailability['start_datetime']);
            $unavailability_end = new DateTime($unavailability['end_datetime']);

            foreach ($periods as &$period)
            {
                $period_start = $period['start'];
                $period_end = $period['end'];

                if ($unavailability_start <= $period_start && $unavailability_end >= $period_start && $unavailability_end <= $period_end)
                {
                    // left
                    $period['start'] = $unavailability_end;
                    continue;
                }

                if ($unavailability_start >= $period_start && $unavailability_start <= $period_end && $unavailability_end >= $period_start && $unavailability_end <= $period_end)
                {
                    // middle
                    $period['end'] = $unavailability_start;
                    $periods[] = [
                        'start' => $unavailability_end,
                        'end' => $period_end
                    ];
                    continue;
                }

                if ($unavailability_start >= $period_start && $unavailability_start <= $period_end && $unavailability_end >= $period_end)
                {
                    // right
                    $period['end'] = $unavailability_start;
                    continue;
                }

                if ($unavailability_start <= $period_start && $unavailability_end >= $period_end)
                {
                    // Unavaibility contains period
                    $period['start'] = $unavailability_end;
                    continue;
                }
            }
        }

        return $periods;
    }
}
