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

include('db_conn.php');
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

$input = json_decode(file_get_contents("php://input"), true);

// Sanitize and validate inputs
if (is_array($input)) {
  $fullname = trim($input['fullname'] ?? '');
  $email = trim($input['email'] ?? '');
  $phone = trim($input['phone'] ?? '');
  $password = trim($input['password'] ?? '');
  $vendor_type = trim($input['vendor_type'] ?? ''); // Added vendor_type from $input
  $business_name = trim($input['business_name'] ?? ''); // Added business_name from $input
  $location = trim($input['location'] ?? '');
} else {
  $fullname      = trim($_POST['fullname']);
  $email         = trim($_POST['email']);
  $phone         = trim($_POST['phone']);
  $password      = trim($_POST['password']);
  $vendor_type   = trim($_POST['vendor_type']);
  $business_name = trim($_POST['business_name']);
  $location      = trim($_POST['location']);
}

$rand = rand(1000, 9999);
$apha =  chr(mt_rand(65, 90)) . chr(mt_rand(65, 90));
$aphaa =  chr(rand(65, 90));
$vendor_uin = $aphaa . $rand . "EY";

$_SESSION['email'] = $email;

// Check required fields
if (empty($fullname) || empty($email) || empty($phone) || empty($password)) {
  http_response_code(400);
  echo json_encode(["status" => "error", "message" => "Missing required fields"]);
  exit;
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(400);
  echo json_encode(["status" => "error", "message" => "Invalid email format"]);
  exit;
}

// Check for duplicate vendor
$stmt = $conn->prepare("SELECT id FROM vendors_info WHERE phone = ? OR email = ?");
$stmt->bind_param("ss", $phone, $email);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
  http_response_code(409);
  echo json_encode(["status" => "error", "message" => "Vendor already exists"]);
  $stmt->close();
  exit;
}
$stmt->close();

// File upload functions
function uploadFile($file, $prefix, $uploadDir = "uploads/")
{
  if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) return null;

  $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
  $filename = uniqid($prefix . "_") . "." . $ext;
  $targetPath = $uploadDir . $filename;

  if (!move_uploaded_file($file['tmp_name'], $targetPath)) return null;
  return $filename;
}

function uploadMultipleFiles($files, $prefix, $uploadDir = "uploads/")
{
  $uploadedFiles = [];
  if (!isset($files['name']) || !is_array($files['name'])) return $uploadedFiles;

  foreach ($files['name'] as $index => $name) {
    if ($files['error'][$index] === UPLOAD_ERR_OK) {
      $ext = pathinfo($name, PATHINFO_EXTENSION);
      $filename = uniqid($prefix . "_") . "." . $ext;
      $targetPath = $uploadDir . $filename;

      if (move_uploaded_file($files['tmp_name'][$index], $targetPath)) {
        $uploadedFiles[] = $filename;
      }
    }
  }

  return $uploadedFiles;
}

// Upload files
$workImages  = isset($_FILES['work']) ? uploadMultipleFiles($_FILES['work'], 'work') : [];
$businessDoc = uploadFile($_FILES['business_doc'] ?? null, 'business_doc');
$idDoc       = uploadFile($_FILES['id_doc'] ?? null, 'id_doc');

$workJson = !empty($workImages) ? json_encode($workImages) : '';
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
$veriCode = rand(100000, 999999);

// Generate auth token
$authToken = bin2hex(random_bytes(32)); // 64-character token

// Insert vendor
$stmt = $conn->prepare("INSERT INTO vendors_info (
    user_uin, fullname, email, phone, password, vendor_type, business_name, location,
    work, business_doc, id_doc, veri_code, auth_token, status
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')");

$stmt->bind_param(
  "sssssssssssss",
  $vendor_uin,
  $fullname,
  $email,
  $phone,
  $hashedPassword,
  $vendor_type,
  $business_name,
  $location,
  $workJson,
  $businessDoc,
  $idDoc,
  $veriCode,
  $authToken
);

if (!$stmt->execute()) {
  http_response_code(500);
  echo json_encode(["status" => "error", "message" => "Failed to register vendor"]);
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

// Get last name for personalization
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
$mail->Subject = "Vendor Account Verification";

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
                    <h2 style='color:#0193E0;'>EVENT YAD Vendor Support</h2>
                    <p><strong>Hello $lastname,</strong></p>
                    <p>Thank you for registering as a vendor. Here is your verification code:</p>
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
  "message" => "Vendor registered successfully",
  "auth_token" => $authToken
]);

$conn->close();
exit;
