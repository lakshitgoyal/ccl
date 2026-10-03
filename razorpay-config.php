<?php
//razorpay-config.php

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require __DIR__ . '/vendor/autoload.php';

# Production
const KEY_ID = 'rzp_live_Rjr4iKbNEyNeiv';
const KEY_SECRET = '01u4qsoauqc6D1aEiklOf4M6';

# Testing
// const KEY_ID = 'rzp_test_FvWVeiMWly74xf';
// const KEY_SECRET = 'dIlnytsaZhi575INukrM5MNo';

// Error reporting for development
ini_set('display_errors', 1);
error_reporting(E_ALL);

use Razorpay\Api\Api;

$api = new Api(KEY_ID, KEY_SECRET);
?>