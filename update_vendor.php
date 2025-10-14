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
    http_response_code(204); // No Content
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

// Check if user is logged in
$email = $_SESSION['email'];
if (!$email) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized: Email not in session"]);
    exit;
}

// Sanitize optional update inputs
$vendor_type   = trim($_POST['vendor_type']);
$business_name = trim($_POST['business_name']);
$location      = trim($_POST['location']);

// Utility functions to handle uploads
function uploadFile($file, $prefix, $uploadDir = "uploads/") {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) return null;

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid($prefix . "_") . "." . $ext;
    $targetPath = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return null;
    }
    return $filename;
}

function uploadMultipleFiles($files, $prefix, $uploadDir = "uploads/") {
    $uploadedFiles = [];

    if (!isset($files['name']) || !is_array($files['name'])) {
        return $uploadedFiles;
    }

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

// Handle file uploads
$workImages  = isset($_FILES['work']) ? uploadMultipleFiles($_FILES['work'], 'work') : [];
$businessDoc = uploadFile($_FILES['business_doc'] ?? null, 'business_doc');
$idDoc       = uploadFile($_FILES['id_doc'] ?? null, 'id_doc');

$workJson = !empty($workImages) ? json_encode($workImages) : null;

// Build dynamic update query
$updateFields = [];
$params = [];
$types = '';

// Add optional fields
if ($vendor_type) {
    $updateFields[] = "vendor_type = ?";
    $params[] = $vendor_type;
    $types .= 's';
}

if ($business_name) {
    $updateFields[] = "business_name = ?";
    $params[] = $business_name;
    $types .= 's';
}

if ($location) {
    $updateFields[] = "location = ?";
    $params[] = $location;
    $types .= 's';
}

if ($workJson) {
    $updateFields[] = "work = ?";
    $params[] = $workJson;
    $types .= 's';
}

if ($businessDoc) {
    $updateFields[] = "business_doc = ?";
    $params[] = $businessDoc;
    $types .= 's';
}

if ($idDoc) {
    $updateFields[] = "id_doc = ?";
    $params[] = $idDoc;
    $types .= 's';
}

if (empty($updateFields)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "No fields provided to update"]);
    exit;
}

// Add the email as the last parameter for WHERE clause
$params[] = $email;
$types .= 's';

$sql = "UPDATE vendors_info SET " . implode(', ', $updateFields) . " WHERE email = ?";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Failed to prepare update query"]);
    exit;
}

$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    http_response_code(200);
    echo json_encode(["status" => "success", "message" => "Vendor profile updated successfully"]);
} else {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Failed to update vendor"]);
}

$stmt->close();
$conn->close();
exit;
?>
