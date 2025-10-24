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

// Database connection
include('db_conn.php');

// Get the query parameter (if provided)
$query = isset($_GET['query']) ? trim($_GET['query']) : '';

// Base SQL
$sql = "SELECT * FROM `vendors_info`";

// If a query parameter is provided, add WHERE clause
if (!empty($query)) {
  // Escape input to prevent SQL injection
  $safe_query = mysqli_real_escape_string($conn, $query);
  // Make search case-insensitive using LOWER()
  $sql .= " WHERE LOWER(`business_name`) LIKE LOWER('%$safe_query%') 
            OR LOWER(`location`) LIKE LOWER('%$safe_query%')";
}

$result = mysqli_query($conn, $sql);

// Initialize response array
$response = [];

if ($result && mysqli_num_rows($result) > 0) {
  while ($row = mysqli_fetch_assoc($result)) {
    unset($row['password'], $row['auth_token'], $row['session_token']);
    $response[] = $row;
  }

  echo json_encode([
    'status' => 'success',
    'message' => 'Data fetched successfully',
    'data' => $response
  ]);
} else {
  echo json_encode([
    'status' => 'error',
    'message' => 'No data found',
    'data' => []
  ]);
}

// Close DB connection
mysqli_close($conn);
