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
 * SOAP Report Model
 *
 * @package Models
 */
class SOAP_Reports_Model extends CI_Model {
    /**
     * Add a report record to the database.
     *
     * This method adds a report to the database. If the report doesn't exists it is going to be inserted, otherwise
     * the record is going to be updated.
     *
     * @param array $report Associative array with the report's data. Each key has the same name with the database
     * fields.
     *
     * @return int Returns the report id.
     */
    public function add(&$report)
    {
        // Validate the report data before doing anything.
        $this->validate($report);

        // :: INSERT OR UPDATE report RECORD
        if ( ! isset($report['id']))
        {
            $report['id'] = $this->_insert($report);
        }
        else
        {
            $this->_update($report);
        }

        return $report['id'];
    }

    /**
     * Insert a new report record to the database.
     *
     * @param array $report Associative array with the report's data. Each key has the same name with the database
     * fields.
     *
     * @return int Returns the id of the new record.
     *
     * @throws Exception If report record could not be inserted.
     */
    protected function _insert($report)
    {
        if ( ! $this->db->insert('ea_soap_reports', $report))
        {
            throw new Exception('Could not insert report to the database.');
        }

        $report['id'] = (int)$this->db->insert_id();
        
        return (int)$report['id'];
    }

    /**
     * Update an existing report record in the database.
     *
     * The report data argument should already include the record ID in order to process the update operation.
     *
     * @param array $report Associative array with the report's data. Each key has the same name with the database
     * fields.
     *
     * @return int Returns the updated record ID.
     *
     * @throws Exception If report record could not be updated.
     */
    protected function _update($report)
    {

        // Do not update empty string values.
        foreach ($report as $key => $value)
        {
            if ($value === '')
            {
                unset($report[$key]);
            }
        }

        $this->db->where('id', $report['id']);
        if ( ! $this->db->update('ea_soap_reports', $report))
        {
            throw new Exception('Could not update report to the database.');
        }

        return (int)$report['id'];
    }

    /**
     * Validate report data before the insert or update operation is executed.
     *
     * @param array $report Contains the report data.
     *
     * @return bool Returns the validation result.
     *
     * @throws Exception If report validation fails.
     */
    public function validate($report)
    {
        $this->load->helper('data_validation');

        // If a report id is provided, check whether the record
        // exist in the database.
        if (isset($report['id']))
        {
            $num_rows = $this->db->get_where('ea_soap_reports',
                ['id' => $report['id']])->num_rows();
            if ($num_rows == 0)
            {
                throw new Exception('Provided report id does not '
                    . 'exist in the database.');
            }
        }
        // Validate required fields
        if ( ! isset($report['date']) )
        {
            throw new Exception('Not all required fields are provided: '
                . print_r($report, TRUE));
        }

        return TRUE;
    }

    /**
     * Delete an existing report record from the database.
     *
     * @param int $report_id The record id to be deleted.
     *
     * @return bool Returns the delete operation result.
     *
     * @throws Exception If $report_id argument is invalid.
     */
    public function delete($report_id)
    {
        if ( ! is_numeric($report_id))
        {
            throw new Exception('Invalid argument type $report_id: ' . $report_id);
        }

        $num_rows = $this->db->get_where('ea_soap_reports', ['id' => $report_id])->num_rows();
        if ($num_rows == 0)
        {
            return FALSE;
        }

        return $this->db->delete('ea_soap_reports', ['id' => $report_id]);
    }

    /**
     * Get a specific row from the reports table.
     *
     * @param int $report_id The record's id to be returned.
     *
     * @return array Returns an associative array with the selected record's data. Each key has the same name as the
     * database field names.
     *
     * @throws Exception If $report_id argumnet is invalid.
     */
    public function get_row($report_id)
    {
        if ( ! is_numeric($report_id))
        {
            throw new Exception('Invalid argument provided as $report_id : ' . $report_id);
        }

        $report = $this->db->get_where('ea_soap_reports', ['id' => $report_id])->row_array();

        return $report;
    }

    /**
     * Get all reports for the pet.
     *
     * @param int $pet_id
     *
     * @return array Returns the rows from the database.
     */
    public function get_reports($pet_id)
    {
        $batch = $this->db->get_where('ea_soap_reports', ['id_pets' => $pet_id])->result_array();

        return $batch;
    }
}
