<?php
// includes/validate.php

function validate_username($username) {
    return preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username);
}

function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validate_password($password) {
    $len = strlen($password);
    if ($len < 8 || $len > 128) return false;
    if (!preg_match('/[A-Za-z]/', $password)) return false;
    if (!preg_match('/[0-9]/', $password)) return false;
    return true;
}

function validate_color($color) {
    return preg_match('/^#[0-9A-Fa-f]{6}$/', $color);
}

function validate_coord($x, $y) {
    return is_numeric($x) && is_numeric($y) && $x >= 0 && $x < 800 && $y >= 0 && $y < 800;
}
