<?php

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

// Optional: add more helpers later
function getAuthorizationToken()
{
  $headers = getallheaders();
  $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;

  if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    respond('error', 'Missing or invalid Authorization header', [], 401);
  }

  return $matches[1];
}
