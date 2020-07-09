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

        // :: INSERT OR UPDATE pet RECORD
        if ( ! isset($pet['id']))
        {
            $pet['id'] = $this->_insert($pet);
        }
        else
        {
            $this->_update($pet);
        }

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
            throw new Exception('pet\'s owner or pet\'s name is not provided.');
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
        $this->load->helper('general');

        // Before inserting the pet we need to get the pet's role id
        // from the database and assign it to the new record as a foreign key.
        $customer_role_id = $this->db
            ->select('id')
            ->from('ea_roles')
            ->where('slug', DB_SLUG_CUSTOMER)
            ->get()->row()->id;

        $pet['id_roles'] = $customer_role_id;
        $settings = $pet['settings'];
        unset($pet['settings']);

        $this->db->trans_begin();

        if ( ! $this->db->insert('ea_users', $pet))
        {
            throw new Exception('Could not insert pet to the database.');
        }

        $pet['id'] = (int)$this->db->insert_id();
        $settings['id_users'] = $pet['id'];
        $settings['salt'] = generate_salt();
        $settings['password'] = hash_password($settings['salt'], $settings['password']);

        // Insert pet settings. 
        if ( ! $this->db->insert('ea_user_settings', $settings))
        {
            $this->db->trans_rollback();
            throw new Exception('Could not insert pet settings into the database.');
        }

        $this->db->trans_complete();

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
        $this->load->helper('general');

        // Do not update empty string values.
        foreach ($pet as $key => $value)
        {
            if ($value === '')
            {
                unset($pet[$key]);
            }
        }

        $settings = $pet['settings'];
        $settings_row = $this->db->get_where('ea_user_settings', ['id_users' => $pet['id']])->row();
        unset($pet['settings']);
        $settings['id_users'] = $pet['id'];

        $this->db->trans_begin();

        $this->db->where('id', $pet['id']);
        if ( ! $this->db->update('ea_users', $pet))
        {
            throw new Exception('Could not update pet to the database.');
        }

        if( !empty($settings) ){
            if (isset($settings['password']))
            {
                if( isset($settings_row) )
                    $salt = $settings_row->salt;
                else 
                    $settings['salt'] = $salt = generate_salt();
                $settings['password'] = hash_password($salt, $settings['password']);
            }

            if( isset($settings_row) ){
                // Update pet settings.
                $this->db->where('id_users', $settings['id_users']);
                if ( ! $this->db->update('ea_user_settings', $settings))
                {
                    throw new Exception('Could not update pet settings.');
                }
            }
            else
            {
                // Insert pet settings. 
                if ( ! $this->db->insert('ea_user_settings', $settings))
                {
                    $this->db->trans_rollback();
                    throw new Exception('Could not insert pet settings into the database.');
                }
            }
        }

        $this->db->trans_complete();

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
        if ( ! isset($pet['email']))
        {
            throw new Exception('pet\'s email was not provided: '
                . print_r($pet, TRUE));
        }

        // Get pet's role id
        $result = $this->db
            ->select('ea_users.id')
            ->from('ea_users')
            ->join('ea_roles', 'ea_roles.id = ea_users.id_roles', 'inner')
            ->where('ea_users.email', $pet['email'])
            ->where('ea_roles.slug', DB_SLUG_CUSTOMER)
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
            $num_rows = $this->db->get_where('ea_users',
                ['id' => $pet['id']])->num_rows();
            if ($num_rows == 0)
            {
                throw new Exception('Provided pet id does not '
                    . 'exist in the database.');
            }
        }
        // Validate required fields
        if ( ! isset($pet['last_name'])
            || ! isset($pet['email'])
            || ! isset($pet['phone_number']))
        {
            throw new Exception('Not all required fields are provided: '
                . print_r($pet, TRUE));
        }

        // Validate email address
        if ( ! filter_var($pet['email'], FILTER_VALIDATE_EMAIL))
        {
            throw new Exception('Invalid email address provided: '
                . $pet['email']);
        }

        // Validate pet password
        if (isset($pet['settings']['password']))
        {
            if (strlen($pet['settings']['password']) < MIN_PASSWORD_LENGTH)
            {
                throw new Exception('The user password must be at least '
                    . MIN_PASSWORD_LENGTH . ' characters long.');
            }
        }

        // When inserting a record the email address must be unique.
        $customer_id = (isset($pet['id'])) ? $pet['id'] : '';

        $num_rows = $this->db
            ->select('*')
            ->from('ea_users')
            ->join('ea_roles', 'ea_roles.id = ea_users.id_roles', 'inner')
            ->where('ea_roles.slug', DB_SLUG_CUSTOMER)
            ->where('ea_users.email', $pet['email'])
            ->where('ea_users.id <>', $customer_id)
            ->get()
            ->num_rows();

        if ($num_rows > 0)
        {
            throw new Exception('Given email address belongs to another pet record. '
                . 'Please use a different email.');
        }

        return TRUE;
    }

    /**
     * Delete an existing pet record from the database.
     *
     * @param int $customer_id The record id to be deleted.
     *
     * @return bool Returns the delete operation result.
     *
     * @throws Exception If $customer_id argument is invalid.
     */
    public function delete($customer_id)
    {
        if ( ! is_numeric($customer_id))
        {
            throw new Exception('Invalid argument type $customer_id: ' . $customer_id);
        }

        $num_rows = $this->db->get_where('ea_users', ['id' => $customer_id])->num_rows();
        if ($num_rows == 0)
        {
            return FALSE;
        }

        return $this->db->delete('ea_users', ['id' => $customer_id]);
    }

    /**
     * Get a specific row from the appointments table.
     *
     * @param int $customer_id The record's id to be returned.
     *
     * @return array Returns an associative array with the selected record's data. Each key has the same name as the
     * database field names.
     *
     * @throws Exception If $customer_id argumnet is invalid.
     */
    public function get_row($customer_id)
    {
        if ( ! is_numeric($customer_id))
        {
            throw new Exception('Invalid argument provided as $customer_id : ' . $customer_id);
        }

        $pet = $this->db->get_where('ea_users', ['id' => $customer_id])->row_array();

        $pet['settings'] = $this->db->get_where('ea_user_settings',
            ['id_users' => $customer_id])->row_array();
        unset($pet['settings']['id_users']);

        return $pet;
    }

    /**
     * Get a specific field value from the database.
     *
     * @param string $field_name The field name of the value to be returned.
     * @param int $customer_id The selected record's id.
     *
     * @return string Returns the records value from the database.
     *
     * @throws Exception If $customer_id argument is invalid.
     * @throws Exception If $field_name argument is invalid.
     * @throws Exception If requested pet record does not exist in the database.
     * @throws Exception If requested field name does not exist in the database.
     */
    public function get_value($field_name, $customer_id)
    {
        if ( ! is_numeric($customer_id))
        {
            throw new Exception('Invalid argument provided as $customer_id: '
                . $customer_id);
        }

        if ( ! is_string($field_name))
        {
            throw new Exception('$field_name argument is not a string: '
                . $field_name);
        }

        if ($this->db->get_where('ea_users', ['id' => $customer_id])->num_rows() == 0)
        {
            throw new Exception('The record with the $customer_id argument '
                . 'does not exist in the database: ' . $customer_id);
        }

        $row_data = $this->db->get_where('ea_users', ['id' => $customer_id]
        )->row_array();
        if ( ! isset($row_data[$field_name]))
        {
            throw new Exception('The given $field_name argument does not'
                . 'exist in the database: ' . $field_name);
        }

        $pet = $this->db->get_where('ea_users', ['id' => $customer_id])->row_array();

        return $pet[$field_name];
    }

    /**
     * Get all, or specific records from appointment's table.
     *
     * @example $this->Model->getBatch('id = ' . $recordId);
     *
     * @param string $whereClause (OPTIONAL) The WHERE clause of the query to be executed. DO NOT INCLUDE 'WHERE'
     * KEYWORD.
     *
     * @return array Returns the rows from the database.
     */
    public function get_batch($where_clause = '')
    {
        $customers_role_id = $this->get_customers_role_id();

        if ($where_clause != '')
        {
            $this->db->where($where_clause);
        }

        $this->db->where('id_roles', $customers_role_id);

        $batch = $this->db->get('ea_users')->result_array();

        // Get every pet settings.
        foreach ($batch as &$pet)
        {
            $pet['settings'] = $this->db->get_where('ea_user_settings',
                ['id_users' => $pet['id']])->row_array();
            unset($pet['settings']['id_users']);
        }

        return $batch;
    }

    /**
     * Get the customers role id from the database.
     *
     * @return int Returns the role id for the pet records.
     */
    public function get_customers_role_id()
    {
        return $this->db->get_where('ea_roles', ['slug' => DB_SLUG_CUSTOMER])->row()->id;
    }

    /**
     * Retrieve user's salt from database.
     *
     * @param string $username This will be used to find the user record.
     *
     * @return string Returns the salt db value.
     */
    public function get_salt($customer_id)
    {
        $user = $this->db->get_where('ea_user_settings', ['id_users' => $customer_id])->row_array();
        return ($user) ? $user['salt'] : '';
    }

    /**
     * Performs the check of the given user credentials.
     *
     * @param string $username Given user's name.
     * @param string $password Given user's password (not hashed yet).
     *
     * @return array|null Returns the session data of the logged in user or null on failure.
     */
    public function check_login($email, $password)
    {
        $this->load->helper('general');

        $pet = [ 'email' => $email ];
        $customer_id = $this->customers_model->find_record_id($pet);

        $salt = $this->customers_model->get_salt($customer_id);
        if ( !$salt )
            return [ 'email' => $email ];

        $password = hash_password($salt, $password);

        $pet = $this->db
            ->select('ea_users.*')
            ->from('ea_users')
            ->join('ea_user_settings', 'ea_user_settings.id_users = ea_users.id', 'inner')
            ->where('ea_users.email', $email)
            ->where('ea_user_settings.password', $password)
            ->get()->row_array();

        return ($pet) ? $pet : NULL;
    }
}
