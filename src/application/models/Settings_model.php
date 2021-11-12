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
 * Settings Model
 *
 * @package Models
 */
class Settings_Model extends CI_Model {
    /**
     * Get setting value from database.
     *
     * This method returns a system setting from the database.
     *
     * @param string $name The database setting name.
     *
     * @return string Returns the database value for the selected setting.
     *
     * @throws Exception If the $name argument is invalid.
     * @throws Exception If the requested $name setting does not exist in the database.
     */
    public function get_setting($name)
    {
        if ( ! is_string($name))
        { // Check argument type.
            throw new Exception('$name argument is not a string: ' . $name);
        }

        if ($this->db->get_where('ea_settings', ['name' => $name])->num_rows() == 0)
        { // Check if setting exists in db.
            throw new Exception('$name setting does not exist in database: ' . $name);
        }

        $query = $this->db->get_where('ea_settings', ['name' => $name]);
        $setting = $query->num_rows() > 0 ? $query->row() : '';
        return $setting->value;
    }

    /**
     * Get setting value from database.
     *
     * This method returns a system setting from the database.
     *
     * @param string $name The database setting name.
     *
     * @return string Returns the database value for the selected setting or NULL if settings does not exist.
     *
     * @throws Exception If the $name argument is invalid.
     * @throws Exception If the requested $name setting does not exist in the database.
     */
    public function find_setting($name)
    {
        if ( ! is_string($name))
        { // Check argument type.
            throw new Exception('$name argument is not a string: ' . $name);
        }

        if ($this->db->get_where('ea_settings', ['name' => $name])->num_rows() == 0)
            return NULL;

        $query = $this->db->get_where('ea_settings', ['name' => $name]);
        return $query->num_rows() > 0 ? $query->row()->value : NULL;
    }

    /**
     * This method sets the value for a specific setting on the database.
     *
     * If the setting doesn't exist, it is going to be created, otherwise updated.
     *
     * @param string $name The setting name.
     * @param string $value The setting value.
     *
     * @return int Returns the setting database id.
     *
     * @throws Exception If $name argument is invalid.
     * @throws Exception If the save operation fails.
     */
    public function set_setting($name, $value)
    {
        if ( ! is_string($name))
        {
            throw new Exception('$name argument is not a string: ' . $name);
        }

        $query = $this->db->get_where('ea_settings', ['name' => $name]);
        if ($query->num_rows() > 0)
        {
            // Update setting
            if ( ! $this->db->update('ea_settings', ['value' => $value], ['name' => $name]))
            {
                throw new Exception('Could not update database setting.');
            }
            $setting_id = (int)$this->db->get_where('ea_settings', ['name' => $name])->row()->id;
        }
        else
        {
            // Insert setting
            $insert_data = [
                'name' => $name,
                'value' => $value
            ];
            if ( ! $this->db->insert('ea_settings', $insert_data))
            {
                throw new Exception('Could not insert database setting');
            }
            $setting_id = (int)$this->db->insert_id();
        }

        return $setting_id;
    }

    /**
     * Remove a setting from the database.
     *
     * @param string $name The setting name to be removed.
     *
     * @return bool Returns the delete operation result.
     *
     * @throws Exception If the $name argument is invalid.
     */
    public function remove_setting($name)
    {
        if ( ! is_string($name))
        {
            throw new Exception('$name is not a string: ' . $name);
        }

        if ($this->db->get_where('ea_settings', ['name' => $name])->num_rows() == 0)
        {
            return FALSE; // There is no such setting.
        }

        return $this->db->delete('ea_settings', ['name' => $name]);
    }

    /**
     * Saves all the system settings into the database.
     *
     * This method is useful when trying to save all the system settings at once instead of
     * saving them one by one.
     *
     * @param array $settings Contains all the system settings.
     *
     * @return bool Returns the save operation result.
     *
     * @throws Exception When the update operation won't work for a specific setting.
     */
    public function save_settings($settings)
    {
        if ( ! is_array($settings))
        {
            throw new Exception('$settings argument is invalid: ' . print_r($settings, TRUE));
        }

        foreach ($settings as $setting)
        {
            $this->db->where('name', $setting['name']);
            if ( ! $this->db->update('ea_settings', ['value' => $setting['value']]))
            {
                throw new Exception('Could not save setting (' . $setting['name']
                    . ' - ' . $setting['value'] . ')');
            }
        }

        return TRUE;
    }

    /**
     * Returns all the system settings at once.
     *
     * @return array Array of all the system settings stored in the 'ea_settings' table.
     */
    public function get_settings()
    {
        return $this->db->get('ea_settings')->result_array();
    }

    public function getNotification(
        string $template_name,
        $appointment,
        $provider,
        $service,
        $customer,
        $pet,
        bool $is_customer_notification,
        bool $displayHeader = FALSE,
        bool $replaceFirstApp = TRUE
    ) {
        if ($template_name == 'email_appointment_new' || $template_name == 'email_first_appointment')
        {
            if ($is_customer_notification) {
                // If new appointment template was requested but this is a first appointment
                if($replaceFirstApp && $template_name == 'email_appointment_new') {
                    if($this->db->get_where('ea_appointments', ['id_users_customer' => $customer['id'], 'id_services' => $service['id']])->num_rows() == 1)
                        $template_name = 'email_first_appointment';
                }
                // If first appointment template was requested but there is no service template
                if($replaceFirstApp && $template_name == 'email_first_appointment') {
                    if(!($service['email_first_appointment_subject'] && $service['email_first_appointment_subject']))
                        $template_name = 'email_appointment_new';
                }
                $title = new Text(($template_name == 'email_first_appointment' ? $service['email_first_appointment_subject'] : null) ?? $this->get_setting('email_appointment_new_subject') ?? $this->lang->line('appointment_booked'));
                $message = new Text($this->lang->line('thank_you_for_appointment'));
            }
            else{
                $title = new Text($this->lang->line('appointment_added_to_your_plan'));
                $message = new Text($this->lang->line('appointment_link_description'));
            }

        }
        elseif ($template_name == 'email_appointment_change')
        {
            if ($is_customer_notification) {
                $title = new Text($this->get_setting('email_appointment_change_subject') ?? $this->lang->line('appointment_changes_saved'));
                $message = new Text('');
            }
            else{
                $title = new Text($this->lang->line('appointment_details_changed'));
                $message = new Text('');
            }
        }
        elseif ($template_name == 'email_customer_registration')
        {
            $title = new Text($this->get_setting('email_customer_registration_subject') ?? $this->lang->line('customer_registered'));
            $message = new Text($this->lang->line('thank_you_for_registering'));
        }
        else
        {
            $title = new Text('');
            $message = new Text('');
        }

        if (isset($appointment['hash'])) {
            if ($is_customer_notification) {
                $link = new Url(site_url('appointments/index/' . $appointment['hash']));
            }
            else{
                $link = new Url(site_url('backend/index/' . $appointment['hash']));
            }
        }
        else {
            $link = new Url();
        }

        $company = [
            'company_name' => $this->get_setting('company_name'),
            'company_link' => $this->get_setting('company_link'),
            'company_email' => $this->get_setting('company_email'),
            'date_format' => $this->get_setting('date_format'),
            'time_format' => $this->get_setting('time_format')
        ];

        $body = $this->_replaceTemplateBody(
            $template_name,
            $company,
            $appointment,
            $provider,
            $service,
            $customer,
            $pet,
            $title,
            $message,
            $link
            );

        if (strpos($template_name, 'email_') === 0)
            $body = "<html><head><title>{$title->get()}</title></head><bodystyle=\"font: 13px arial, helvetica, tahoma;\">" .
                ($displayHeader ? "From: {$company['company_name']} &lt;{$company['company_email']}&gt;<br/>Subject: {$title->get()}<hr>" : '') .
                $body . "</body></html>";
        
        return (object)[
            "from" => $company['company_email'],
            "fromName" => $company['company_name'],
            "subject" => $title->get(),
            "body" => $body,
            ];
    }

    public function _replaceTemplateBody(
        string $template_name,
        $company,
        $appointment,
        $provider,
        $service,
        $customer,
        $pet,
        Text $title,
        Text $message,
        Url $appointmentLink
    ) {
        switch ($company['date_format'])
        {
            case 'DMY':
                $date_format = 'd/m/Y';
                break;
            case 'MDY':
                $date_format = 'm/d/Y';
                break;
            case 'YMD':
                $date_format = 'Y/m/d';
                break;
            default:
                throw new \Exception('Invalid date_format value: ' . $company['date_format']);
        }

        switch ($company['time_format'])
        {
            case 'military':
                $timeFormat = 'H:i';
                break;
            case 'regular':
                $timeFormat = 'g:i A';
                break;
            default:
                throw new \Exception('Invalid time_format value: ' . $company['time_format']);
        }

        // Prepare template replace array.
        $replaceArray = [
            '$email_title' => $title->get(),
            '$email_message' => $message->get(),
            '$appointment_service' => $service['name'],
            '$appointment_provider' => $provider['first_name'] . ' ' . $provider['last_name'],
            '$appointment_start_date' => date($date_format . ' ' . $timeFormat, strtotime($appointment['start_datetime'])),
            '$appointment_start_time' => date($timeFormat, strtotime($appointment['start_datetime'])),
            '$appointment_end_date' => date($date_format . ' ' . $timeFormat, strtotime($appointment['end_datetime'])),
            '$appointment_end_time' => date($timeFormat, strtotime($appointment['end_datetime'])),
            '$appointment_link' => $appointmentLink->get(),
            '$company_link' => $company['company_link'],
            '$company_name' => $company['company_name'],
            '$customer_name' => $customer['first_name'] . ' ' . $customer['last_name'],
            '$provider_name' => $provider['first_name'] . ' ' . $provider['last_name'],
            '$customer_email' => $customer['email'],
            '$customer_phone' => $customer['phone_number'],
            '$customer_address' => $customer['address'],

            // Translations
            'Appointment Details' => $this->lang->line('appointment_details_title'),
            'Service' => $this->lang->line('service'),
            'Provider' => $this->lang->line('provider'),
            'Start' => $this->lang->line('start'),
            'End' => $this->lang->line('end'),
            'Customer Details' => $this->lang->line('customer_details_title'),
            'Name' => $this->lang->line('name'),
            'Email' => $this->lang->line('email'),
            'Phone' => $this->lang->line('phone'),
            'Address' => $this->lang->line('address'),
            'Appointment Link' => $this->lang->line('appointment_link_title')
        ];

        if( isset($pet) )
            foreach ($pet as $key => $value) {
                if( $key === 'dob' )
                    $replaceArray['$pet_'.$key] = date($date_format, strtotime($value));
                elseif( $value === null || is_scalar($value) || (is_object($value) && method_exists($value, '__toString')) )
                    $replaceArray['$pet_'.$key] = $value;
            }

        $body = $template_name == 'email_first_appointment' ? $service['email_first_appointment'] : $this->get_setting($template_name);
        $body = $this->_replaceTemplateVariables($replaceArray, $body);

        return $body;
    }


    /**
     * Replace the email template variables.
     *
     * This method finds and replaces the html variables of an email template. It is used to
     * generate dynamic HTML emails that are send as notifications to the system users.
     *
     * @param array $replaceArray Array that contains the variables to be replaced.
     * @param string $templateHtml The email template HTML.
     *
     * @return string Returns the new email html that contain the variables of the $replaceArray.
     */
    protected function _replaceTemplateVariables(array $replaceArray, $templateHtml)
    {
        foreach ($replaceArray as $name => $value)
        {
            $templateHtml = str_replace($name, $value, $templateHtml);
        }

        return $templateHtml;
    }
}
