<?php

function validate_username($username)
{
    if (empty($username) || !is_string($username)) {
        return false;
    }

    if (strlen($username) < 3 || strlen($username) > 20) {
        return false;
    }

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        return false;
    }

    return true;
}

function validate_email($email)
{
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    if (strlen($email) > 254) {
        return false;
    }

    return true;
}

function validate_password($password)
{
    if (empty($password) || !is_string($password)) {
        return false;
    }

    if (strlen($password) < 8) {
        return false;
    }

    if (!preg_match('/[a-zA-Z]/', $password)) {
        return false;
    }

    if (!preg_match('/[0-9]/', $password)) {
        return false;
    }

    return true;
}

function validate_color($color)
{
    if (empty($color) || !is_string($color)) {
        return false;
    }

    if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
        return false;
    }

    return true;
}

function validate_coordinate($value, $max = GRID_SIZE - 1)
{
    if (!is_numeric($value)) {
        return false;
    }

    $value = intval($value);

    if ($value < 0 || $value > $max) {
        return false;
    }

    return true;
}

function validate_chunk_coordinate($value, $max = GRID_SIZE / GRID_CHUNK_SIZE - 1)
{
    if (!is_numeric($value)) {
        return false;
    }

    $value = intval($value);

    if ($value < 0 || $value > $max) {
        return false;
    }

    return true;
}

function validate_positive_integer($value, $min = 0, $max = PHP_INT_MAX)
{
    if (!is_numeric($value)) {
        return false;
    }

    $value = intval($value);

    if ($value < $min || $value > $max) {
        return false;
    }

    return true;
}

?>