<?php defined('BASEPATH') OR exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Open Source Web Scheduler
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) 2013 - 2018, Alex Tselegidis
 * @license     http://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        http://easyappointments.org
 * @since       v1.0.0
 * ---------------------------------------------------------------------------- */

/**
 * Customers Model
 *
 * @package Models
 */
class Pets_Model extends CI_Model {
    /**
     * Add a pet record to the database.
     *
     * This method adds a pet to the database. If the pet doesn't exists it is going to be inserted, otherwise
     * the record is going to be updated.
     *
     * @param array $pet Associative array with the pet's data. Each key has the same name with the database
     * fields.
     *
     * @return int Returns the pet id.
     */
    public function add($pet)
    {
        // Validate the pet data before doing anything.
        $this->validate($pet);

        // :: CHECK IF pet ALREADY EXIST (FROM EMAIL).
        if ($this->exists($pet) && ! isset($pet['id']))
        {
            // Find the pet id from the database.
            $pet['id'] = $this->find_record_id($pet);
        }

        $attachments = 0;
        if( isset($pet['attachment']) ){
            if ( !is_numeric($pet['attachment']) )
                throw new Exception('attachment is expected to specify number of attachments if any.');
            $attachments = (int)$pet['attachment'];
            unset($pet['attachment']);
        }

        unset($pet['age']);

        $this->db->trans_begin();

        // :: INSERT OR UPDATE pet RECORD
        if ( ! isset($pet['id']))
        {
            $pet['id'] = $this->_insert($pet);
        }
        else
        {
            $this->_update($pet);
        }

        if( $attachments > 0 ){
            $files = $_FILES['pet_attachment'];

            for($i=0; $i<count($files['name']); $i++){
                $target_path = FCPATH.'storage/uploads/';
                $ext = strtolower(pathinfo($files['name'][$i],PATHINFO_EXTENSION));
                $storage_name = md5(uniqid()) . "." . $ext;
                $target_path = $target_path . $storage_name;
                $tmp_path = $files['tmp_name'][$i];

                if(!move_uploaded_file($tmp_path, $target_path))
                    throw new Exception("There was an error uploading the file, please try again!");

                $attachment = [ 
                    'id_pets' => $pet['id'], 
                    'type' => $files['type'][$i],
                    'filename' => $files['name'][$i],
                    'storage_name' => $storage_name
                ];

                if ( ! $this->db->insert('ea_attachments', $attachment))
                    throw new Exception('Could not insert pet to the database.');
            }

        }

        $this->db->trans_complete();

        return $pet['id'];
    }

    /**
     * Check if a particular pet record already exists.
     *
     * This method checks whether the given pet already exists in the database. It doesn't search with the id, but
     * with the following fields: "email"
     *
     * @param array $pet Associative array with the pet's data. Each key has the same name with the database
     * fields.
     *
     * @return bool Returns whether the record exists or not.
     *
     * @throws Exception If pet email property is missing.
     */
    public function exists($pet)
    {
        if ( ! isset($pet['id_users']) || ! isset($pet['name']))
        {
            throw new Exception('Pet\'s owner or pet\'s name is not provided.');
        }

        // This method shouldn't depend on another method of this class.
        $num_rows = $this->db
            ->select('*')
            ->from('ea_users')
            ->join('ea_pets', 'ea_users.id = ea_pets.id_users', 'inner')
            ->where('ea_pets.name', $pet['name'])
            ->where('ea_users.id', $pet['id_users'])
            ->get()->num_rows();

        return ($num_rows > 0) ? TRUE : FALSE;
    }

    /**
     * Insert a new pet record to the database.
     *
     * @param array $pet Associative array with the pet's data. Each key has the same name with the database
     * fields.
     *
     * @return int Returns the id of the new record.
     *
     * @throws Exception If pet record could not be inserted.
     */
    protected function _insert($pet)
    {
        if ( ! $this->db->insert('ea_pets', $pet))
        {
            throw new Exception('Could not insert pet to the database.');
        }

        $pet['id'] = (int)$this->db->insert_id();

        return (int)$this->db->insert_id();
    }

    /**
     * Update an existing pet record in the database.
     *
     * The pet data argument should already include the record ID in order to process the update operation.
     *
     * @param array $pet Associative array with the pet's data. Each key has the same name with the database
     * fields.
     *
     * @return int Returns the updated record ID.
     *
     * @throws Exception If pet record could not be updated.
     */
    protected function _update($pet)
    {
        // Do not update empty string values.
        // foreach ($pet as $key => $value)
        // {
        //     if ($value === '')
        //     {
        //         unset($pet[$key]);
        //     }
        // }

        $this->db->where('id', $pet['id']);
        if ( ! $this->db->update('ea_pets', $pet))
        {
            throw new Exception('Could not update pet to the database.');
        }

        return (int)$pet['id'];
    }

    /**
     * Find the database id of a pet record.
     *
     * The pet data should include the following fields in order to get the unique id from the database: "email"
     *
     * IMPORTANT: The record must already exists in the database, otherwise an exception is raised.
     *
     * @param array $pet Array with the pet data. The keys of the array should have the same names as the
     * database fields.
     *
     * @return int Returns the ID.
     *
     * @throws Exception If pet record does not exist.
     */
    public function find_record_id($pet)
    {
        if ( ! isset($pet['id_users']) || ! isset($pet['name']))
        {
            throw new Exception('Pet\'s owner or pet\'s name is not provided.');
        }

        $result = $this->db
            ->select('ea_pets.id')
            ->from('ea_users')
            ->join('ea_pets', 'ea_users.id = ea_pets.id_users', 'inner')
            ->where('ea_pets.name', $pet['name'])
            ->where('ea_users.id', $pet['id_users'])
            ->get();

        if ($result->num_rows() == 0)
        {
            throw new Exception('Could not find pet record id.');
        }

        return $result->row()->id;
    }

    /**
     * Validate pet data before the insert or update operation is executed.
     *
     * @param array $pet Contains the pet data.
     *
     * @return bool Returns the validation result.
     *
     * @throws Exception If pet validation fails.
     */
    public function validate($pet)
    {
        $this->load->helper('data_validation');

        // If a pet id is provided, check whether the record
        // exist in the database.
        if (isset($pet['id']))
        {
            $num_rows = $this->db->get_where('ea_pets',
                ['id' => $pet['id']])->num_rows();
            if ($num_rows == 0)
            {
                throw new Exception('Provided pet id does not '
                    . 'exist in the database.');
            }
        }
        // Validate required fields
        if ( ! isset($pet['name'])
            || ! isset($pet['breed'])
            || ! isset($pet['colours'])
            || ! isset($pet['sex'])
            || ! isset($pet['dob'])
            || ! isset($pet['nature'])
        )
        {
            throw new Exception('Not all required fields are provided: '
                . print_r($pet, TRUE));
        }

        // When inserting a pet the name must be unique.
        $pet_id = (isset($pet['id'])) ? $pet['id'] : '';

        $num_rows = $this->db
            ->select('*')
            ->from('ea_pets')
            ->where('name', $pet['name'])
            ->where('id_users', $pet['id_users'])
            ->where('id <>', $pet_id)
            ->get()
            ->num_rows();

        if ($num_rows > 0)
        {
            throw new Exception('A pet with this name already exists. '
                . 'Please select from existing to update or check the name.');
        }

        return TRUE;
    }

    /**
     * Delete an existing pet record from the database.
     *
     * @param int $pet_id The record id to be deleted.
     *
     * @return bool Returns the delete operation result.
     *
     * @throws Exception If $pet_id argument is invalid.
     */
    public function delete($pet_id)
    {
        if ( ! is_numeric($pet_id))
        {
            throw new Exception('Invalid argument type $pet_id: ' . $pet_id);
        }

        $num_rows = $this->db->get_where('ea_pets', ['id' => $pet_id])->num_rows();
        if ($num_rows == 0)
        {
            return FALSE;
        }

        return $this->db->delete('ea_pets', ['id' => $pet_id]);
    }

    /**
     * Get a specific row from the appointments table.
     *
     * @param int $pet_id The record's id to be returned.
     *
     * @return array Returns an associative array with the selected record's data. Each key has the same name as the
     * database field names.
     *
     * @throws Exception If $pet_id argumnet is invalid.
     */
    public function get_row($pet_id, $get_subs = TRUE)
    {
        if ( ! is_numeric($pet_id))
        {
            throw new Exception('Invalid argument provided as $pet_id : ' . $pet_id);
        }

        $pet = $this->db->get_where('ea_pets', ['id' => $pet_id])->row_array();

        $pet['appointments'] = $this->db
            ->select('app.*, CONCAT(provider.first_name, " ", provider.last_name) provider_name, service.name service_name')
            ->from('ea_appointments AS app')
            ->join('ea_users AS provider', 'app.id_users_provider=provider.id', 'inner')
            ->join('ea_services AS service', 'app.id_services=service.id', 'inner')
            ->where(['id_pets' => $pet_id])
            ->order_by('start_datetime', 'DESC')
            ->limit(10)
            ->get()->result_array();

        $pet['attachments'] = $this->db
            ->get_where('ea_attachments',['id_pets' => $pet_id])
            ->result_array();

        $this->compute_details($pet);

        return $pet;
    }

    /**
     * Get a specific row from the appointments table.
     *
     * @param int $pet_id The record's id to be returned.
     *
     * @return array Returns an associative array with the selected record's data. Each key has the same name as the
     * database field names.
     *
     * @throws Exception If $pet_id argumnet is invalid.
     */
    public function compute_details(&$pet_details)
    {
        $this->load->model('settings_model');

        if ( ! isset($pet_details) )
        {
            throw new Exception('No Pet provided');
        }

        if ( isset($pet_details['dob']) ){
            $date = new DateTime($pet_details['dob']);
            $now = new DateTime();
            $interval = $now->diff($date);
            $pet_details['age'] = $interval->y == 0 ? $interval->m.' '.lang('months') : $interval->y.' '.lang('years');
        }

        if ( isset($pet_details['nature']) ){
            $natures = json_decode($this->settings_model->get_setting('pet_nature'));
            $pet_details['nature_name'] = $natures->{$pet_details['nature']};
        }

        if ( isset($pet_details['sex']) ){
            $sexes = json_decode($this->settings_model->get_setting('pet_sex'));
            $pet_details['sex_name'] = $sexes->{$pet_details['sex']};
        }

        $pet_details['title'] = $pet_details['name'] .', '.
            $pet_details['breed'] .', '.
            $pet_details['colours'] .', '.
            $pet_details['age'];

        return $pet_details;
    }

    /**
     * Get a specific field value from the database.
     *
     * @param string $field_name The field name of the value to be returned.
     * @param int $pet_id The selected record's id.
     *
     * @return string Returns the records value from the database.
     *
     * @throws Exception If $pet_id argument is invalid.
     * @throws Exception If $field_name argument is invalid.
     * @throws Exception If requested pet record does not exist in the database.
     * @throws Exception If requested field name does not exist in the database.
     */
    public function get_value($field_name, $pet_id)
    {
        if ( ! is_numeric($pet_id))
        {
            throw new Exception('Invalid argument provided as $pet_id: '
                . $pet_id);
        }

        if ( ! is_string($field_name))
        {
            throw new Exception('$field_name argument is not a string: '
                . $field_name);
        }

        if ($this->db->get_where('ea_pets', ['id' => $pet_id])->num_rows() == 0)
        {
            throw new Exception('The record with the $pet_id argument '
                . 'does not exist in the database: ' . $pet_id);
        }

        $row_data = $this->db->get_where('ea_pets', ['id' => $pet_id])->row_array();
        if ( ! isset($row_data[$field_name]))
        {
            throw new Exception('The given $field_name argument does not'
                . 'exist in the database: ' . $field_name);
        }

        $pet = $this->db->get_where('ea_pets', ['id' => $pet_id])->row_array();

        return $pet[$field_name];
    }
}
