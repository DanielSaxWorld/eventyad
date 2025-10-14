<?php
require_once '../../db/connection.php';
require_once '../../helpers/response.php';
require_once '../../helpers/send_verification_mail.php';

// CORS handling
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    cors();
    exit;
}
cors();

// Only allow POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(["error" => "Only POST method is allowed"], 405);
}

// Get Authorization header
$headers = getallheaders();
$authHeader = isset($headers['Authorization']) ? trim($headers['Authorization']) : null;

$authToken = null;
if ($authHeader) {
    if (stripos($authHeader, 'Bearer ') === 0) {
        $authToken = trim(substr($authHeader, 7)); // Remove "Bearer "
    } else {
        $authToken = $authHeader;
    }
}

if (empty($authToken)) {
    send_json(["error" => "Authorization token is required"], 401);
}

// Parse input JSON body
$input = json_decode(file_get_contents("php://input"), true);
$code = isset($input['code']) ? trim($input['code']) : null;

if (empty($code)) {
    send_json(["error" => "Verification code is required"], 400);
}

// Check if user exists with matching auth token and code
$stmt = $conn->prepare("SELECT id, fullname, status FROM vendors_info WHERE auth_token = ? AND veri_code = ?");
$stmt->bind_param("ss", $authToken, $code);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    $stmt->close();
    send_json(["error" => "Invalid authorization token or verification code"], 401);
}

$stmt->bind_result($vendor_id, $fullname, $current_status);
$stmt->fetch();
$stmt->close();

// Prevent reactivation if already active
if ($current_status === 'Active') {
    send_json(["message" => "Account already verified and active."], 200);
}

// Update vendor status and clear verification code
$update = $conn->prepare("UPDATE vendors_info SET status = 'Active', veri_code = NULL WHERE id = ?");
$update->bind_param("i", $vendor_id);

if (!$update->execute()) {
    $update->close();
    send_json(["error" => "Failed to update account status"], 500);
}
$update->close();

// Send confirmation email
sendGenericEmail($fullname, "Account Activated", "Hi $fullname, your account has been successfully verified.");

send_json([
    "message" => "Verification successful. Your account is now active.",
    "vendor_id" => $vendor_id
], 200);
