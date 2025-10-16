<?php
// Handle CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  header("Access-Control-Allow-Origin: *");
  header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
  header("Access-Control-Allow-Headers: Content-Type, Authorization");
  header("Access-Control-Max-Age: 86400");
  http_response_code(204);
  exit;
}

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

include('db_conn.php');

// Helper function for consistent JSON response
function respond($status, $message, $data = [], $code = 200)
{
  http_response_code($code);
  echo json_encode([
    'status' => $status,
    'message' => $message,
    'data' => $data
  ]);
  exit;
}

// Get Authorization header
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;

if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
  respond('error', 'Missing or invalid Authorization header', [], 401);
}

$token = $matches[1];

// Validate token against database
$stmt = $conn->prepare("SELECT * FROM vendors_info WHERE auth_token = ?");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  respond('error', 'Invalid or expired token', [], 401);
}

// Get user info
$user = $result->fetch_assoc();
$user_email = $user['email'];

// Fetch vendor info based on email 
$stmt2 = $conn->prepare("SELECT * FROM vendors_info WHERE email = ?");
$stmt2->bind_param("s", $user_email);
$stmt2->execute();
$res = $stmt2->get_result();

if ($res->num_rows === 0) {
  respond('error', 'No data found for this user', []);
}

$data = [];
while ($row = $res->fetch_assoc()) {
  // remove password and auth_token from the result
  unset($row['password'], $row['auth_token'], $row['session_token']);
  $data[] = $row;
}

respond('success', 'Data fetched successfully', $data);
