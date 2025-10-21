<?php
/* ini_set('display_errors', 1); */
/* ini_set('display_startup_errors', 1); */
/* error_reporting(E_ALL); */

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

// Sanitize inputs
if (is_array($input)) {
  $email = trim($input['email'] ?? '');
  $code = trim($input['code'] ?? '');
} else {
  $email = trim($_POST['email'] ?? '');
  $code = trim($_POST['code']) ?? '';
}


// Validate required fields
if (empty($email) || empty($code)) {
  http_response_code(400);
  echo json_encode(["status" => "error", "message" => "Email and code are required"]);
  exit;
}

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(400);
  echo json_encode(["status" => "error", "message" => "Invalid email format"]);
  exit;
}

// Check if email and code match
$stmt = $conn->prepare("SELECT id FROM user_info WHERE email = ? AND veri_code = ?");
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

// Check if user status has already been updated
$verifyStmt = $conn->prepare("SELECT status FROM user_info WHERE email = ?");
$verifyStmt->bind_param("s", $email);
$verifyStmt->execute();
$verifyStmt->store_result();

// Check if a record exists
if ($verifyStmt->num_rows > 0) {
  // Bind the result column to a variable
  $verifyStmt->bind_result($status);
  $verifyStmt->fetch();

  if ($status == "Active") {
    echo json_encode(["status" => "error", "message" => "User has already been verified"]);
    $verifyStmt->close();
    $conn->close();
    exit;
  }
}

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
