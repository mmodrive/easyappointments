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

class Migration_Soap_report extends CI_Migration {
    public function up()
    {
        $this->db->query('
            CREATE TABLE IF NOT EXISTS `ea_soap_reports` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `modified` DATETIME DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,
                `id_pets` INT NOT NULL,
                `date` DATETIME,
                `subjective` TEXT,
                `objective` TEXT,
                `assessment` TEXT,
                `plan` TEXT,
                PRIMARY KEY (`id`)
            )
                ENGINE = InnoDB
                DEFAULT CHARSET = utf8;
        ');

        $this->db->query('ALTER TABLE `ea_soap_reports`
            ADD CONSTRAINT `soap_report_pets` FOREIGN KEY (`id_pets`) REFERENCES `ea_pets` (`id`)
            ON DELETE CASCADE
            ON UPDATE CASCADE');
    }

    public function down()
    {
        $this->db->query('DROP TABLE `ea_soap_reports`;');
    }
}
