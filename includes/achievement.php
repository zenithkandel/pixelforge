<?php
// includes/achievement.php

function check_and_grant_achievement($user_id, $achievement_key) {
    $db = DB::getInstance();
    
    // Get achievement details
    $stmt = $db->prepare("SELECT id FROM achievements WHERE key_name = ?");
    $stmt->execute([$achievement_key]);
    $ach_id = $stmt->fetchColumn();
    
    if (!$ach_id) return false;
    
    // Check if already earned
    $stmt = $db->prepare("SELECT 1 FROM user_achievements WHERE user_id = ? AND achievement_id = ?");
    $stmt->execute([$user_id, $ach_id]);
    if ($stmt->fetchColumn()) return false; // Already has it
    
    // Grant achievement
    $stmt = $db->prepare("INSERT INTO user_achievements (user_id, achievement_id) VALUES (?, ?)");
    $stmt->execute([$user_id, $ach_id]);
    
    return true;
}
