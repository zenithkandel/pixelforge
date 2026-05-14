<?php

function credit_pxl($user_id, $amount, $type, $related_id = null, $description = '')
{
    Database::beginTransaction();
    try {
        // Update balance
        Database::execute(
            'UPDATE users SET pxl_balance = pxl_balance + ? WHERE id = ?',
            [$amount, $user_id]
        );

        // Log transaction
        Database::execute(
            'INSERT INTO pxl_transactions (user_id, amount, type, related_id, description, created_at) VALUES (?, ?, ?, ?, ?, NOW())',
            [$user_id, $amount, $type, $related_id, $description]
        );

        Database::commit();
        return true;
    } catch (Exception $e) {
        Database::rollback();
        log_error('Failed to credit PXL', ['user_id' => $user_id, 'amount' => $amount, 'error' => $e->getMessage()]);
        return false;
    }
}

function debit_pxl($user_id, $amount, $type, $related_id = null, $description = '')
{
    // Check balance first
    $user = Database::fetch('SELECT pxl_balance FROM users WHERE id = ?', [$user_id]);

    if (!$user || $user['pxl_balance'] < $amount) {
        return false;
    }

    Database::beginTransaction();
    try {
        // Update balance
        Database::execute(
            'UPDATE users SET pxl_balance = pxl_balance - ? WHERE id = ?',
            [$amount, $user_id]
        );

        // Log transaction
        Database::execute(
            'INSERT INTO pxl_transactions (user_id, amount, type, related_id, description, created_at) VALUES (?, ?, ?, ?, ?, NOW())',
            [$user_id, -$amount, $type, $related_id, $description]
        );

        Database::commit();
        return true;
    } catch (Exception $e) {
        Database::rollback();
        log_error('Failed to debit PXL', ['user_id' => $user_id, 'amount' => $amount, 'error' => $e->getMessage()]);
        return false;
    }
}

function get_pxl_balance($user_id)
{
    $user = Database::fetch('SELECT pxl_balance FROM users WHERE id = ?', [$user_id]);
    return $user ? $user['pxl_balance'] : 0;
}

?>