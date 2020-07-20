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

        $this->settings_model->set_setting('last_cron_date', date(DATE_ATOM));
    }
}
