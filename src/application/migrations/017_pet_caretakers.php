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

class Migration_Pet_caretakers extends CI_Migration {
    public function up()
    {
        $fields = [
            'vet_name' => [
                'type' => 'VARCHAR',
                'constraint' => '512'
            ],
            'vet_phone' => [
                'type' => 'VARCHAR',
                'constraint' => '128'
            ],
            'vet_email' => [
                'type' => 'VARCHAR',
                'constraint' => '512'
            ],
            'therapist_name' => [
                'type' => 'VARCHAR',
                'constraint' => '512'
            ],
            'therapist_phone' => [
                'type' => 'VARCHAR',
                'constraint' => '128'
            ],
            'therapist_email' => [
                'type' => 'VARCHAR',
                'constraint' => '512'
            ],
        ];

        $this->dbforge->add_column('ea_pets', $fields);
    }

    public function down()
    {
        $fields = ['vet_name', 'vet_phone', 'vet_email', 'therapist_name', 'therapist_phone', 'therapist_email'];
        foreach ($fields as $field_name) {
            $this->dbforge->drop_column('ea_pets', $field_name);
        }
    }
}
