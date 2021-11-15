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

class Migration_User_notification_new_customer extends CI_Migration {
    public function up()
    {
        $fields = [
            'notifications_new_customer' => [
                'type' => 'BOOLEAN',
                'null' => FALSE,
                'default' => FALSE
            ],
        ];

        $this->dbforge->add_column('ea_user_settings', $fields);

    }

    public function down()
    {
        $this->dbforge->drop_column('ea_user_settings', 'notifications_new_customer');
    }
}
