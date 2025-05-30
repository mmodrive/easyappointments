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

/**
 * Backend Controller
 *
 * @package Controllers
 */
class Backend extends CI_Controller {
    /**
     * Class Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->load->library('session');

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
     * Display the main backend page.
     *
     * This method displays the main backend page. All users login permission can view this page which displays a
     * calendar with the events of the selected provider or service. If a user has more privileges he will see more
     * menus at the top of the page.
     *
     * @param string $appointment_hash Appointment edit dialog will appear when the page loads (default '').
     */
    public function index($appointment_hash = '')
    {
        $this->session->set_userdata('dest_url', site_url('backend'));

        if ( ! $this->_has_privileges(PRIV_APPOINTMENTS))
        {
            return;
        }

        $this->load->model('appointments_model');
        $this->load->model('providers_model');
        $this->load->model('services_model');
        $this->load->model('customers_model');
        $this->load->model('settings_model');
        $this->load->model('roles_model');
        $this->load->model('user_model');
        $this->load->model('secretaries_model');

        $view['base_url'] = $this->config->item('base_url');
        $view['user_display_name'] = $this->user_model->get_user_display_name($this->session->userdata('user_id'));
        $view['active_menu'] = PRIV_APPOINTMENTS;
        $view['book_advance_timeout'] = $this->settings_model->get_setting('book_advance_timeout');
        $view['date_format'] = $this->settings_model->get_setting('date_format');
        $view['time_format'] = $this->settings_model->get_setting('time_format');
        $view['company_name'] = $this->settings_model->get_setting('company_name');
        $view['available_providers'] = $this->providers_model->get_available_providers();
        $view['available_services'] = $this->services_model->get_available_services();
        $view['customers'] = $this->customers_model->get_batch();
        $user = $this->user_model->get_settings($this->session->userdata('user_id'));
        $view['calendar_view'] = $user['settings']['calendar_view'];
        $view['calendar_selections'] = json_decode($user['settings']['calendar_selections']);
        $this->set_user_data($view);

        if ($this->session->userdata('role_slug') === DB_SLUG_SECRETARY)
        {
            $secretary = $this->secretaries_model->get_row($this->session->userdata('user_id'));
            $view['secretary_providers'] = $secretary['providers'];
            $view['secretary_services'] = $secretary['services'];
        }
        else
        {
            $view['secretary_providers'] = [];
            $view['secretary_services'] = [];
        }

        $results = $this->appointments_model->get_batch(['hash' => $appointment_hash]);

        if ($appointment_hash !== '' && count($results) > 0)
        {
            $appointment = $results[0];
            $appointment['customer'] = $this->customers_model->get_row($appointment['id_users_customer']);
            $view['edit_appointment'] = $appointment; // This will display the appointment edit dialog on page load.
        }
        else
        {
            $view['edit_appointment'] = NULL;
        }

        $this->load->view('backend/header', $view);
        $this->load->view('backend/calendar', $view);
        $this->load->view('backend/soap_report', $view);
        $this->load->view('backend/footer', $view);
    }

    /**
     * Display the backend customers page.
     *
     * In this page the user can manage all the customer records of the system.
     */
    public function customers()
    {
        $this->session->set_userdata('dest_url', site_url('backend/customers'));

        if ( ! $this->_has_privileges(PRIV_CUSTOMERS))
        {
            return;
        }

        $this->load->model('providers_model');
        $this->load->model('customers_model');
        $this->load->model('secretaries_model');
        $this->load->model('services_model');
        $this->load->model('settings_model');
        $this->load->model('user_model');

        $view['base_url'] = $this->config->item('base_url');
        $view['user_display_name'] = $this->user_model->get_user_display_name($this->session->userdata('user_id'));
        $view['active_menu'] = PRIV_CUSTOMERS;
        $view['company_name'] = $this->settings_model->get_setting('company_name');
        $view['date_format'] = $this->settings_model->get_setting('date_format');
        $view['time_format'] = $this->settings_model->get_setting('time_format');
        $view['customers'] = $this->customers_model->get_batch();
        $view['available_providers'] = $this->providers_model->get_available_providers();
        $view['available_services'] = $this->services_model->get_available_services();

        if ($this->session->userdata('role_slug') === DB_SLUG_SECRETARY)
        {
            $secretary = $this->secretaries_model->get_row($this->session->userdata('user_id'));
            $view['secretary_providers'] = $secretary['providers'];
            $view['secretary_services'] = $secretary['services'];
        }
        else
        {
            $view['secretary_providers'] = [];
            $view['secretary_services'] = [];
        }

        $this->set_user_data($view);

        $this->load->view('backend/header', $view);
        $this->load->view('backend/customers', $view);
        $this->load->view('backend/soap_report', $view);
        $this->load->view('backend/footer', $view);
    }

    /**
     * Displays the backend services page.
     *
     * Here the admin user will be able to organize and create the services that the user will be able to book
     * appointments in frontend.
     *
     * NOTICE: The services that each provider is able to service is managed from the backend services page.
     */
    public function services()
    {
        $this->session->set_userdata('dest_url', site_url('backend/services'));

        if ( ! $this->_has_privileges(PRIV_SERVICES))
        {
            return;
        }

        $this->load->model('customers_model');
        $this->load->model('services_model');
        $this->load->model('settings_model');
        $this->load->model('user_model');
        $this->load->model('providers_model');

        $view['base_url'] = $this->config->item('base_url');
        $view['user_display_name'] = $this->user_model->get_user_display_name($this->session->userdata('user_id'));
        $view['active_menu'] = PRIV_SERVICES;
        $view['company_name'] = $this->settings_model->get_setting('company_name');
        $view['date_format'] = $this->settings_model->get_setting('date_format');
        $view['time_format'] = $this->settings_model->get_setting('time_format');
        $view['services'] = $this->services_model->get_batch();
        $view['categories'] = $this->services_model->get_all_categories();
        $view['providers'] = $this->providers_model->get_available_providers();
        $this->set_user_data($view);

        $this->load->view('backend/header', $view);
        $this->load->view('backend/services', $view);
        $this->load->view('backend/footer', $view);
    }

    /**
     * Display the backend users page.
     *
     * In this page the admin user will be able to manage the system users. By this, we mean the provider, secretary and
     * admin users. This is also the page where the admin defines which service can each provider provide.
     */
    public function users()
    {
        $this->session->set_userdata('dest_url', site_url('backend/users'));

        if ( ! $this->_has_privileges(PRIV_USERS))
        {
            return;
        }

        $this->load->model('providers_model');
        $this->load->model('secretaries_model');
        $this->load->model('admins_model');
        $this->load->model('services_model');
        $this->load->model('settings_model');
        $this->load->model('user_model');

        $view['base_url'] = $this->config->item('base_url');
        $view['user_display_name'] = $this->user_model->get_user_display_name($this->session->userdata('user_id'));
        $view['active_menu'] = PRIV_USERS;
        $view['company_name'] = $this->settings_model->get_setting('company_name');
        $view['date_format'] = $this->settings_model->get_setting('date_format');
        $view['time_format'] = $this->settings_model->get_setting('time_format');
        $view['admins'] = $this->admins_model->get_batch();
        $view['providers'] = $this->providers_model->get_batch();
        $view['secretaries'] = $this->secretaries_model->get_batch();
        $view['services'] = $this->services_model->get_batch();
        $view['working_plan'] = $this->settings_model->get_setting('company_working_plan');
        $this->set_user_data($view);

        $this->load->view('backend/header', $view);
        $this->load->view('backend/users', $view);
        $this->load->view('backend/footer', $view);
    }

    /**
     * Exports CSV file of customer data.
     *
     */
    public function export_user_csv()
    {
        $this->session->set_userdata('dest_url', site_url('backend/more'));

        if ( ! $this->_has_privileges(PRIV_USERS))
        {
            return;
        }

        $this->load->model('customers_model');
        $this->load->model('services_model');
        $this->load->model('providers_model');
        $this->load->model('settings_model');
        $this->load->model('user_model');
        $this->load->model('pets_model');
        $this->load->helper('form');

        $view['available_services'] = $this->services_model->get_available_services();
        $view['available_providers'] = $this->providers_model->get_available_providers();
        $view['company_name'] = $this->settings_model->get_setting('company_name');
        $view['base_url'] = $this->config->item('base_url');
        $view['user_display_name'] = $this->user_model->get_user_display_name($this->session->userdata('user_id'));
        $view['active_menu'] = MENU_MORE;
        $view['date_format'] = $this->settings_model->get_setting('date_format');
        $view['time_format'] = $this->settings_model->get_setting('time_format');

        $dateFormat = '';
        switch ($view['date_format']) {
            case 'DMY':
                $dateFormat = 'd/m/Y';
                break;
            case 'MDY':
                $dateFormat = 'm/d/Y';
                break;
            case 'YMD':
                $dateFormat = 'Y/m/d';
                break;
        }
        $view['php_date_format'] = $dateFormat;

        $time_format = '';
        switch ($view['time_format']) {
            case TIME_FORMAT_REGULAR:
                $time_format = 'g:i A';
                break;
            case TIME_FORMAT_MILITARY:
                $time_format = 'H:i';
                break;
        }
        $view['php_time_format'] = $time_format;

        $view['service'] = $service = $this->input->post('service');
        $view['provider'] = $provider = $this->input->post('provider');

        $this->db
            ->select('last_name, first_name, email, mobile_number, phone_number, address, city, state, zip_code, notes')
            ->from('ea_users AS customer')
            ->where("marketing_subscribe = 1 AND id_roles = 3");
        $customers = $this->db->order_by('last_name, first_name')
            ->get()->result_array();

        header("Content-type: application/csv");
        header("Content-Disposition: attachment; filename=\"Customers".date('dmY').".csv\"");
        header("Pragma: no-cache");
        header("Expires: 0");

        $handle = fopen('php://output', 'w');

        fputcsv($handle, ['last_name', 'first_name', 'email', 'mobile_number', 'phone_number', 'address', 'city', 'state', 'zip_code', 'notes']);
        foreach ($customers as $customer) {
            fputcsv($handle, $customer);
        }
        fclose($handle);
        exit;
    }

    /**
     * Displays the backend print appointments page.
     *
     * Here the admin user will be able to filer and list appointments.
     *
     */
    public function print_appointments()
    {
        $this->session->set_userdata('dest_url', site_url('backend/print_appointments'));

        if ( ! $this->_has_privileges(PRIV_PRINT_APPOINTMENTS))
        {
            return;
        }

        $this->load->model('customers_model');
        $this->load->model('services_model');
        $this->load->model('providers_model');
        $this->load->model('settings_model');
        $this->load->model('user_model');
        $this->load->model('pets_model');
        $this->load->helper('form');

        $view['available_services'] = $this->services_model->get_available_services();
        $view['available_providers'] = $this->providers_model->get_available_providers();
        $view['company_name'] = $this->settings_model->get_setting('company_name');
        $view['base_url'] = $this->config->item('base_url');
        $view['user_display_name'] = $this->user_model->get_user_display_name($this->session->userdata('user_id'));
        $view['active_menu'] = MENU_MORE;
        $view['date_format'] = $this->settings_model->get_setting('date_format');
        $view['time_format'] = $this->settings_model->get_setting('time_format');

        $dateFormat = '';
        switch ($view['date_format']) {
            case 'DMY':
                $dateFormat = 'd/m/Y';
                break;
            case 'MDY':
                $dateFormat = 'm/d/Y';
                break;
            case 'YMD':
                $dateFormat = 'Y/m/d';
                break;
        }
        $view['php_date_format'] = $dateFormat;

        $time_format = '';
        switch ($view['time_format']) {
            case TIME_FORMAT_REGULAR:
                $time_format = 'g:i A';
                break;
            case TIME_FORMAT_MILITARY:
                $time_format = 'H:i';
                break;
        }
        $view['php_time_format'] = $time_format;

        $view['post_at'] = $this->input->post('post_at') ?? date($dateFormat, strtotime("-7 days"));
        $view['post_at_to_date'] = $this->input->post('post_at_to_date') ?? date($dateFormat, time());

        $queryCondition = "";
        $appointments = [];
        if($this->input->post('search')) {
            $post_at = DateTime::createFromFormat($dateFormat, $view['post_at']);
            $post_at_to_date = DateTime::createFromFormat($dateFormat, $view['post_at_to_date']);

            $view['service'] = $service = $this->input->post('service');
            $view['provider'] = $provider = $this->input->post('provider');

            $this->db
                ->select('CONCAT(customer.first_name, " ", customer.last_name) customer_name, CONCAT(provider.first_name, " ", provider.last_name) provider_name, provider_settings.working_plan provider_working_plan, service.name service_name, app.start_datetime, app.end_datetime, app.is_cancelled, customer.phone_number, pet.id pet_id, disc.app_discount ')
                ->from('ea_appointments AS app')
                ->join('ea_users AS customer', 'app.id_users_customer=customer.id', 'inner')
                ->join('ea_users AS provider', 'app.id_users_provider=provider.id', 'inner')
                ->join('ea_user_settings AS provider_settings', 'provider.id=provider_settings.id_users', 'inner')
                ->join('ea_services AS service', 'app.id_services=service.id', 'inner')
                ->join('ea_pets AS pet', 'app.id_pets=pet.id', 'left')
                ->join('ea_appointments_discount AS disc', 'app.id=disc.id_appointment', 'left')
                ->where("start_datetime BETWEEN '" . date_format($post_at, "Y-m-d") . " 00:00:00' AND '" . date_format($post_at_to_date, "Y-m-d") . " 23:59:59'");
            if ($service && $service !== "all")
                $this->db->where('app.id_services', $service);
            if ($provider && $provider !== "all")
                $this->db->where('app.id_users_provider', $provider);
            $appointments = $this->db->order_by('service.name')
                ->order_by('provider.id')
                ->order_by('app.start_datetime')
                ->get()->result_array();

            $pet_ids = array_values(
                array_filter(
                    array_unique(
                        array_map(function($value){ return $value['pet_id']; },
                            $appointments)
                    ),
                    function($value){ return isset($value); }
                )
            );

            if(!empty($pet_ids)){
                $pets = [];
                $pets_raw = $this->db
                    ->where_in('id', $pet_ids )
                    ->get('ea_pets')->result_array();
                foreach ($pets_raw as $pet) 
                    $pets[$pet['id']] = $this->pets_model->compute_details($pet);

                foreach ($appointments as &$appointment)
                    if (isset($appointment['pet_id'])) 
                        $appointment['pet_title'] = $pets[$appointment['pet_id']]['title'];
            }
        }
        $view['appointments'] = $appointments;

        $this->set_user_data($view);

        $this->load->view('backend/header', $view);
        $this->load->view('backend/print_appointments', $view);
        $this->load->view('backend/footer', $view);
    }

    /**
     * Display the more options screen.
     *
     */
    public function more()
    {
        $this->session->set_userdata('dest_url', site_url('backend/more'));
        if ( ! $this->_has_privileges(PRIV_APPOINTMENTS, FALSE)
            && ! $this->_has_privileges(PRIV_APPOINTMENTS))
        {
            return;
        }

        $this->load->model('settings_model');
        $this->load->model('user_model');

        $this->load->library('session');
        $user_id = $this->session->userdata('user_id');

        $view['base_url'] = $this->config->item('base_url');
        $view['user_display_name'] = $this->user_model->get_user_display_name($user_id);
        $view['user_phone_number'] = $this->user_model->get_settings($this->session->userdata('user_id'))['phone_number'] ?? '';
        $view['active_menu'] = MENU_MORE;
        $view['company_name'] = $this->settings_model->get_setting('company_name');
        $view['date_format'] = $this->settings_model->get_setting('date_format');
        $view['time_format'] = $this->settings_model->get_setting('time_format');
        $view['role_slug'] = $this->session->userdata('role_slug');
        $this->set_user_data($view);

        $this->load->view('backend/header', $view);
        $this->load->view('backend/more', $view);
        $this->load->view('backend/footer', $view);
    }

    /**
     * Display the user/system settings.
     *
     * This page will display the user settings (name, password etc). If current user is an administrator, then he will
     * be able to make change to the current Easy!Appointment installation (core settings like company name, book
     * timeout etc).
     */
    public function settings()
    {
        $this->session->set_userdata('dest_url', site_url('backend/settings'));
        if ( ! $this->_has_privileges(PRIV_SYSTEM_SETTINGS, FALSE)
            && ! $this->_has_privileges(PRIV_USER_SETTINGS))
        {
            return;
        }

        $this->load->model('settings_model');
        $this->load->model('user_model');

        $this->load->library('session');
        $user_id = $this->session->userdata('user_id');

        $view['base_url'] = $this->config->item('base_url');
        $view['user_display_name'] = $this->user_model->get_user_display_name($user_id);
        $view['user_phone_number'] = $this->user_model->get_settings($this->session->userdata('user_id'))['phone_number'] ?? '';
        $view['active_menu'] = PRIV_SYSTEM_SETTINGS;
        $view['company_name'] = $this->settings_model->get_setting('company_name');
        $view['date_format'] = $this->settings_model->get_setting('date_format');
        $view['time_format'] = $this->settings_model->get_setting('time_format');
        $view['role_slug'] = $this->session->userdata('role_slug');
        $view['system_settings'] = $this->settings_model->get_settings();
        $view['user_settings'] = $this->user_model->get_settings($user_id);
        $cron_last_run = date_create_from_format(DATE_ATOM, $this->settings_model->find_setting('last_cron_date'));
        $view['cron_active'] = $cron_last_run ? ((time() - $cron_last_run->getTimestamp()) / 60) <= 2 : FALSE;
        $this->set_user_data($view);

        $this->load->view('backend/header', $view);
        $this->load->view('backend/settings', $view);
        $this->load->view('backend/footer', $view);
    }

    /**
     * Check whether current user is logged in and has the required privileges to view a page.
     *
     * The backend page requires different privileges from the users to display pages. Not all pages are available to
     * all users. For example secretaries should not be able to edit the system users.
     *
     * @see Constant definition in application/config/constants.php.
     *
     * @param string $page This argument must match the roles field names of each section (eg "appointments", "users"
     * ...).
     * @param bool $redirect If the user has not the required privileges (either not logged in or insufficient role
     * privileges) then the user will be redirected to another page. Set this argument to FALSE when using ajax (default
     * true).
     *
     * @return bool Returns whether the user has the required privileges to view the page or not. If the user is not
     * logged in then he will be prompted to log in. If he hasn't the required privileges then an info message will be
     * displayed.
     */
    protected function _has_privileges($page, $redirect = TRUE)
    {
        // Check if user is logged in.
        $user_id = $this->session->userdata('user_id');
        if ($user_id == FALSE)
        { // User not logged in, display the login view.
            if ($redirect)
            {
                header('Location: ' . site_url('user/login'));
            }
            return FALSE;
        }

        // Check if the user has the required privileges for viewing the selected page.
        $role_slug = $this->session->userdata('role_slug');
        $role_priv = $this->db->get_where('ea_roles', ['slug' => $role_slug])->row_array();
        if ($role_priv[$page] < PRIV_VIEW)
        { // User does not have the permission to view the page.
            if ($redirect)
            {
                header('Location: ' . site_url('user/no_privileges'));
            }
            return FALSE;
        }

        return TRUE;
    }

    /**
     * This method will update the installation to the latest available version in the server.
     *
     * IMPORTANT: The code files must exist in the server, this method will not fetch any new files but will update
     * the database schema.
     *
     * This method can be used either by loading the page in the browser or by an ajax request. But it will answer with
     * JSON encoded data.
     */
    public function update()
    {
        try
        {
            if ( ! $this->_has_privileges(PRIV_SYSTEM_SETTINGS, TRUE))
            {
                throw new Exception('You do not have the required privileges for this task!');
            }

            $this->load->library('migration');

            if ( ! $this->migration->current())
            {
                throw new Exception($this->migration->error_string());
            }

            $view = ['success' => TRUE];
        }
        catch (Exception $exc)
        {
            $view = ['success' => FALSE, 'exception' => $exc->getMessage()];
        }

        $this->load->view('general/update', $view);
    }

    public function GetTemplate($template_name)
    {
        $this->load->library('session');
        $this->load->model('roles_model');

        if ($this->session->userdata('role_slug'))
            $privileges = $this->roles_model->get_privileges($this->session->userdata('role_slug'));

        if ($privileges[PRIV_USERS]['view'] == FALSE)
        {
            throw new Exception('You do not have the required privileges for this task.');
        }

        $this->load->model('settings_model');
        $this->load->model('appointments_model');

        $appointment = $this->appointments_model->get_sample_appointment($this->input->get('sid'));

        if($appointment){

            $html = $this->settings_model->getNotification(
                $template_name,
                $appointment->appointment,
                $appointment->provider,
                $appointment->service,
                $appointment->customer,
                $appointment->pet,
                TRUE,
                TRUE,
                FALSE)->body;
            
            if (strpos($template_name, 'sms_') === 0)
                $html = '<pre>'.$html.'</pre>';

            echo $html;
        }
        else
            echo 'No Appointments Found To Demonstrate!';
    }

    /**
     * Set the user data in order to be available at the view and js code.
     *
     * @param array $view Contains the view data.
     */
    protected function set_user_data(&$view)
    {
        $this->load->model('roles_model');

        // Get privileges
        $view['user_id'] = $this->session->userdata('user_id');
        $view['user_email'] = $this->session->userdata('user_email');
        $view['role_slug'] = $this->session->userdata('role_slug');
        $view['privileges'] = $this->roles_model->get_privileges($this->session->userdata('role_slug'));
    }
}
