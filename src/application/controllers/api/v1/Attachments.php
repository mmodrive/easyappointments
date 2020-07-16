<?php defined('BASEPATH') OR exit('No direct script access allowed');

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


use \EA\Engine\Api\V1\Response;
use \EA\Engine\Api\V1\Request;
use \EA\Engine\Types\NonEmptyText;

/**
 * Attachments Controller
 *
 * @package Controllers
 * @subpackage API
 */
class Attachments extends CI_Controller {
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
        $this->load->model('roles_model');

        if ($this->session->userdata('role_slug'))
        {
            $this->privileges = $this->roles_model->get_privileges($this->session->userdata('role_slug'));
        }
    }

    public function open_attachment($attachment_id)
    {
        $this->load->model('attachments_model');

        if ($this->privileges[PRIV_APPOINTMENTS]['view'] == FALSE)
        {
            throw new Exception('You do not have the required privileges for this task.');
        }

        $attachment = $this->attachments_model->get_row($attachment_id);

        $target_path = FCPATH.'storage/uploads/'.$attachment['storage_name'];
        
        $fp = fopen($target_path, 'rb');

        // send the right headers
        header("Content-Type: ".$attachment['type']);
        header('Content-Disposition: inline; filename="' . $attachment['filename'] . '"');
        header("Content-Length: " . filesize($target_path));

        // dump the file and stop the script
        fpassthru($fp);
        exit;
    }

    public function delete_attachment($attachment_id)
    {
        $this->load->model('attachments_model');

        if ($this->privileges[PRIV_APPOINTMENTS]['view'] == FALSE)
        {
            throw new Exception('You do not have the required privileges for this task.');
        }

        $attachment = $this->attachments_model->get_row($attachment_id);
        $target_path = FCPATH.'storage/uploads/'.$attachment['storage_name'];

        $this->attachments_model->delete($attachment_id);

        unlink($target_path);

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode(AJAX_SUCCESS));
    }
}
