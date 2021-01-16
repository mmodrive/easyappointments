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

class Migration_Add_observations extends CI_Migration {
    public function up()
    {
        $fields = [
            'comments' => [
                'name' => 'observ_current',
                'type' => 'TEXT'
            ]
        ];

        $this->dbforge->modify_column('ea_appointments', $fields);
        
        $fields = [
            'observ_previous' => [
                'type' => 'TEXT',
                'after' => 'observ_current'
            ],
        ];

        $this->dbforge->add_column('ea_appointments', $fields);
    }

    public function down()
    {
        $fields = [
            'observ_current' => [
                'name' => 'comments',
                'type' => 'TEXT'
            ]
        ];
        $this->dbforge->modify_column('ea_appointments', $fields);
        
        $this->dbforge->drop_column('ea_appointments', 'observ_previous');
    }
}
