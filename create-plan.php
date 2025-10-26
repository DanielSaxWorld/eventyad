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

if (is_array($input)) {
    $plan_name        = trim($input['plan_name'] ?? '');
    $plan_price       = trim($input['plan_price'] ?? '');
    $plan_offer       = trim($input['plan_offer'] ?? '');
    $plan_description = trim($input['plan_description'] ?? '');
    $vendor_uin       = trim($input['vendor_uin'] ?? '');
    $status           = trim($input['status'] ?? 'Active');
} else {
    $plan_name        = trim($_POST['plan_name']);
    $plan_price       = trim($_POST['plan_price']);
    $plan_offer       = trim($_POST['plan_offer']);
    $plan_description = trim($_POST['plan_description']);
    $vendor_uin       = trim($_POST['vendor_uin']);
    $status           = trim($_POST['status'] ?? 'Active');
}

// Validate required fields
if (empty($plan_name) || empty($plan_price) || empty($vendor_uin)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Missing required fields"]);
    exit;
}

// Generate unique plan_uin
$rand = rand(10000, 99999);
$apha = chr(mt_rand(65, 90)) . chr(mt_rand(65, 90));
$aphaa = chr(rand(65, 90));
$plan_uin = $aphaa . $rand . "EY";

// Insert new plan
$stmt = $conn->prepare("
    INSERT INTO plans (
        plan_uin, plan_name, plan_price, plan_offer, plan_description, vendor_uin, status
    ) VALUES (?, ?, ?, ?, ?, ?, ?)
");

if (!$stmt) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Failed to prepare statement"]);
    exit;
}

$stmt->bind_param(
    "ssdssss",
    $plan_uin,
    $plan_name,
    $plan_price,
    $plan_offer,
    $plan_description,
    $vendor_uin,
    $status
);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Failed to create plan"]);
    $stmt->close();
    exit;
}

$stmt->close();

http_response_code(201);
echo json_encode([
    "status" => "success",
    "message" => "Plan created successfully",
    "plan_uin" => $plan_uin
]);

$conn->close();
exit;
?>
