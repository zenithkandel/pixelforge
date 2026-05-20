<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/logger.php';

log_info('AUTH', 'User logged out');
session_unset();
session_destroy();
session_start();
session_regenerate_id(true);

header('Location: ' . BASE_URL . '/');
exit();
