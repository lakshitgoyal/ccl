<?php

// All presentation pages live inside /api.
// This allows them to continue using:
// include 'header.php';
// include 'footer.php';

chdir(__DIR__);

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = rtrim($path, '/');

if ($path === '') {
    $path = '/index.php';
}

$pages = [
    '/index.php'                 => 'index.php',
    '/about.php'                 => 'about.php',
    '/contact.php'               => 'contact.php',
    '/default.php'               => 'default.php',
    '/demo.php'                  => 'demo.php',
    '/form.php'                  => 'form.php',
    '/gallery.php'               => 'gallery.php',
    '/login.php'                 => 'login.php',
    '/mandatory-documents.php'   => 'mandatory-documents.php',
    '/organisers.php'            => 'organisers.php',
    '/pay-fee.php'               => 'pay-fee.php',
    '/portfolio.php'             => 'portfolio.php',
    '/privacy-policy.php'        => 'privacy-policy.php',
    '/refund-policy.php'         => 'refund-policy.php',
    '/registration-policies.php' => 'registration-policies.php',
    '/registration.php'          => 'registration.php',
    '/registration2.php'         => 'registration2.php',
    '/review.php'                => 'review.php',
    '/selection-process.php'     => 'selection-process.php',
    '/state-league.php'          => 'state-league.php',
    '/team.php'                  => 'team.php',
    '/terms-conditions.php'      => 'terms-conditions.php',
    '/thank-you.php'             => 'thank-you.php',
    '/trials.php'                => 'trials.php',
    '/videos.php'                => 'videos.php',
    '/payment-failed.php'        => 'payment-failed.php'
];

if (!isset($pages[$path])) {
    http_response_code(404);
    echo 'Page not found';
    exit;
}

$file = __DIR__ . '/' . $pages[$path];

if (!file_exists($file)) {
    http_response_code(404);
    echo 'Page not found';
    exit;
}

require $file;