<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// JSON response
// Handle CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  header("Access-Control-Allow-Origin: *");
  header("Access-Control-Allow-Methods: POST, OPTIONS");
  header("Access-Control-Allow-Headers: Content-Type, Authorization");
  header("Access-Control-Max-Age: 86400");
  http_response_code(204); // No Content
  exit;
}

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");


// Include DB connection
include('db_conn.php');

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

// Read Input (Supports both JSON and form-data)
$input = json_decode(file_get_contents('php://input'), true);

if (is_array($input)) {
  $fullname = trim($input['fullname'] ?? '');
  $phone    = trim($input['phone'] ?? '');
  $email    = trim($input['email'] ?? '');
  $password = trim($input['password'] ?? '');
} else {
  $fullname = trim($_POST['fullname']);
  $phone    = trim($_POST['phone']);
  $email    = trim($_POST['email']);
  $password = trim($_POST['password']);
}

$veriCode = rand(100000, 999999);

// Validate required fields
if (empty($fullname) || empty($phone) || empty($email) || empty($password)) {
  http_response_code(400);
  echo json_encode(["status" => "error", "message" => "Missing required fields"]);
  exit;
}

/* if (empty($fullname) || empty($phone) || empty($email) || empty($password) || !isset($_FILES['passport'])) { */
/*   http_response_code(400); */
/*   echo json_encode(["status" => "error", "message" => "Missing required fields"]); */
/*   exit; */
/* } */

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(400);
  echo json_encode(["status" => "error", "message" => "Invalid email format"]);
  exit;
}

// Check for duplicate
$stmt = $conn->prepare("SELECT id FROM user_info WHERE phone = ? OR email = ?");
$stmt->bind_param("ss", $phone, $email);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
  http_response_code(409);
  echo json_encode(["status" => "error", "message" => "User already exists"]);
  $stmt->close();
  exit;
}
$stmt->close();

// Upload passport image
$uploadDir = "passport/";
$passportFile = $_FILES['passport'];
$extension = pathinfo($passportFile['name'], PATHINFO_EXTENSION);
$passportFilename = uniqid("passport_") . "." . $extension;
$targetPath = $uploadDir . $passportFilename;

if (!move_uploaded_file($passportFile['tmp_name'], $targetPath)) {
  http_response_code(500);
  echo json_encode(["status" => "error", "message" => "Failed to upload passport image"]);
  exit;
}

// Hash password
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
$passportFilename = "passwprf_sdjkan.png";

// Insert user
$stmt = $conn->prepare("INSERT INTO user_info (fullname, phone, email, password, passport, veri_code, status) VALUES (?, ?, ?, ?, ?, ?, 'Pending')");
$veriCode = bin2hex(random_bytes(4));
$stmt->bind_param("ssssss", $fullname, $phone, $email, $hashedPassword, $passportFilename, $veriCode);

if (!$stmt->execute()) {
  http_response_code(500);
  echo json_encode(["status" => "error", "message" => "Failed to register user"]);
  $stmt->close();
  exit;
}
$stmt->close();

// SEND EMAIL
require 'includes/PHPMailer.php';
require 'includes/SMTP.php';
require 'includes/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Extract details for email
$lastname = explode(' ', $fullname)[1] ?? $fullname;

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
$mail->Subject = "Account Verification";

$mail->Body = "
<style>
    body { font-family: 'Roboto', sans-serif; color: #8094ae; font-size: 14px; }
</style>
<center style='width: 100%; background-color: #f5f6fa;'>
    <table width='100%' bgcolor='#f5f6fa'>
        <tr><td style='padding: 40px 0;'>
            <table style='max-width:620px;margin:0 auto;'>
                <tr><td style='text-align:center;'><a href='https://eventyad.com.ng/'><img src='https://live-api.eventyad.com.ng/logo/4.png' height='70'></a></td></tr>
            </table>
            <table style='max-width:620px;margin:0 auto;background:#fff;'>
                <tr><td style='padding:30px;'>
                    <h2 style='color:#0193E0;'>EVENT YAD Support</h2>
                    <p><strong>Hello $lastname,</strong></p>
                    <p>This is your verification code:</p>
                    <br>
                    <p><strong>CODE:</strong> $veriCode</p>
                    <br>
                    <p>If this wasn't you, please contact support immediately.</p>
                    <p><strong>EVENT-YAD</strong></p>
                </td></tr>
            </table>
            <table style='max-width:620px;margin:0 auto; text-align:center;'>
                <tr><td style='padding:25px 20px 0; font-size:13px;'>
                    &copy; 2025 EVENT-YAD. All rights reserved.
                </td></tr>
            </table>
        </td></tr>
    </table>
</center>
";

$mailSent = $mail->send();

http_response_code(201);
echo json_encode([
  "status" => "success",
  "message" => "User registered successfully",
  "email_sent" => $mailSent ? true : false,
  "authCode" => $veriCode
]);

$conn->close();
exit;
