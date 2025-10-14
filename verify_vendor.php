<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

// Handle CORS preflight
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

// Get Authorization header
$headers = getallheaders();
$authHeader = isset($headers['Authorization']) ? trim($headers['Authorization']) : null;

// Strip "Bearer " prefix if present (case-insensitive)
$authToken = null;
if ($authHeader) {
    if (stripos($authHeader, 'Bearer ') === 0) {
        $authToken = trim(substr($authHeader, 7)); // Remove "Bearer "
    } else {
        $authToken = $authHeader;
    }
}

// Get verification code (PIN) from request body
$input = json_decode(file_get_contents("php://input"), true);
$code = isset($input['code']) ? trim($input['code']) : null;

// Validate inputs
if (empty($authToken) || empty($code)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Authorization token and code are required"]);
    exit;
}

// Check if token and code match in DB
$stmt = $conn->prepare("SELECT id FROM vendors_info WHERE auth_token = ? AND veri_code = ?");
$stmt->bind_param("ss", $authToken, $code);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Invalid token or verification code"]);
    $stmt->close();
    $conn->close();
    exit;
}

$stmt->bind_result($userId);
$stmt->fetch();
$stmt->close();

// Update user status to 'Active' and clear verification code
$updateStmt = $conn->prepare("UPDATE vendors_info SET status = 'Active', veri_code = NULL WHERE auth_token = ?");
$updateStmt->bind_param("s", $authToken);

if ($updateStmt->execute()) {
    http_response_code(200);
    echo json_encode([
        "status" => "success",
        "message" => "Code verified and user activated successfully"
    ]);
} else {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Failed to update user status"]);
}

$updateStmt->close();
$conn->close();
exit;
?>
