<?php
function cors() {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    header("Access-Control-Max-Age: 86400");
    header("Content-Type: application/json");
}

function send_json($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}
