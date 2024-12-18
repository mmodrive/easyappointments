<?php

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Open Source Web Scheduler
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) 2013 - 2018, Alex Tselegidis
 * @license     http://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        http://easyappointments.org
 * @since       v1.2.0
 * ---------------------------------------------------------------------------- */

namespace EA\Engine\Api\V1\Parsers;

/**
 * Customers Parser
 *
 * This class will handle the encoding and decoding from the API requests.
 */
class Customers implements ParsersInterface {
    /**
     * Encode Response Array
     *
     * @param array &$response The response to be encoded.
     */
    public function encode(array &$response)
    {
        $encodedResponse = [
            'id' => $response['id'] !== NULL ? (int)$response['id'] : NULL,
            'firstName' => $response['first_name'],
            'lastName' => $response['last_name'],
            'email' => $response['email'],
            'phone' => $response['phone_number'],
            'address' => $response['address'],
            'city' => $response['city'],
            'zip' => $response['zip_code'],
            'disc_qualify' => $response['disc_qualify'],
            'marketing_subscribe' => $response['marketing_subscribe'],
            'notes' => $response['notes']
        ];

        $response = $encodedResponse;
    }

    /**
     * Decode Request
     *
     * @param array &$request The request to be decoded.
     * @param array $base Optional (null), if provided it will be used as a base array.
     */
    public function decode(array &$request, array $base = NULL)
    {
        $decodedRequest = $base ?: [];

        if ( ! empty($request['id']))
        {
            $decodedRequest['id'] = $request['id'];
        }

        if ( ! empty($request['firstName']))
        {
            $decodedRequest['first_name'] = $request['firstName'];
        }

        if ( ! empty($request['lastName']))
        {
            $decodedRequest['last_name'] = $request['lastName'];
        }

        if ( ! empty($request['email']))
        {
            $decodedRequest['email'] = $request['email'];
        }

        if ( ! empty($request['phone']))
        {
            $decodedRequest['phone_number'] = $request['phone'];
        }

        if ( ! empty($request['address']))
        {
            $decodedRequest['address'] = $request['address'];
        }

        if ( ! empty($request['city']))
        {
            $decodedRequest['city'] = $request['city'];
        }

        if ( ! empty($request['zip']))
        {
            $decodedRequest['zip_code'] = $request['zip'];
        }

        if ( ! empty($request['disc_qualify']))
        {
            $decodedRequest['disc_qualify'] = $request['disc_qualify'];
        }

        if ( ! empty($request['marketing_subscribe']))
        {
            $decodedRequest['marketing_subscribe'] = $request['marketing_subscribe'];
        }

        if ( ! empty($request['notes']))
        {
            $decodedRequest['notes'] = $request['notes'];
        }

        $request = $decodedRequest;
    }
}
