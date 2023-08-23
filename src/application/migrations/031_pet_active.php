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

class Migration_Pet_active extends CI_Migration {

    public function up()
    {
        $fields = [
            'active' => [
                'type' => 'BOOLEAN',
                'null' => FALSE,
                'default' => TRUE
            ],
        ];

        $this->dbforge->add_column('ea_pets', $fields);
    }

    public function down()
    {
        $fields = ['active'];
        foreach ($fields as $field_name) {
            $this->dbforge->drop_column('ea_pets', $field_name);
        }
    }
}
