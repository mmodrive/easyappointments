<?php defined('BASEPATH') OR exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Open Source Web Scheduler
 *
 * @package     EasyAppointments
 * @author      A.Dmitriev <mmodrive@gmail.com>
 * @copyright   Copyright (c) 2020 - now, Alexey Dmitriev
 * @license     http://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        http://easyappointments.org
 * @since       v1.0.0
 * ---------------------------------------------------------------------------- */

use \EA\Engine\Types\NonEmptyText;

/**
 * Cron Controller
 *
 * This controller handles the Cron jobs.
 *
 * @package Controllers
 */
class Cron extends CI_Controller {
    /**
     * Class Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->load->library('session');
    }

        /**
     * This function is used to update the age of users automatically
     * This function is called by cron job once in a day at midnight 00:00
     */
    public function run()
    {            
        // is_cli_request() is provided by default input library of codeigniter
        if(!$this->input->is_cli_request() && !$this->session->userdata('role_slug'))
            return;

        $this->load->model('settings_model');

        $this->sendReminders();

        $this->settings_model->set_setting('last_cron_date', date(DATE_ATOM));
    }

    protected function sendReminders()
    {
        $this->load->model('settings_model');

        $send_customer = filter_var($this->settings_model->get_setting('customer_notifications'),
            FILTER_VALIDATE_BOOLEAN);

        if ($send_customer === TRUE)
        {
            $this->load->helper('date_helper');
            $this->load->model('appointments_model');

            $from = date_timestamp_set(new DateTime(), strtotime("+0 hours", now()));
            $to = date_timestamp_set(new DateTime(), strtotime("+24 hours", now()));
            $appointments = $this->db
                ->select('a.id')
                ->from('ea_appointments AS a')
                ->join('ea_users AS c', 'a.id_users_customer=c.id', 'inner')
                ->where('c.phone_number IS NOT NULL')
                ->where('start_datetime >=', $from->format('Y-m-d H:i:s'))
                ->where('start_datetime <', $to->format('Y-m-d H:i:s'))
                ->get()->result();

            $config = [
                'sms_sender' => $this->settings_model->get_setting('sms_sender'),
                'sms_username' => $this->settings_model->get_setting('sms_username'),
                'sms_password' => $this->settings_model->get_setting('sms_password'),
                ];

            $sms = new \EA\Engine\Notifications\SMS($this, $config);

            $notification_type = 'sms_reminder';

            foreach ($appointments as $appointment_id) {
                try{
                    $appointment = $this->appointments_model->get_appointment($appointment_id->id);

                    $notification = $this->settings_model->getNotification(
                        $notification_type,
                        $appointment->appointment,
                        $appointment->provider,
                        $appointment->service,
                        $appointment->customer,
                        $appointment->pet,
                        TRUE);
                    
                    $ref = $sms->sendText($notification, 
                        new NonEmptyText($appointment->customer['phone_number']), 
                        //TRUE);
                        ENVIRONMENT === 'development');

                    $this->db->where(['id' => $appointment->appointment['id']]);
                    $this->db->update('ea_appointments', ['sms_notification' => $ref]);
                }
                catch (Exception $exc)
                {
                    log_message('error', $exc->getMessage());
                    log_message('error', $exc->getTraceAsString());
                    $this->db->where(['id' => $appointment->appointment['id']]);
                    $this->db->update('ea_appointments', ['sms_notification' => 'FAILED']);
                }
            }
        }
    }
}
