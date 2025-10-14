<?php
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


// Database connection
include('db_conn.php');

// Get today's date (if needed)
$today = date("Y-m-d");

// Query to get data
$sql = "SELECT * FROM `vendor_info`";
$result = mysqli_query($conn, $sql);

// Initialize response array
$response = [];

if (mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
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

?>