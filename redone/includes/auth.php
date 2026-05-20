<?php
function is_logged_in()
{
    return isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id']);
}
function login_user($user)
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
}
function logout_user()
{
    session_unset();
    session_destroy();
}
function require_login()
{
    if (!is_logged_in()) {
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }
}
function get_logged_in_user()
{
    if (!is_logged_in())
        return null;
    return Database::fetch('SELECT * FROM users WHERE id = ?', [$_SESSION['user_id']]);
}
?>