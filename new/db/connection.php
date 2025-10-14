<?php
$host = "localhost";
$user = "eventyad_main";
$pass = "eventyad_main";
$dbname = "eventyad_main";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "DB connection failed"]);
    exit;
}