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

class Migration_Provider_services_workplan extends CI_Migration {
    public function up()
    {
        $this->dbforge->drop_column('ea_services', 'attendants_number');
    }

    public function down()
    {
        $fields = [
            'attendants_number' => [
                'type' => 'INT',
                'constraint' => '11',
                'default' => '1',
                'after' => 'availabilities_type'
            ]
        ];

        $this->dbforge->add_column('ea_services', $fields);

        $this->db->update('ea_services', ['attendants_number' => '1']);
    }
}
