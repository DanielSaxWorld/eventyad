<?php

function uploadFile($file, $prefix, $uploadDir = "../../uploads/") {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) return null;
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid($prefix . "_") . "." . $ext;
    $targetPath = $uploadDir . $filename;
    return move_uploaded_file($file['tmp_name'], $targetPath) ? $filename : null;
}

function uploadMultipleFiles($files, $prefix, $uploadDir = "../../uploads/") {
    $uploadedFiles = [];
    if (!isset($files['name'])) return [];
    foreach ($files['name'] as $i => $name) {
        if ($files['error'][$i] === UPLOAD_ERR_OK) {
            $ext = pathinfo($name, PATHINFO_EXTENSION);
            $filename = uniqid($prefix . "_") . "." . $ext;
            $targetPath = $uploadDir . $filename;
            if (move_uploaded_file($files['tmp_name'][$i], $targetPath)) {
                $uploadedFiles[] = $filename;
            }
        }
    }
    return $uploadedFiles;
}
