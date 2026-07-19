<?php

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// --- 1. Initial Setup and Logging ---
error_log("[PROCESS-REGISTRATION] Script execution started. Request Method: " . $_SERVER['REQUEST_METHOD']);

header('Content-Type: application/json');

require dirname(__DIR__) . '/vendor/autoload.php';
include __DIR__ . '/../razorpay-config.php';

$configFile = dirname(__DIR__) . '/config/config.php';
if (!file_exists($configFile)) {
    $errorMsg = "[PROCESS-REGISTRATION] CRITICAL ERROR: Configuration file not found at " . $configFile;
    error_log($errorMsg);
    // Use die() here since we can't send a JSON response without configuration
    die('<p style="color: red;">CRITICAL ERROR: Configuration file not found. Please create config.php.</p>');
}
$config = require $configFile;
error_log("[PROCESS-REGISTRATION] Configuration loaded successfully.");

use Razorpay\Api\Api;
use Razorpay\Api\Errors\Error as RazorpayError;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

use Google\Client as GoogleClient;
use Google\Service\Sheets;
use Google\Service\Sheets\ValueRange;

// --- 2. Request Method and Input Validation ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    error_log("[PROCESS-REGISTRATION] Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit();
}

error_log("[PROCESS-REGISTRATION] Starting input validation. Received POST data: " . json_encode($_POST));
$required_fields = ['name', 'age', 'phone', 'email', 'speciality', 'state', 'city'];
foreach ($required_fields as $field) {
    if (empty($_POST[$field])) {
        http_response_code(400);
        $errorMessage = "Field '$field' is required.";
        error_log("[PROCESS-REGISTRATION] Validation failed: " . $errorMessage);
        echo json_encode(['error' => $errorMessage]);
        exit();
    }
}
error_log("[PROCESS-REGISTRATION] All required fields are present.");

// --- 3. Sanitize Input Data & Generate ID ---
$regId = 'CCLREG' . date('YmdHis') . rand(100, 999); // Added random element for better uniqueness
$name = htmlspecialchars(trim($_POST['name']), ENT_QUOTES, 'UTF-8');
$age = htmlspecialchars(trim($_POST['age']), ENT_QUOTES, 'UTF-8');
$phone = htmlspecialchars(trim($_POST['phone']), ENT_QUOTES, 'UTF-8');
$email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
$speciality = htmlspecialchars(trim($_POST['speciality']), ENT_QUOTES, 'UTF-8');
$state = htmlspecialchars(trim($_POST['state']), ENT_QUOTES, 'UTF-8');
$city = htmlspecialchars(trim($_POST['city']), ENT_QUOTES, 'UTF-8');
$couponCode = isset($_POST['c-code']) && trim($_POST['c-code']) !== '' ? htmlspecialchars(trim($_POST['c-code']), ENT_QUOTES, 'UTF-8') : 'N/A';
$sanitizedData = compact('regId', 'name', 'age', 'phone', 'email', 'speciality', 'state', 'city', 'couponCode');
error_log("[{$regId}] Input data sanitized successfully.");

// --- 3b. Google Sheets Logging (via Apps Script webhook) ---
// Isolated in its own try-catch so a Sheets failure never blocks email or payment.
try {
    error_log("[{$regId}] Sending data to Google Sheets webhook.");

    $payload = json_encode([
        'regId'      => $regId,
        'date'       => date('d-M-Y H:i:s'),
        'name'       => $name,
        'age'        => $age,
        'phone'      => $phone,
        'email'      => $email,
        'speciality' => $speciality,
        'state'      => $state,
        'city'       => $city,
        'couponCode' => $couponCode,
    ]);

    $ch = curl_init($config['sheets_webhook_url']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CAINFO, $config['cacert_path']);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);   // <-- add this
    curl_setopt($ch, CURLOPT_MAXREDIRS, 3);           

    $result = curl_exec($ch);

    if (curl_errno($ch)) {
        error_log("[{$regId}] WARNING: Sheets webhook cURL error: " . curl_error($ch));
    } else {
        error_log("[{$regId}] Sheets webhook responded: " . $result);
    }
    curl_close($ch);

} catch (\Exception $e) {
    error_log("[{$regId}] WARNING: Sheets webhook logging failed. Error: " . $e->getMessage());
}

// --- 4. PHPMailer Email Sending ---
// This is in its own try-catch so an email failure doesn't stop the payment process.
try {
    $mail = new PHPMailer(true);
    // SMTP Configuration
    $mail->isSMTP();
    $mail->Host = $config['smtp']['host'];
    $mail->SMTPAuth = true;
    $mail->Username = $config['smtp']['username'];
    $mail->Password = $config['smtp']['password'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = $config['smtp']['port'];

    // Send notification to ADMIN
    error_log("[{$regId}] Attempting to send admin notification to " . $config['email']['admin_email']);
    $mail->setFrom($config['email']['from_address'], $config['email']['from_name']);
    $mail->addAddress($config['email']['admin_email']);
    $mail->addReplyTo($email, $name);
    $mail->isHTML(true);
    $mail->Subject = 'New Cricket League Registration - ' . $name;
    $mail->Body = '
        <h3 style="text-align: center;">New Registration Enquiry</h3>
        <table border="1" width="100%" cellpadding="8" cellspacing="0" style="border-collapse: collapse;">
            <tr><td style="width:30%;"><strong>Registration ID</strong></td><td>' . $regId . '</td></tr>
            <tr><td><strong>Name</strong></td><td>' . $name . '</td></tr>
            <tr><td><strong>Age</strong></td><td>' . $age . '</td></tr>
            <tr><td><strong>Mobile</strong></td><td>' . $phone . '</td></tr>
            <tr><td><strong>Email</strong></td><td>' . $email . '</td></tr>
            <tr><td><strong>Specialty</strong></td><td>' . $speciality . '</td></tr>
            <tr><td><strong>State</strong></td><td>' . $state . '</td></tr>
            <tr><td><strong>City</strong></td><td>' . $city . '</td></tr>
            <tr><td><strong>Coupon Code</strong></td><td>' . $couponCode . '</td></tr>
            <tr><td><strong>Date</strong></td><td>' . date('d-M-Y H:i:s') . '</td></tr>
        </table>';
    $mail->AltBody = "Registration Details:\nID: $regId\nName: $name\nAge: $age\nMobile: $phone\nEmail: $email\nSpecialty: $speciality\nState: $state\nCity: $city\nCoupon: $couponCode";
    $mail->send();
    error_log("[{$regId}] Admin notification email sent successfully.");

    // Send confirmation to USER
    error_log("[{$regId}] Attempting to send user confirmation to " . $email);
    $mail->clearAddresses();
    $mail->clearReplyTos();
    $mail->addAddress($email, $name);
    $mail->Subject = 'Your Registration for Champion Cricket League';
    $mail->Body = "<h3>Hi {$name},</h3><p>Thank you for registering. Your registration ID is: <strong>{$regId}</strong>. Please proceed with the payment to complete the process.</p>";
    $mail->send();
    error_log("[{$regId}] User confirmation email sent successfully.");

} catch (PHPMailerException $e) {
    // Log the email error but don't stop the script. The user can still pay.
    error_log("[{$regId}] WARNING: PHPMailer failed to send email. Error: " . $e->errorMessage() . ". The process will continue to payment.");
}

// --- 5. Main Payment Processing Logic ---
// This is the critical part. If this fails, the user cannot proceed.
try {
    // --- Calculate Amount and Create Razorpay Order ---
    error_log("[{$regId}] Calculating amount for specialty: '{$speciality}'.");
    $amount_in_inr = 0;
    switch ($speciality) {
        case 'Batsman':
        case 'Bowler':
            $amount_in_inr = 799; // Preserved your updated price
            break;
        case 'All Rounder':
        case 'Wicketkeeper':
            $amount_in_inr = 1199; // Preserved your updated price
            break;
        default:
            // This is a data integrity issue, should be a hard failure.
            throw new Exception('Invalid specialty selected: ' . $speciality);
    }
    $amount_in_paise = $amount_in_inr * 100;
    error_log("[{$regId}] Amount calculated. INR: {$amount_in_inr}, Paise: {$amount_in_paise}.");

    $api = new Api(KEY_ID, KEY_SECRET);
    $orderData = [
        'receipt'         => $regId,
        'amount'          => $amount_in_paise,
        'currency'        => 'INR',
        'payment_capture' => 1
    ];
    error_log("[{$regId}] Creating Razorpay order with data: " . json_encode($orderData));
    $razorpayOrder = $api->order->create($orderData);
    error_log("[{$regId}] Razorpay order created successfully. Order ID: " . $razorpayOrder['id']);

    // --- Prepare and Send Success Response ---
    $response_data = [
        "status"       => "success",
        "razorpay_key" => KEY_ID,
        "order_id"     => $razorpayOrder['id'],
        "amount"       => $amount_in_paise,
        "currency"     => "INR",
        "name"         => "Champion Cricket League",
        "description"  => "Trial Registration Fees",
        "prefill"      => [
            "name"     => $name,
            "email"    => $email,
            "contact"  => $phone,
        ],
    ];

    error_log("[{$regId}] Sending success response to client for Order ID: {$razorpayOrder['id']}.");
    echo json_encode($response_data);
    exit;

} catch (RazorpayError $e) {
    http_response_code(500);
    $errorMsg = "A Razorpay API error occurred. HTTP Status: " . $e->getStatusCode() . ". Code: " . $e->getCode() . ". Message: " . $e->getMessage();
    error_log("[{$regId}] CRITICAL ERROR: " . $errorMsg . " | Data: " . json_encode($sanitizedData));
    echo json_encode(['error' => 'Payment gateway error. Please try again in a few moments.']);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    error_log("[{$regId}] CRITICAL ERROR: An unexpected exception occurred. Message: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() . " | Data: " . json_encode($sanitizedData));
    echo json_encode(['error' => 'An unexpected error occurred. Our team has been notified. Please try again later.']);
    exit;
}