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

class Migration_Customer_discount_default extends CI_Migration {

    public function up()
    {
        $this->db->query("ALTER TABLE `ea_users` CHANGE `disc_qualify` `disc_qualify` TINYINT(1) NOT NULL DEFAULT '1'");
    }
    
    public function down()
    {
        $this->db->query("ALTER TABLE `ea_users` CHANGE `disc_qualify` `disc_qualify` TINYINT(1) NOT NULL DEFAULT '0'");
    }
}
