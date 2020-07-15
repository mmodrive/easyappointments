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
class Attachments_Model extends CI_Model {
    /**
     * Delete an existing attachment record from the database.
     *
     * @param int $attachment_id The record id to be deleted.
     *
     * @return bool Returns the delete operation result.
     *
     * @throws Exception If $attachment_id argument is invalid.
     */
    public function delete($attachment_id)
    {
        if ( ! is_numeric($attachment_id))
        {
            throw new Exception('Invalid argument type $attachment_id: ' . $attachment_id);
        }

        $num_rows = $this->db->get_where('ea_attachments', ['id' => $attachment_id])->num_rows();
        if ($num_rows == 0)
        {
            return FALSE;
        }

        return $this->db->delete('ea_attachments', ['id' => $attachment_id]);
    }

    /**
     * Get a specific row from the appointments table.
     *
     * @param int $attachment_id The record's id to be returned.
     *
     * @return array Returns an associative array with the selected record's data. Each key has the same name as the
     * database field names.
     *
     * @throws Exception If $attachment_id argumnet is invalid.
     */
    public function get_row($attachment_id)
    {
        if ( ! is_numeric($attachment_id))
        {
            throw new Exception('Invalid argument provided as $attachment_id : ' . $attachment_id);
        }

        $attachment = $this->db->get_where('ea_attachments', ['id' => $attachment_id])->row_array();

        return $attachment;
    }
}
