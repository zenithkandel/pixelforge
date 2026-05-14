<?php
// includes/response.php

function respond_success($data = null) {
    header('Content-Type: application/json');
    $response = ['ok' => true];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response);
    exit;
}

function respond_error($error_code, $message, $status = 400) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode([
        'ok' => false,
        'error' => $error_code,
        'message' => $message
    ]);
    exit;
}
