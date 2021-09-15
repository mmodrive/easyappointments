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
            'disc_counter' => [
                'type' => 'INT',
                'null' => FALSE,
                'default' => 0,
            ],
            'disc_qualify' => [
                'type' => 'BOOLEAN',
                'null' => FALSE,
                'default' => FALSE,
            ],
        ];
        
        $this->dbforge->add_column('ea_appointments', $fields);

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

        $this->dbforge->add_column('ea_user_settings', $fields);

        $this->db->query('ALTER TABLE `ea_user_settings`
        ADD CONSTRAINT `disc_opening_appointment` FOREIGN KEY (`id_disc_opening_appointment`) REFERENCES `ea_appointments` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE');

        $this->db->query('
CREATE PROCEDURE ApplyAppointmentDiscounts(p_id_users_customer INT, p_id_services INT)
BEGIN 
SET @prev_date:=NULL,@prev_customer:=NULL,@prev_service:=NULL,@days_sum:=0,@app_counter:=1;
CREATE TEMPORARY TABLE disc_calculations
SELECT *, 
    @days_sum:=IF(reset_base_counter OR opening_app OR @days_sum+days_passed > disc_timeframe_days,0,@days_sum+days_passed) AS days_sum,
    @app_counter:=IF(reset_base_counter OR opening_app OR @days_sum+days_passed > disc_timeframe_days OR @app_counter+1 > disc_num_of_apps_before+1,1,@app_counter+1) AS app_counter,
    IF(disc_qualify AND disc_num_of_apps_before > 0 AND disc_timeframe_days > 0 AND @app_counter = disc_num_of_apps_before+1,TRUE,FALSE) AS app_discount
FROM (SELECT apps.id AS id_appointment, apps.id_services, apps.id_users_customer, servs.disc_num_of_apps_before, servs.disc_timeframe_days, usrsets.disc_qualify,
        NOT ISNULL(discopen.id_users) AS opening_app,
        IFNULL(DATEDIFF(apps.end_datetime, @prev_date),0) AS days_passed,
        @prev_date:=apps.end_datetime AS prev_date,
        IF(@prev_customer = apps.id_users_customer AND @prev_service = apps.id_services, FALSE, TRUE) AS reset_base_counter,
        @prev_service:=apps.id_services AS prev_service,
        @prev_customer:=apps.id_users_customer AS prev_customer
        FROM `ea_appointments` AS apps 
            LEFT JOIN `ea_services` servs ON apps.id_services = servs.id
            LEFT JOIN `ea_user_settings` discopen ON apps.id = discopen.id_disc_opening_appointment
            LEFT JOIN `ea_user_settings` usrsets ON apps.id_users_customer = usrsets.id_users
        WHERE is_unavailable = 0 AND (p_id_users_customer IS NULL or apps.id_users_customer = p_id_users_customer) AND (p_id_services IS NULL or apps.id_services = p_id_services)
        ORDER BY id_services, id_users_customer, end_datetime) AS base;
SET @DISABLE_EA_DISCOUNT_TRIGGERS=1;
UPDATE `ea_appointments` orig
    INNER JOIN disc_calculations calc ON orig.id = calc.id_appointment
    SET orig.disc_counter = calc.app_counter, orig.disc_qualify = calc.app_discount;
SET @DISABLE_EA_DISCOUNT_TRIGGERS=NULL;
DROP TEMPORARY TABLE disc_calculations;
END
        ');

        $this->db->query('CREATE TRIGGER ea_appointments_insert_calc_discount AFTER INSERT ON `ea_appointments` FOR EACH ROW CALL ApplyAppointmentDiscounts(NEW.id_users_customer, NEW.id_services);');
        $this->db->query('CREATE TRIGGER ea_appointments_update_calc_discount AFTER UPDATE ON `ea_appointments` FOR EACH ROW IF (@DISABLE_EA_DISCOUNT_TRIGGERS IS NULL) THEN CALL ApplyAppointmentDiscounts(NULL, NULL); END IF;');
        $this->db->query('CREATE TRIGGER ea_appointments_delete_calc_discount AFTER DELETE ON `ea_appointments` FOR EACH ROW CALL ApplyAppointmentDiscounts(OLD.id_users_customer, OLD.id_services);');

        $this->db->query('CREATE TRIGGER ea_services_insert_calc_discount AFTER INSERT ON `ea_services` FOR EACH ROW CALL ApplyAppointmentDiscounts(NULL, NEW.id);');
        $this->db->query('CREATE TRIGGER ea_services_update_calc_discount AFTER UPDATE ON `ea_services` FOR EACH ROW CALL ApplyAppointmentDiscounts(NULL, NULL);');
        $this->db->query('CREATE TRIGGER ea_services_delete_calc_discount AFTER DELETE ON `ea_services` FOR EACH ROW CALL ApplyAppointmentDiscounts(NULL, OLD.id);');

        $this->db->query('CREATE TRIGGER ea_user_settings_insert_calc_discount AFTER INSERT ON `ea_user_settings` FOR EACH ROW CALL ApplyAppointmentDiscounts(NEW.id_users, NULL);');
        $this->db->query('CREATE TRIGGER ea_user_settings_update_calc_discount AFTER UPDATE ON `ea_user_settings` FOR EACH ROW CALL ApplyAppointmentDiscounts(NULL, NULL);');
        $this->db->query('CREATE TRIGGER ea_user_settings_delete_calc_discount AFTER DELETE ON `ea_user_settings` FOR EACH ROW CALL ApplyAppointmentDiscounts(OLD.id_users, NULL);');
    }

    public function down()
    {
        $this->db->query('DROP TRIGGER IF EXISTS ea_appointments_insert_calc_discount');
        $this->db->query('DROP TRIGGER IF EXISTS ea_appointments_update_calc_discount');
        $this->db->query('DROP TRIGGER IF EXISTS ea_appointments_delete_calc_discount');
        $this->db->query('DROP TRIGGER IF EXISTS ea_services_insert_calc_discount');
        $this->db->query('DROP TRIGGER IF EXISTS ea_services_update_calc_discount');
        $this->db->query('DROP TRIGGER IF EXISTS ea_services_delete_calc_discount');
        $this->db->query('DROP TRIGGER IF EXISTS ea_user_settings_insert_calc_discount');
        $this->db->query('DROP TRIGGER IF EXISTS ea_user_settings_update_calc_discount');
        $this->db->query('DROP TRIGGER IF EXISTS ea_user_settings_delete_calc_discount');
        $this->db->query('DROP PROCEDURE IF EXISTS ApplyAppointmentDiscounts');
        $this->dbforge->drop_column('ea_services', 'disc_num_of_apps_before');
        $this->dbforge->drop_column('ea_services', 'disc_timeframe_days');
        $this->dbforge->drop_column('ea_appointments', 'disc_counter');
        $this->dbforge->drop_column('ea_appointments', 'disc_qualify');
        $this->db->query('ALTER TABLE `ea_user_settings` DROP FOREIGN KEY `disc_opening_appointment`');
        $this->dbforge->drop_column('ea_user_settings', 'id_disc_opening_appointment');
        $this->dbforge->drop_column('ea_user_settings', 'disc_qualify');
    }
}
