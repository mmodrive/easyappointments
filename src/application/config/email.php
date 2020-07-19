<?php defined('BASEPATH') OR exit('No direct script access allowed');

// Add custom values by settings them to the $config array.
// Example: $config['smtp_host'] = 'smtp.gmail.com'; 
// @link https://codeigniter.com/user_guide/libraries/email.html

$config['useragent'] = 'Easy!Appointments';
$config['protocol'] = defined('Config::MAIL_PROTOCOL') ? Config::MAIL_PROTOCOL : 'mail'; // or 'smtp'
$config['mailtype'] = defined('Config::MAIL_TYPE') ? Config::MAIL_TYPE : 'html'; // or 'text'
$config['smtp_host'] = defined('Config::MAIL_SMTP_HOST') ? Config::MAIL_SMTP_HOST : '';
$config['smtp_user'] = defined('Config::MAIL_SMTP_USER') ? Config::MAIL_SMTP_USER : ''; 
$config['smtp_pass'] = defined('Config::MAIL_SMTP_PASS') ? Config::MAIL_SMTP_PASS : '';
$config['smtp_crypto'] = defined('Config::MAIL_SMTP_CRYPTO') ? Config::MAIL_SMTP_CRYPTO : 'tls'; // or 'tls'
$config['smtp_port'] = defined('Config::MAIL_SMTP_PORT') ? Config::MAIL_SMTP_PORT : '587';
