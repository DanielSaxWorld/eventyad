<?php

$servername = "localhost";
$username   = "eventyad_main";
$password   = "eventyad_main";
$database   = "eventyad_main";

// Create connection using MySQLi OOP
$conn = new mysqli($servername, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
  http_response_code(500);
  echo json_encode([
    "status" => "error",
    "message" => "Database connection failed",
    "error" => $conn->connect_error
  ]);
  exit;
}
