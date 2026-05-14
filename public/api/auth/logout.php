<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';

require_auth();

// Destroys session
session_unset();
session_destroy();
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

respond_success(['message' => 'Logged out successfully']);
