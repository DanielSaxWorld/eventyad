<?php
require_once '../../db/connection.php';
require_once '../../helpers/file_upload.php';
require_once '../../helpers/response.php';
require_once '../../helpers/send_mail.php';

// CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    cors();
    exit;
}
cors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(["error" => "Only POST allowed"], 405);
}

// Input (Assumes application/json or form-data)
$fullname      = trim($_POST['fullname'] ?? '');
$email         = trim($_POST['email'] ?? '');
$phone         = trim($_POST['phone'] ?? '');
$password      = trim($_POST['password'] ?? '');
$vendor_type   = trim($_POST['vendor_type'] ?? '');
$business_name = trim($_POST['business_name'] ?? '');
$location      = trim($_POST['location'] ?? '');

// Validation
if (!$fullname || !$email || !$phone || !$password) {
    send_json(["error" => "Missing required fields"], 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    send_json(["error" => "Invalid email format"], 400);
}

// Duplicate Check
$stmt = $conn->prepare("SELECT id FROM vendors_info WHERE email = ? OR phone = ?");
$stmt->bind_param("ss", $email, $phone);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    send_json(["error" => "Vendor already exists"], 409);
}
$stmt->close();

// Generate values
$vendor_uin = chr(rand(65, 90)) . rand(1000, 9999) . "EY";
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
$veriCode = rand(100000, 999999);
$authToken = bin2hex(random_bytes(32));

// Uploads
$workImages  = uploadMultipleFiles($_FILES['work'] ?? null, 'work');
$businessDoc = uploadFile($_FILES['business_doc'] ?? null, 'business_doc');
$idDoc       = uploadFile($_FILES['id_doc'] ?? null, 'id_doc');

// Save
$stmt = $conn->prepare("INSERT INTO vendors_info (
    user_uin, fullname, email, phone, password, vendor_type,
    business_name, location, work, business_doc, id_doc,
    veri_code, auth_token, status
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')");

$workJson = json_encode($workImages);
$stmt->bind_param("sssssssssssss", $vendor_uin, $fullname, $email, $phone, $hashedPassword,
    $vendor_type, $business_name, $location, $workJson, $businessDoc, $idDoc,
    $veriCode, $authToken);

if (!$stmt->execute()) {
    send_json(["error" => "Failed to save vendor"], 500);
}
$stmt->close();

// Email
$mailSuccess = sendVerificationEmail($email, $fullname, $veriCode);

send_json([
    "message" => "Vendor registered successfully",
    "auth_token" => $authToken,
    "email_sent" => $mailSuccess
], 201);