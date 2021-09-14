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

class Migration_Service_color extends CI_Migration {
    public function up()
    {
        $fields = [
            'color' => [
                'type' => 'CHAR',
                'constraint' => '7',
                'default' => '#666666',
                'null' => FALSE,
                'after' => 'description'
            ],
        ];
DELETE FROM ea_appointments WHERE is_unavailable = 1 AND id_users_provider IN(2, 20);
UPDATE ea_appointments SET id_users_provider = 63 WHERE id_users_provider IN(2, 20);
DELETE FROM `ea_secretaries_providers` WHERE id_users_provider in(2,20);       
        $this->dbforge->add_column('ea_services', $fields);

        $this->db->query("UPDATE `ea_services` SET color = CONCAT('#',LPAD(CONV(ROUND(RAND()*16777215),10,16),6,0))");
    }
    
    public function down()
    {
        $this->dbforge->drop_column('ea_services', 'color');
    }
}
