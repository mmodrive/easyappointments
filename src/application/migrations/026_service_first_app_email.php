<?php defined('BASEPATH') OR exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Open Source Web Scheduler
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) 2013 - 2018, Alex Tselegidis
 * @license     http://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        http://easyappointments.org
 * @since       v1.3.2
 * ---------------------------------------------------------------------------- */

class Migration_Service_first_app_email extends CI_Migration {
    public function up()
    {
        $fields = [
            'email_first_appointment_subject' => [
                'type' => 'VARCHAR',
                'constraint' => '512'
            ],
            'email_first_appointment' => [
                'type' => 'TEXT'
            ],
        ];

        $this->dbforge->add_column('ea_services', $fields);
    }
    
    public function down()
    {
        $this->dbforge->drop_column('ea_services', 'email_first_appointment_subject');
        $this->dbforge->drop_column('ea_services', 'email_first_appointment');
    }
}
