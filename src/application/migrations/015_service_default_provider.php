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

class Migration_Service_default_provider extends CI_Migration {
    public function up()
    {
        $fields = [
            'id_users_default_provider' => [
                'type' => 'INT',
            ],
        ];

        $this->dbforge->add_column('ea_services', $fields);

        $this->db->query('ALTER TABLE `ea_services`
            ADD CONSTRAINT `services_default_provider` FOREIGN KEY (`id_users_default_provider`) REFERENCES `ea_users` (`id`)
            ON DELETE CASCADE
            ON UPDATE CASCADE');
    }

    public function down()
    {
        $this->db->query('ALTER TABLE ea_services DROP FOREIGN KEY services_default_provider');
        $this->dbforge->drop_column('ea_services', 'id_users_default_provider');
    }
}
