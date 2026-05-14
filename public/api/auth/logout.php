<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';

require_method('POST');

if (!empty($_SESSION['user_id'])) {
    log_audit('user_logout', $_SESSION['user_id']);
}

session_unset();
session_destroy();

setcookie('PXLSESS', '', time() - 3600, '/', '', true, true);

respond_success(['message' => 'Logged out successfully']);