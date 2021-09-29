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

class Migration_Appointment_disc_reset extends CI_Migration {
    public function up()
    {
        $this->db->query('ALTER TABLE `ea_users` DROP FOREIGN KEY `disc_opening_appointment`');
        $this->dbforge->drop_column('ea_users', 'id_disc_opening_appointment');

        $fields = [
            'disc_reset' => [
                'type' => 'BOOLEAN',
                'null' => FALSE,
                'default' => FALSE
            ]
        ];
        
        $this->dbforge->add_column('ea_appointments', $fields);

        $this->db->query('DROP PROCEDURE IF EXISTS ApplyAppointmentDiscounts');

//CREATE DEFINER=`bookingsuser`@`localhost` PROCEDURE ApplyAppointmentDiscounts(p_id_users_customer INT, p_id_services INT)
        $this->db->query('
CREATE PROCEDURE ApplyAppointmentDiscounts(p_id_users_customer INT, p_id_services INT)
BEGIN 
DELETE FROM ea_appointments_discount;
INSERT INTO ea_appointments_discount (id_appointment,app_counter,days_sum,app_discount)
WITH apps AS (
    SELECT apps.id AS id_appointment, start_datetime app_datetime, apps.id_services, apps.id_users_customer, apps.id_pets, servs.disc_num_of_apps_before, servs.disc_timeframe_days, usrsets.disc_qualify,
            disc_reset AS opening_app,
            IFNULL(DATEDIFF(start_datetime, LAG(start_datetime,1) OVER w),0) AS days_passed,
            ROW_NUMBER() OVER w AS position
    FROM  `ea_appointments` AS apps 
                LEFT JOIN `ea_services` servs ON apps.id_services = servs.id
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
    }

    public function down()
    {
        $this->dbforge->drop_column('ea_appointments', 'disc_reset');

        $fields = [
            'id_disc_opening_appointment' => [
                'type' => 'INT'
            ],
        ];
        $this->dbforge->add_column('ea_users', $fields);

        $this->db->query('ALTER TABLE `ea_users`
        ADD CONSTRAINT `disc_opening_appointment` FOREIGN KEY (`id_disc_opening_appointment`) REFERENCES `ea_appointments` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE');

        
$this->db->query('DROP PROCEDURE IF EXISTS ApplyAppointmentDiscounts');

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
    }
}
