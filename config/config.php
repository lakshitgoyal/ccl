<?php

return [
    'smtp' => [
        'host'      => 'smtp.gmail.com',
        'port'      => 587,
        'username'  => 'khelbharat2026@gmail.com',
        'password'  => 'gzid fmgh nefi kbqr',
    ],

    'google_sheets' => [
        'credentials_path' => dirname(__DIR__) . '/credentials.json',
        'spreadsheet_id'   => '1CV06Zv0bE47aFHV9lsGRoIZCdHUhHGgj62P0M4GRcAw',
        ],
        
        'sheets_webhook_url' => 'https://script.google.com/macros/s/AKfycbxt5oIdemVItc2tPpgIzPliKEeOmHhtzUeUyJwiGgSMoxKRxKwqjvo0mh-eLQYJSGxx/exec',
        'cacert_path'        => dirname(__DIR__) . '/certs/cacert.pem',
    'email' => [
        'from_address' => 'khelbharat2026@gmail.com', // Email sending notifications
        'from_name'    => 'Champion Cricket League',
        'admin_email'  => 'ccl19175131@gmail.com', // Where to send notifications
    ]
];