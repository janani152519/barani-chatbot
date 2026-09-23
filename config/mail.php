<?php
/**
 * Mail SMTP Configuration
 */

require_once __DIR__ . '/../core/helpers.php';

return [
    'host' => env('MAIL_HOST', 'smtp.gmail.com'),
    'port' => (int)env('MAIL_PORT', 587),
    'username' => env('MAIL_USERNAME', 'baranihydraluics@gmail.com'),
    'password' => env('MAIL_PASSWORD', 'tjsphfhpzmlgfbtl'),
    'encryption' => env('MAIL_ENCRYPTION', 'tls'),
    'from_address' => env('MAIL_FROM_ADDRESS', 'baranihydraluics@gmail.com'),
    'from_name' => env('MAIL_FROM_NAME', 'Barani Hydraulics')
];
