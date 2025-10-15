<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  header("Access-Control-Max-Age: 86400");
  http_response_code(204);
  exit;
}

// Include DB connection
include('db_conn.php');

// PHPMailer
require 'includes/PHPMailer.php';
require 'includes/SMTP.php';
require 'includes/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

session_start();

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

// Client details
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
// use consistent 24h format or 12h + am/pm. Here we use 24h.
$currentDateTime = date('Y-m-d H:i:s');

$raw = file_get_contents("php://input");
$input = json_decode($raw, true);

// Get and sanitize input safely
if (is_array($input)) {
  $email = trim($input['email'] ?? '');
  $password = trim($input['password'] ?? '');
} else {
  // fallback to form data
  $email = trim($_POST['email'] ?? '');
  $password = trim($_POST['password'] ?? '');
}

// Validate input
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

// Check user using standard bind_result + fetch
$stmt = $conn->prepare("SELECT id, password FROM user_info WHERE email = ?");
if (!$stmt) {
  http_response_code(500);
  echo json_encode(["status" => "error", "message" => "Prepare failed: " . $conn->error]);
  exit;
}

$stmt->bind_param("s", $email);

if (!$stmt->execute()) {
  http_response_code(500);
  echo json_encode(["status" => "error", "message" => "Execute failed: " . $stmt->error]);
  $stmt->close();
  exit;
}

// Use bind_result for portability
$stmt->bind_result($user_id, $password_hash);

if (!$stmt->fetch()) {
  // no row
  http_response_code(401);
  echo json_encode(["status" => "error", "message" => "Invalid email or password"]);
  $stmt->close();
  exit;
}

$stmt->close();

// Verify password
if (!password_verify($password, $password_hash)) {
  http_response_code(401);
  echo json_encode(["status" => "error", "message" => "Invalid email or password"]);
  exit;
}

// Set session
$_SESSION['email'] = $email;
$_SESSION['user_id'] = $user_id;

// Send email notification (wrap in try/catch to capture PHPMailer errors)
$mail = new PHPMailer(true);
try {
  $mail->isSMTP();
  $mail->Host       = "eventyad.com.ng";
  $mail->SMTPAuth   = true;
  $mail->SMTPSecure = "ssl";
  $mail->Port       = 465;
  $mail->Username   = "support@eventyad.com.ng";
  $mail->Password   = "8Xe)w3spwX,aTnZ_"; // move this to env in production
  $mail->setFrom('support@eventyad.com.ng', 'FD Mobile Support');
  $mail->addAddress($email);
  $mail->isHTML(true);
  $mail->CharSet = 'UTF-8';
  $mail->Subject = "Login Notification";

  $mail->Body = "
    <style>
    html, body { font-family: 'Roboto', sans-serif; font-size: 14px; line-height: 24px; color: #8094ae; background-color: #f5f6fa; }
    </style>
    <center>
      <table style='max-width:620px;background:#fff;padding:30px;border-radius:8px;'>
        <tr><td style='text-align:center;padding-bottom:20px;'>
            <a href='https://fdmobile.ng'><img src='https://fdmobile.ng/logo/FD-MOBILE_BLUE.png' height='60' alt='FD Mobile'></a>
        </td></tr>
        <tr><td>
            <h2 style='color:#0193E0;'>Login Notification</h2>
            <p>Hello,</p>
            <p>A login to your FD Mobile account was detected:</p>
            <p><strong>Device:</strong> {$userAgent}</p>
            <p><strong>IP Address:</strong> {$ipAddress}</p>
            <p><strong>Date & Time:</strong> {$currentDateTime}</p>
            <p>If this was not you, reset your password or contact support immediately.</p>
            <br><p>Best regards,<br><strong>FD MOBILE SUPPORT</strong></p>
        </td></tr>
        <tr><td style='text-align:center;font-size:12px;color:#aaa;padding-top:30px;'>&copy; 2024 FD Mobile. All rights reserved.</td></tr>
      </table>
    </center>
    ";

  $mailSent = $mail->send();
} catch (Exception $e) {
  // log error in real app
  $mailSent = false;
}

// Success response
http_response_code(200);
echo json_encode([
  "status" => "success",
  "message" => "Login successful",
  "user_id" => $user_id,
  "email_sent" => $mailSent,
  "ip_address" => $ipAddress,
  "device" => $userAgent,
  "logged_in_at" => $currentDateTime
]);

$conn->close();
exit;
