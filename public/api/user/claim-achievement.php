<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

require_method('POST');

$user = require_auth();
$data = get_json_body();

$key_name = $data['achievement_key'] ?? '';
$csrf_token = $data['csrf_token'] ?? '';

if (empty($key_name)) {
    respond_error('invalid_request', 'Achievement key required', 400);
}

if (!verify_csrf($csrf_token)) {
    respond_error('invalid_csrf', 'Invalid CSRF token', 403);
}

try {
    $pdo = get_db();

    $result = claim_achievement($pdo, $user['id'], $key_name);

    log_audit('achievement_claimed', $user['id'], ['key' => $key_name, 'pxl' => $result['pxl_awarded']]);

    respond_success([
        'pxl_awarded' => $result['pxl_awarded'],
        'new_balance' => $result['new_balance']
    ]);

} catch (Exception $e) {
    log_error('Claim achievement failed', ['exception' => $e->getMessage()]);
    respond_error('invalid_request', $e->getMessage(), 400);
}