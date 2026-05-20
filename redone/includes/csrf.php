<?php
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function csrf_field()
{
    return '<input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">';
}
function csrf_verify($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
?>