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

namespace EA\Engine\Notifications;

use \EA\Engine\Types\NonEmptyText;

/**
 * Email Notifications Class
 *
 * This library handles all the notification email deliveries on the system.
 *
 * Important: The email configuration settings are located at: /application/config/email.php
 */
class SMS {
    /**
     * Framework Instance
     *
     * @var CI_Controller
     */
    protected $framework;

    /**
     * Contains email configuration.
     *
     * @var array
     */
    protected $config;

    /**
     * Class Constructor
     *
     * @param \CI_Controller $framework
     * @param array $config Contains the email configuration to be used.
     */
    public function __construct(\CI_Controller $framework, array $config)
    {
        $this->framework = $framework;
        $this->config = $config;
    }

    public function sendText(
        \stdClass $notification,
        NonEmptyText $recipient
    ) {

        function callAPI($content) {
            $ch = curl_init('https://api.smsbroadcast.com.au/api-adv.php');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $output = curl_exec ($ch);
            curl_close ($ch);
            return $output;    
        }

        $ref = md5(uniqid(mt_rand(), true));
        $content =  'username='.rawurlencode($this->config['sms_username']).
                    '&password='.rawurlencode($this->config['sms_password']).
                    '&to='.rawurlencode($recipient->get()).
                    '&from='.rawurlencode($this->config['sms_sender']).
                    '&message='.rawurlencode($notification->body).
                    '&ref='.rawurlencode($ref);
      
        $smsbroadcast_response = callAPI($content);
        $response_lines = explode("\n", $smsbroadcast_response);
        
        foreach( $response_lines as $data_line){
            $message_data = "";
            $message_data = explode(':',$data_line);
            if($message_data[0] == "OK"){
            }elseif( $message_data[0] == "BAD" ){
                throw new \RuntimeException("The message to ".$message_data[1]." was NOT successful. Reason: ".$message_data[2]);
            }elseif( $message_data[0] == "ERROR" ){
                throw new \RuntimeException("There was an error with this request. Reason: ".$message_data[1]);
            }
        }

        return $ref;
    }
}
