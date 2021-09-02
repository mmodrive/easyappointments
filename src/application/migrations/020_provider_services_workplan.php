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
        $fields = [
            'id_workplan_json' => [
                'type' => 'TEXT'
            ],
        ];

        // $this->dbforge->add_column('ea_services_providers', $fields);

        // $this->db->query('ALTER TABLE `ea_services_providers` DROP PRIMARY KEY,
        //     ADD PRIMARY KEY (`id_users`, `id_services`, `id_workplan`, `id_availability`)');
    }

    public function down()
    {
        // $this->db->query('ALTER TABLE `ea_services_providers` DROP PRIMARY KEY,
        //     ADD PRIMARY KEY (`id_users`, `id_services`)');
        $this->dbforge->drop_column('ea_services_providers', 'id_workplan_json');
    }
}
