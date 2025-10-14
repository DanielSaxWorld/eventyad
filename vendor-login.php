<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Handle CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    header("Access-Control-Max-Age: 86400");
    http_response_code(204);
    exit;
}

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// DB connection
include('db_conn.php');

// Include PHPMailer
require 'includes/PHPMailer.php';
require 'includes/SMTP.php';
require 'includes/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!$conn) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Only POST method allowed"]);
    exit;
}

// Get request data
$email    = trim($_POST['email']);
$password = trim($_POST['password']);

// Validate
if (empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Missing required fields"]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid email format"]);
    exit;
}

// Get user from DB
$stmt = $conn->prepare("SELECT id, fullname, email, password, status FROM vendors_info WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Invalid email or password"]);
    $stmt->close();
    exit;
}

$user = $result->fetch_assoc();
$stmt->close();

// Verify password
if (!password_verify($password, $user['password'])) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Invalid email or password"]);
    exit;
}

// Capture client info
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
$currentDateTime = date('Y-m-d H:i a');

// Case 1: Status = Pending → Send 6-digit code
if ($user['status'] === 'Pending') {
    $veriCode = rand(100000, 999999);

    // Update verification code in DB
    $updateStmt = $conn->prepare("UPDATE vendors_info SET veri_code = ? WHERE email = ?");
    $updateStmt->bind_param("ss", $veriCode, $email);
    $updateStmt->execute();
    $updateStmt->close();

    // Get last name
    $fullname = $user['fullname'];
    $lastname = explode(' ', $fullname)[1] ?? $fullname;

    // Send verification email
    $mail = new PHPMailer();
    $mail->isSMTP();
    $mail->Host = "eventyad.com.ng";
    $mail->SMTPAuth = true;
    $mail->SMTPSecure = "ssl";
    $mail->Port = 465;
    $mail->Username = "support@eventyad.com.ng";
    $mail->Password = "8Xe)w3spwX,aTnZ_";
    $mail->setFrom('support@eventyad.com.ng', 'Verification Code');
    $mail->addAddress($email);
    $mail->isHTML(true);
    $mail->Subject = "Vendor Account Verification";

    $mail->Body = "
    <style>body { font-family: 'Roboto', sans-serif; color: #8094ae; font-size: 14px; }</style>
    <center style='width: 100%; background-color: #f5f6fa;'>
        <table width='100%' bgcolor='#f5f6fa'>
            <tr><td style='padding: 40px 0;'>
                <table style='max-width:620px;margin:0 auto;'><tr>
                    <td style='text-align:center;'>
                        <a href='https://eventyad.com.ng/'>
                            <img src='https://live-api.eventyad.com.ng/logo/4.png' height='70'>
                        </a>
                    </td></tr>
                </table>
                <table style='max-width:620px;margin:0 auto;background:#fff;'>
                    <tr><td style='padding:30px;'>
                        <h2 style='color:#0193E0;'>EVENT YAD Vendor Support</h2>
                        <p><strong>Hello $lastname,</strong></p>
                        <p>Thank you for logging in. Here is your verification code:</p>
                        <p><strong>CODE:</strong> $veriCode</p>
                        <p>Please verify your account to continue.</p>
                        <p><strong>EVENT-YAD</strong></p>
                    </td></tr>
                </table>
                <table style='max-width:620px;margin:0 auto;text-align:center;'>
                    <tr><td style='padding:25px 20px 0; font-size:13px;'>&copy; 2025 EVENT-YAD. All rights reserved.</td></tr>
                </table>
            </td></tr>
        </table>
    </center>
    ";

    $mail->send();

    http_response_code(200);
    echo json_encode([
        "status" => "validate",
        "message" => "Validate your account. A verification code has been sent.",
        "email" => $email
    ]);
    $conn->close();
    exit;
}

// Case 2: Active user — generate auth token and login
$authToken = bin2hex(random_bytes(32));

// Update auth token
$updateToken = $conn->prepare("UPDATE vendors_info SET auth_token = ? WHERE email = ?");
$updateToken->bind_param("ss", $authToken, $email);
$updateToken->execute();
$updateToken->close();

// Send login notification email
$mail = new PHPMailer();
$mail->isSMTP();
$mail->Host = "eventyad.com.ng";
$mail->SMTPAuth = true;
$mail->SMTPSecure = "ssl";
$mail->Port = 465;
$mail->Username = "support@eventyad.com.ng";
$mail->Password = "8Xe)w3spwX,aTnZ_";
$mail->setFrom('support@eventyad.com.ng', 'Event-Yad Support');
$mail->addAddress($email);
$mail->isHTML(true);
$mail->Subject = "Login Notification";
$mail->Body = "
<center>
    <table style='max-width: 620px; background-color: #ffffff; padding: 30px; border-radius: 8px;'>
        <tr>
            <td style='text-align: center;'>
                <a href='https://fdmobile.ng'><img src='https://fdmobile.ng/logo/FD-MOBILE_BLUE.png' height='60'></a>
            </td>
        </tr>
        <tr>
            <td>
                <h2 style='color: #0193E0;'>Login Notification</h2>
                <p>Hello,</p>
                <p>A login to Event-Yad account was detected:</p>
                <p><strong>Device:</strong> $userAgent</p>
                <p><strong>IP Address:</strong> $ipAddress</p>
                <p><strong>Date & Time:</strong> $currentDateTime</p>
                <p>If this was not you, please reset your password or contact support.</p>
                <p>Best regards,<br><strong>EVENT-YAD</strong></p>
            </td>
        </tr>
        <tr>
            <td style='text-align: center; font-size: 12px; color: #aaa;'>
                &copy; 2025 EVENT-YAD. All rights reserved.
            </td>
        </tr>
    </table>
</center>
";

$mail->send();

// Final response
http_response_code(200);
echo json_encode([
    "status" => "success",
    "message" => "Login successful",
    "auth_token" => $authToken,
    "user_id" => $user['id'],
    "email" => $email,
    "ip_address" => $ipAddress,
    "device" => $userAgent,
    "logged_in_at" => $currentDateTime
]);

$conn->close();
exit;
?>
