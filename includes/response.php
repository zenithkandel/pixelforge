<?php

function respond_success($data = [], $message = 'Success', $http_code = 200) {
    http_response_code($http_code);
    echo json_encode([
        'ok' => true,
        'message' => $message,
        'data' => $data
    ]);
    exit();
}

function respond_error($error, $message = '', $http_code = 400) {
    http_response_code($http_code);
    echo json_encode([
        'ok' => false,
        'error' => $error,
        'message' => $message
    ]);
    exit();
}

function respond_json($data, $http_code = 200) {
    http_response_code($http_code);
    echo json_encode($data);
    exit();
}

?>
