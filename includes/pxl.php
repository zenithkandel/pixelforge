<?php
// includes/pxl.php

function pxl_credit($user_id, $amount, $type, $reference_id, $description) {
    if ($amount <= 0) return true;
    
    $db = DB::getInstance();
    
    // Update user balance
    $stmt = $db->prepare("UPDATE users SET pxl_balance = pxl_balance + ?, total_pxl_earned = total_pxl_earned + ? WHERE id = ?");
    $stmt->execute([$amount, $amount, $user_id]);
    
    // Get new balance
    $stmt = $db->prepare("SELECT pxl_balance FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $new_balance = $stmt->fetchColumn();
    
    // Record transaction
    $stmt = $db->prepare("INSERT INTO pxl_transactions (user_id, amount, type, reference_id, balance_after, description) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $amount, $type, $reference_id, $new_balance, $description]);
    
    return $new_balance;
}

function pxl_debit($user_id, $amount, $type, $reference_id, $description) {
    if ($amount <= 0) return true;
    
    $db = DB::getInstance();
    
    // Check balance
    $stmt = $db->prepare("SELECT pxl_balance FROM users WHERE id = ? FOR UPDATE");
    $stmt->execute([$user_id]);
    $current = $stmt->fetchColumn();
    
    if ($current < $amount) {
        return false;
    }
    
    // Update user balance
    $stmt = $db->prepare("UPDATE users SET pxl_balance = pxl_balance - ?, total_pxl_spent = total_pxl_spent + ? WHERE id = ?");
    $stmt->execute([$amount, $amount, $user_id]);
    
    $new_balance = $current - $amount;
    
    // Record transaction
    $stmt = $db->prepare("INSERT INTO pxl_transactions (user_id, amount, type, reference_id, balance_after, description) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, -$amount, $type, $reference_id, $new_balance, $description]);
    
    return $new_balance;
}
