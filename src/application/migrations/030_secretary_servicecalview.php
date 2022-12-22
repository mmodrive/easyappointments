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

class Migration_Secretary_servicecalview extends CI_Migration {

    public function up()
    {
        $fields = [
            'id_users_secretary_provider' => [
                'type' => 'INT',
            ],
        ];

        $this->dbforge->add_column('ea_user_settings', $fields);

        $this->db->query('ALTER TABLE `ea_user_settings`
        ADD CONSTRAINT `users_secretary_provider` FOREIGN KEY (`id_users_secretary_provider`) REFERENCES `ea_users` (`id`)
        ON DELETE SET NULL
        ON UPDATE CASCADE');
    }

    public function down()
    {
        $this->db->query('ALTER TABLE ea_user_settings DROP FOREIGN KEY users_secretary_provider');
        $this->dbforge->drop_column('ea_user_settings', 'id_users_secretary_provider');
    }
}
