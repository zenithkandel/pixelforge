<?php

function calculate_level($xp) {
    return min(100, (int)(1 + sqrt($xp / 50)));
}

function xp_for_level($level) {
    return ($level - 1) * ($level - 1) * 50;
}

function xp_progress($xp) {
    $current_level = calculate_level($xp);
    $xp_for_current = xp_for_level($current_level);
    $xp_for_next = xp_for_level($current_level + 1);
    $progress = ($xp - $xp_for_current) / ($xp_for_next - $xp_for_current);
    return min(1, max(0, $progress));
}

function add_xp($user_id, $amount) {
    Database::query("UPDATE users SET xp = xp + ? WHERE id = ?", [$amount, $user_id]);
    $user = Database::fetch("SELECT xp FROM users WHERE id = ?", [$user_id]);
    $new_level = calculate_level($user['xp']);
    $old_level = calculate_level($user['xp'] - $amount);

    if ($new_level > $old_level) {
        Database::query("UPDATE users SET level = ? WHERE id = ?", [$new_level, $user_id]);
        return ['level_up' => true, 'new_level' => $new_level];
    }
    return ['level_up' => false];
}

function add_balance($user_id, $amount) {
    Database::query("UPDATE users SET balance = balance + ? WHERE id = ?", [$amount, $user_id]);
}

function deduct_balance($user_id, $amount) {
    $user = Database::fetch("SELECT balance FROM users WHERE id = ?", [$user_id]);
    if ($user['balance'] < $amount) {
        return false;
    }
    Database::query("UPDATE users SET balance = balance - ? WHERE id = ?", [$amount, $user_id]);
    return true;
}