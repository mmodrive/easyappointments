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
class Customers_Model extends CI_Model {
    /**
     * Add a customer record to the database.
     *
     * This method adds a customer to the database. If the customer doesn't exists it is going to be inserted, otherwise
     * the record is going to be updated.
     *
     * @param array $customer Associative array with the customer's data. Each key has the same name with the database
     * fields.
     *
     * @return int Returns the customer id.
     */
    public function add(&$customer)
    {
        // Validate the customer data before doing anything.
        $this->validate($customer);

        // :: CHECK IF CUSTOMER ALREADY EXIST (FROM EMAIL).
        if ($this->exists($customer) && ! isset($customer['id']))
        {
            // Find the customer id from the database.
            $customer['id'] = $this->find_record_id($customer);
        }

        // :: INSERT OR UPDATE CUSTOMER RECORD
        if ( ! isset($customer['id']))
        {
            $customer['id'] = $this->_insert($customer);
        }
        else
        {
            $this->_update($customer);
        }

        return $customer['id'];
    }

    /**
     * Check if a particular customer record already exists.
     *
     * This method checks whether the given customer already exists in the database. It doesn't search with the id, but
     * with the following fields: "email"
     *
     * @param array $customer Associative array with the customer's data. Each key has the same name with the database
     * fields.
     *
     * @return bool Returns whether the record exists or not.
     *
     * @throws Exception If customer email property is missing.
     */
    public function exists($customer)
    {
        if ( ! isset($customer['email']))
        {
            throw new Exception('Customer\'s email is not provided.');
        }

        // This method shouldn't depend on another method of this class.
        $num_rows = $this->db
            ->select('*')
            ->from('ea_users')
            ->join('ea_roles', 'ea_roles.id = ea_users.id_roles', 'inner')
            ->where('ea_users.email', $customer['email'])
            ->where('ea_roles.slug', DB_SLUG_CUSTOMER)
            ->get()->num_rows();

        return ($num_rows > 0) ? TRUE : FALSE;
    }

    /**
     * Insert a new customer record to the database.
     *
     * @param array $customer Associative array with the customer's data. Each key has the same name with the database
     * fields.
     *
     * @return int Returns the id of the new record.
     *
     * @throws Exception If customer record could not be inserted.
     */
    protected function _insert($customer)
    {
        $this->load->helper('general');

        // Before inserting the customer we need to get the customer's role id
        // from the database and assign it to the new record as a foreign key.
        $customer_role_id = $this->db
            ->select('id')
            ->from('ea_roles')
            ->where('slug', DB_SLUG_CUSTOMER)
            ->get()->row()->id;

        $customer['id_roles'] = $customer_role_id;
        $settings = $customer['settings'] ?? [];
        unset($customer['settings']);

        $this->db->trans_begin();

        if ( ! $this->db->insert('ea_users', $customer))
        {
            throw new Exception('Could not insert customer to the database.');
        }

        $customer['id'] = (int)$this->db->insert_id();
        $settings['id_users'] = $customer['id'];
        $settings['salt'] = generate_salt();
        if(isset($settings['password']))
            $settings['password'] = hash_password($settings['salt'], $settings['password']);

        // Insert customer settings. 
        if ( ! $this->db->insert('ea_user_settings', $settings))
        {
            $this->db->trans_rollback();
            throw new Exception('Could not insert customer settings into the database.');
        }

        $this->db->trans_complete();

        return (int)$customer['id'];
    }

    /**
     * Update an existing customer record in the database.
     *
     * The customer data argument should already include the record ID in order to process the update operation.
     *
     * @param array $customer Associative array with the customer's data. Each key has the same name with the database
     * fields.
     *
     * @return int Returns the updated record ID.
     *
     * @throws Exception If customer record could not be updated.
     */
    protected function _update($customer)
    {
        $this->load->helper('general');

        // Do not update empty string values.
        foreach ($customer as $key => $value)
        {
            if ($value === '')
            {
                unset($customer[$key]);
            }
        }

        $settings = null;
        if (isset($customer['settings'])) {
            $settings = $customer['settings'];
            $settings_row = $this->db->get_where('ea_user_settings', ['id_users' => $customer['id']])->row();
            unset($customer['settings']);
            $settings['id_users'] = $customer['id'];
        }

        $this->db->trans_begin();

        $this->db->where('id', $customer['id']);
        if ( ! $this->db->update('ea_users', $customer))
        {
            throw new Exception('Could not update customer to the database.');
        }

        if( !empty($settings) ){
            if (isset($settings['password']))
            {
                if( isset($settings_row) && !empty($settings_row->salt) )
                    $salt = $settings_row->salt;
                else 
                    $settings['salt'] = $salt = generate_salt();
                $settings['password'] = hash_password($salt, $settings['password']);
            }

            if( isset($settings_row) ){
                // Update customer settings.
                $this->db->where('id_users', $settings['id_users']);
                if ( ! $this->db->update('ea_user_settings', $settings))
                {
                    throw new Exception('Could not update customer settings.');
                }
            }
            else
            {
                // Insert customer settings. 
                if ( ! $this->db->insert('ea_user_settings', $settings))
                {
                    $this->db->trans_rollback();
                    throw new Exception('Could not insert customer settings into the database.');
                }
            }
        }

        $this->db->trans_complete();

        return (int)$customer['id'];
    }

    /**
     * Find the database id of a customer record.
     *
     * The customer data should include the following fields in order to get the unique id from the database: "email"
     *
     * IMPORTANT: The record must already exists in the database, otherwise an exception is raised.
     *
     * @param array $customer Array with the customer data. The keys of the array should have the same names as the
     * database fields.
     *
     * @return int Returns the ID.
     *
     * @throws Exception If customer record does not exist.
     */
    public function find_record_id($customer)
    {
        if ( ! isset($customer['email']))
        {
            throw new Exception('Customer\'s email was not provided: '
                . print_r($customer, TRUE));
        }

        // Get customer's role id
        $result = $this->db
            ->select('ea_users.id')
            ->from('ea_users')
            ->join('ea_roles', 'ea_roles.id = ea_users.id_roles', 'inner')
            ->where('ea_users.email', $customer['email'])
            ->where('ea_roles.slug', DB_SLUG_CUSTOMER)
            ->get();

        if ($result->num_rows() == 0)
        {
            throw new Exception('Could not find customer record id.');
        }

        return $result->row()->id;
    }

    /**
     * Validate customer data before the insert or update operation is executed.
     *
     * @param array $customer Contains the customer data.
     *
     * @return bool Returns the validation result.
     *
     * @throws Exception If customer validation fails.
     */
    public function validate($customer)
    {
        $this->load->helper('data_validation');

        // If a customer id is provided, check whether the record
        // exist in the database.
        if (isset($customer['id']))
        {
            $num_rows = $this->db->get_where('ea_users',
                ['id' => $customer['id']])->num_rows();
            if ($num_rows == 0)
            {
                throw new Exception('Provided customer id does not '
                    . 'exist in the database.');
            }
        }
        // Validate required fields
        if ( ! isset($customer['last_name'])
            || ! isset($customer['email'])
            || ! isset($customer['phone_number']))
        {
            throw new Exception('Not all required fields are provided: '
                . print_r($customer, TRUE));
        }

        // Validate email address
        if ( ! filter_var($customer['email'], FILTER_VALIDATE_EMAIL))
        {
            throw new Exception('Invalid email address provided: '
                . $customer['email']);
        }

        // Validate customer password
        if (isset($customer['settings']['password']))
        {
            if (strlen($customer['settings']['password']) < MIN_PASSWORD_LENGTH)
            {
                throw new Exception('The user password must be at least '
                    . MIN_PASSWORD_LENGTH . ' characters long.');
            }
        }

        // When inserting a record the email address must be unique.
        $customer_id = (isset($customer['id'])) ? $customer['id'] : '';

        $num_rows = $this->db
            ->select('*')
            ->from('ea_users')
            ->join('ea_roles', 'ea_roles.id = ea_users.id_roles', 'inner')
            ->where('ea_roles.slug', DB_SLUG_CUSTOMER)
            ->where('ea_users.email', $customer['email'])
            ->where('ea_users.id <>', $customer_id)
            ->get()
            ->num_rows();

        if ($num_rows > 0)
        {
            throw new Exception('Given email address belongs to another customer record. '
                . 'Please use a different email.');
        }

        return TRUE;
    }

    /**
     * Delete an existing customer record from the database.
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
     * Get a specific row from the customers table.
     *
     * @param int $customer_id The record's id to be returned.
     *
     * @return array Returns an associative array with the selected record's data. Each key has the same name as the
     * database field names.
     *
     * @throws Exception If $customer_id argumnet is invalid.
     */
    public function get_row($customer_id, $loadRelated = FALSE)
    {
        if ( ! is_numeric($customer_id))
        {
            throw new Exception('Invalid argument provided as $customer_id : ' . $customer_id);
        }

        $customer = $this->db->get_where('ea_users', ['id' => $customer_id])->row_array();

        $customer['settings'] = $this->db->get_where('ea_user_settings',
            ['id_users' => $customer_id])->row_array();
        unset($customer['settings']['id_users']);

        if ($loadRelated) {
            $this->load->model('pets_model');
            $pet_ids = $this->db->select('id')->get_where('ea_pets',
                ['id_users' => $customer_id])->result_array();
            if (!empty($pet_ids)) {
                $customer['pets'] = [];
                foreach ($pet_ids as $pet_id)
                    $customer['pets'][$pet_id['id']] = $this->pets_model->get_row($pet_id['id']);
            }
        }

        return $customer;
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
     * @throws Exception If requested customer record does not exist in the database.
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

        $customer = $this->db->get_where('ea_users', ['id' => $customer_id])->row_array();

        return $customer[$field_name];
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
        $this->load->model('pets_model');

        $customers_role_id = $this->get_customers_role_id();

        if ($where_clause != '')
        {
            $this->db->where($where_clause);
        }

        $this->db->where('id_roles', $customers_role_id);

        $batch = $this->db->get('ea_users')->result_array();

        // Get every customer settings.
        foreach ($batch as &$customer)
        {
            $customer['settings'] = $this->db->get_where('ea_user_settings',
                ['id_users' => $customer['id']])->row_array();
            unset($customer['settings']['id_users']);

            $pet_ids = $this->db
                ->select('id')
                ->get_where('ea_pets', ['id_users' => $customer['id']])
                ->result_array();
            if( !empty($pet_ids) ){
                $customer['pets'] = [];
                foreach ($pet_ids as $pet_row)
                    array_push($customer['pets'], $this->pets_model->get_row($pet_row['id']));
            }
        }

        return $batch;
    }

    /**
     * Get the customers role id from the database.
     *
     * @return int Returns the role id for the customer records.
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

        $customer = [ 'email' => $email ];

        if($this->customers_model->exists($customer)){
            $customer_id = $this->customers_model->find_record_id($customer);

            $salt = $this->customers_model->get_salt($customer_id);
            if ( !$salt )
                return NULL;

            $password = hash_password($salt, $password);

            $customer = $this->db
                ->select('ea_users.*')
                ->from('ea_users')
                ->join('ea_user_settings', 'ea_user_settings.id_users = ea_users.id', 'inner')
                ->where('ea_users.email', $email)
                ->group_start()
                    ->where('ea_user_settings.password', $password)
                    ->or_where('ea_user_settings.password', NULL)
                ->group_end()
                ->get()->row_array();

            if (!empty($customer)) 
                $customer['pets'] = $this->db->get_where('ea_pets',
                    ['id_users' => $customer_id])->result_array();

            return $customer;
        }
        else
            return NULL;
    }
}
