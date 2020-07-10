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

class Migration_Add_pets extends CI_Migration {
    public function up()
    {
        $this->db->insert('ea_settings', ['name' => 'pet_sex', 'value' => '{"male":"Male","neutmale":"Neutered Male","female":"Female","sprfemale":"Spayed Female"}' ]);
        $this->db->insert('ea_settings', ['name' => 'pet_nature', 'value' => '{"aggressive":"Aggressive","timid":"Timid","nonsocial":"Non-Social","talkative":"Talkative","social":"Social"}' ]);
        $this->db->insert('ea_settings', ['name' => 'pet_appointments', 'value' => '{"N":"Not Shown","O":"Optional","R":"Required"}' ]);

        $this->db->query('
            CREATE TABLE IF NOT EXISTS `ea_pets` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `modified` DATETIME DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,
                `id_users` INT NOT NULL,
                `name` VARCHAR(256),
                `breed` VARCHAR(256),
                `colours` VARCHAR(512),
                `sex` VARCHAR(256),
                `dob` DATETIME,
                `nature` VARCHAR(256),
                `pathology` VARCHAR(512),
                PRIMARY KEY (`id`)
            )
                ENGINE = InnoDB
                DEFAULT CHARSET = utf8;
        ');

        $this->db->query('ALTER TABLE `ea_pets`
            ADD CONSTRAINT `pets_users` FOREIGN KEY (`id_users`) REFERENCES `ea_users` (`id`)
            ON DELETE CASCADE
            ON UPDATE CASCADE');

        $fields = [
            'id_pets' => [
                'type' => 'INT',
                'after' => 'id_services'
            ],
            'depth' => [
                'type' => 'DECIMAL',
                'constraint' => '10, 2',
                'after' => 'notes'
            ],
            'speed' => [
                'type' => 'DECIMAL',
                'constraint' => '10, 2',
                'after' => 'notes'
            ],
            'time' => [
                'type' => 'INT',
                'after' => 'notes'
            ],
            'comments' => [
                'type' => 'TEXT',
                'after' => 'notes'
            ],
        ];

        $this->dbforge->add_column('ea_appointments', $fields);

        $fields = [
            'pets_option' => [
                'type' => 'CHAR',
                'constraint' => '1',
                'default' => 'N',
                'null' => FALSE,
                'after' => 'attendants_number'
            ]
        ];

        $this->dbforge->add_column('ea_services', $fields);

        $this->db->query('ALTER TABLE `ea_appointments`
            ADD CONSTRAINT `appointments_pets` FOREIGN KEY (`id_pets`) REFERENCES `ea_pets` (`id`)
            ON DELETE CASCADE
            ON UPDATE CASCADE');

        $this->db->query('
            CREATE TABLE IF NOT EXISTS `ea_attachments` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `modified` DATETIME DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,
                `id_pets` INT,
                `filename` VARCHAR(256),
                `type` VARCHAR(256),
                `storage_name` VARCHAR(256),
                PRIMARY KEY (`id`)
            )
                ENGINE = InnoDB
                DEFAULT CHARSET = utf8;
        ');

        $this->db->query('ALTER TABLE `ea_attachments`
            ADD CONSTRAINT `attachment_pets` FOREIGN KEY (`id_pets`) REFERENCES `ea_pets` (`id`)
            ON DELETE CASCADE
            ON UPDATE CASCADE');

    }

    public function down()
    {
        $this->db->delete('ea_settings', ['name' => 'pet_sex']);
        $this->db->delete('ea_settings', ['name' => 'pet_nature']);
        $this->db->query('ALTER TABLE ea_appointments DROP FOREIGN KEY appointments_pets');
        $fields = ['id_pets', 'depth', 'speed', 'time', 'comments'];
        foreach ($fields as $field_name) {
            $this->dbforge->drop_column('ea_appointments', $field_name);
        }
        $this->dbforge->drop_column('ea_services', 'pets_option');
        $this->db->query('ALTER TABLE ea_attachments DROP FOREIGN KEY attachment_pets');
        $this->db->query('DROP TABLE `ea_attachments`;');
        $this->db->query('ALTER TABLE ea_pets DROP FOREIGN KEY pets_users');
        $this->db->query('DROP TABLE `ea_pets`;');
    }
}
