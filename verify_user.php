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

// Sanitize inputs
$email = trim($_POST['email']);
$code = trim($_POST['code']);

// Validate required fields
if (empty($email) || empty($code)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Email and code are required"]);
    exit;
}

// Check if email and code match
$stmt = $conn->prepare("SELECT id FROM user_info WHERE email = ? AND code = ?");
$stmt->bind_param("ss", $email, $code);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Invalid email or code"]);
    $stmt->close();
    $conn->close();
    exit;
}

// Fetch user ID (optional, if you need it)
$stmt->bind_result($user_id);
$stmt->fetch();
$stmt->close();

// Update user status to 'Active'
$updateStmt = $conn->prepare("UPDATE user_info SET status = 'Active' WHERE email = ?");
$updateStmt->bind_param("s", $email);

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
