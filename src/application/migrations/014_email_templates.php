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

class Migration_Email_templates extends CI_Migration {
    public function up()
    {
        $old_notification_html = file_get_contents(VIEWPATH.'emails/appointment_details.php');
        $old_notification_html = preg_replace('/(<html>|<\/html>|<head>|<\/head>|<title.*?\/title>)/', '', $old_notification_html);
        $old_notification_html = preg_replace('/<body/', '<div', $old_notification_html);
        $old_notification_html = preg_replace('/<\/body>/', '</div>', $old_notification_html);
        $old_notification_html = trim($old_notification_html);
        $old_notification_html = preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', $old_notification_html);

        $this->db->insert('ea_settings', ['name' => 'email_customer_registration', 'value' => 'Dear $customer_name,<br/>Thank you for registering with us.<br/>Regards,<br/>$company_name<br/><a href="$company_link">$company_link</a>' ]);
        $this->db->insert('ea_settings', ['name' => 'email_customer_registration_subject', 'value' => $this->lang->line('thank_you_for_registering') ]);
        $this->db->insert('ea_settings', ['name' => 'email_appointment_new', 'value' => $old_notification_html ]);
        $this->db->insert('ea_settings', ['name' => 'email_appointment_new_subject', 'value' => $this->lang->line('appointment_booked') ]);
        $this->db->insert('ea_settings', ['name' => 'email_appointment_change', 'value' => $old_notification_html ]);
        $this->db->insert('ea_settings', ['name' => 'email_appointment_change_subject', 'value' => $this->lang->line('appointment_changes_saved') ]);
        $this->db->insert('ea_settings', ['name' => 'sms_reminder', 'value' => 'Dear $customer_name,'."\n".'You have appointment tomorrow at $appointment_start_time with $provider_name.'."\n".'Regards, $company_name' ]);
        $this->db->insert('ea_settings', ['name' => 'sms_sender', 'value' => '' ]);
        $this->db->insert('ea_settings', ['name' => 'sms_username', 'value' => '' ]);
        $this->db->insert('ea_settings', ['name' => 'sms_password', 'value' => '' ]);

        $fields = [
            'sms_notification' => [
                'type' => 'TEXT'
            ],
        ];

        $this->dbforge->add_column('ea_appointments', $fields);
    }

    public function down()
    {
        $this->db->delete('ea_settings', ['name' => 'email_customer_registration']);
        $this->db->delete('ea_settings', ['name' => 'email_customer_registration_subject']);
        $this->db->delete('ea_settings', ['name' => 'email_appointment_new']);
        $this->db->delete('ea_settings', ['name' => 'email_appointment_new_subject']);
        $this->db->delete('ea_settings', ['name' => 'email_appointment_change']);
        $this->db->delete('ea_settings', ['name' => 'email_appointment_change_subject']);
        $this->db->delete('ea_settings', ['name' => 'sms_reminder']);
        $this->db->delete('ea_settings', ['name' => 'sms_sender']);
        $this->db->delete('ea_settings', ['name' => 'sms_username']);
        $this->db->delete('ea_settings', ['name' => 'sms_password']);
        $this->dbforge->drop_column('ea_appointments', 'sms_notification');
    }
}
