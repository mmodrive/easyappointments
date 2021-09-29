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

class Migration_Appointment_counters extends CI_Migration {
    public function up()
    {
        $fields = [
            'disc_num_of_apps_before' => [
                'type' => 'INT',
                'null' => FALSE,
                'default' => 0,
            ],
            'disc_timeframe_days' => [
                'type' => 'INT',
                'null' => FALSE,
                'default' => 0,
            ],
        ];
        
        $this->dbforge->add_column('ea_services', $fields);
        
        $fields = [
            'id_disc_opening_appointment' => [
                'type' => 'INT'
            ],
            'disc_qualify' => [
                'type' => 'BOOLEAN',
                'null' => FALSE,
                'default' => FALSE
            ]
        ];

        $this->dbforge->add_column('ea_users', $fields);

        $this->db->query('ALTER TABLE `ea_users`
        ADD CONSTRAINT `disc_opening_appointment` FOREIGN KEY (`id_disc_opening_appointment`) REFERENCES `ea_appointments` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE');

        $this->db->query('
        CREATE TABLE IF NOT EXISTS `ea_appointments_discount` (
            `id_appointment` INT NOT NULL,
            `days_sum` INT NOT NULL,
            `app_counter` INT NOT NULL,
            `app_discount` BOOLEAN NOT NULL,
            PRIMARY KEY (`id_appointment`)
        )
            ENGINE = InnoDB
            DEFAULT CHARSET = utf8;
        ');

        $this->db->query('ALTER TABLE `ea_appointments_discount`
        ADD CONSTRAINT `ea_appointments_discount_appointments` FOREIGN KEY (`id_appointment`) REFERENCES `ea_appointments` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE');

//CREATE DEFINER=`bookingsuser`@`localhost` PROCEDURE ApplyAppointmentDiscounts(p_id_users_customer INT, p_id_services INT)
        $this->db->query('
CREATE PROCEDURE ApplyAppointmentDiscounts(p_id_users_customer INT, p_id_services INT)
BEGIN 
DELETE FROM ea_appointments_discount;
INSERT INTO ea_appointments_discount (id_appointment,app_counter,days_sum,app_discount)
WITH apps AS (
    SELECT apps.id AS id_appointment, start_datetime app_datetime, apps.id_services, apps.id_users_customer, apps.id_pets, servs.disc_num_of_apps_before, servs.disc_timeframe_days, usrsets.disc_qualify,
            NOT ISNULL(discopen.id) AS opening_app,
            IFNULL(DATEDIFF(start_datetime, LAG(start_datetime,1) OVER w),0) AS days_passed,
            ROW_NUMBER() OVER w AS position
    FROM  `ea_appointments` AS apps 
                LEFT JOIN `ea_services` servs ON apps.id_services = servs.id
                LEFT JOIN `ea_users` discopen ON apps.id = discopen.id_disc_opening_appointment
                LEFT JOIN `ea_users` usrsets ON apps.id_users_customer = usrsets.id
    WHERE is_unavailable = 0
    WINDOW w AS (PARTITION BY id_services, id_users_customer, id_pets ORDER BY start_datetime))
SELECT id_appointment,
    @app_counter:=IF(position = 1 OR opening_app OR @days_sum+days_passed > disc_timeframe_days OR @app_counter+1 > disc_num_of_apps_before+1,1,@app_counter+1) AS app_counter,
    @days_sum   :=IF(position = 1 OR opening_app OR @days_sum+days_passed > disc_timeframe_days,0,@days_sum+days_passed) AS days_sum,
    IF(disc_qualify AND disc_num_of_apps_before > 0 AND disc_timeframe_days > 0 AND @app_counter = disc_num_of_apps_before+1,TRUE,FALSE) AS app_discount
FROM apps, (SELECT @days_sum:=0,@app_counter:=1) AS declarations
ORDER BY id_services, id_users_customer, id_pets, position;
END
        ');
        //AND (p_id_users_customer IS NULL or apps.id_users_customer = p_id_users_customer) AND (p_id_services IS NULL or apps.id_services = p_id_services)

        $this->db->query('CALL ApplyAppointmentDiscounts(NULL, NULL)');

        $this->db->query('CREATE TRIGGER ea_appointments_insert_calc_discount AFTER INSERT ON `ea_appointments` FOR EACH ROW CALL ApplyAppointmentDiscounts(NEW.id_users_customer, NEW.id_services);');
        $this->db->query('CREATE TRIGGER ea_appointments_update_calc_discount AFTER UPDATE ON `ea_appointments` FOR EACH ROW CALL ApplyAppointmentDiscounts(NULL, NULL);');
        $this->db->query('CREATE TRIGGER ea_appointments_delete_calc_discount AFTER DELETE ON `ea_appointments` FOR EACH ROW CALL ApplyAppointmentDiscounts(OLD.id_users_customer, OLD.id_services);');

        $this->db->query('CREATE TRIGGER ea_services_insert_calc_discount AFTER INSERT ON `ea_services` FOR EACH ROW CALL ApplyAppointmentDiscounts(NULL, NEW.id);');
        $this->db->query('CREATE TRIGGER ea_services_update_calc_discount AFTER UPDATE ON `ea_services` FOR EACH ROW CALL ApplyAppointmentDiscounts(NULL, NULL);');
        $this->db->query('CREATE TRIGGER ea_services_delete_calc_discount AFTER DELETE ON `ea_services` FOR EACH ROW CALL ApplyAppointmentDiscounts(NULL, OLD.id);');

        $this->db->query('CREATE TRIGGER ea_users_insert_calc_discount AFTER INSERT ON `ea_users` FOR EACH ROW CALL ApplyAppointmentDiscounts(NEW.id, NULL);');
        $this->db->query('CREATE TRIGGER ea_users_update_calc_discount AFTER UPDATE ON `ea_users` FOR EACH ROW CALL ApplyAppointmentDiscounts(NULL, NULL);');
        $this->db->query('CREATE TRIGGER ea_users_delete_calc_discount AFTER DELETE ON `ea_users` FOR EACH ROW CALL ApplyAppointmentDiscounts(OLD.id, NULL);');
    }

    public function down()
    {
        $this->db->query('DROP TRIGGER IF EXISTS ea_appointments_insert_calc_discount');
        $this->db->query('DROP TRIGGER IF EXISTS ea_appointments_update_calc_discount');
        $this->db->query('DROP TRIGGER IF EXISTS ea_appointments_delete_calc_discount');
        $this->db->query('DROP TRIGGER IF EXISTS ea_services_insert_calc_discount');
        $this->db->query('DROP TRIGGER IF EXISTS ea_services_update_calc_discount');
        $this->db->query('DROP TRIGGER IF EXISTS ea_services_delete_calc_discount');
        $this->db->query('DROP TRIGGER IF EXISTS ea_users_insert_calc_discount');
        $this->db->query('DROP TRIGGER IF EXISTS ea_users_update_calc_discount');
        $this->db->query('DROP TRIGGER IF EXISTS ea_users_delete_calc_discount');
        $this->db->query('DROP PROCEDURE IF EXISTS ApplyAppointmentDiscounts');
        $this->dbforge->drop_column('ea_services', 'disc_num_of_apps_before');
        $this->dbforge->drop_column('ea_services', 'disc_timeframe_days');
        $this->db->query('ALTER TABLE `ea_users` DROP FOREIGN KEY `disc_opening_appointment`');
        $this->dbforge->drop_column('ea_users', 'id_disc_opening_appointment');
        $this->dbforge->drop_column('ea_users', 'disc_qualify');
        $this->db->query('ALTER TABLE `ea_appointments_discount` DROP FOREIGN KEY `ea_appointments_discount_appointments`');
        $this->db->query('DROP TABLE `ea_appointments_discount`;');
    }
}
