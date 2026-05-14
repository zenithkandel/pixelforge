<?php

function check_and_grant_achievements($user_id, $trigger_type, $trigger_data = []) {
    $achievements_to_check = [];
    
    switch ($trigger_type) {
        case 'game_submit':
            $score = $trigger_data['score'] ?? 0;
            $speed_tier = $trigger_data['speed_tier'] ?? 0;
            
            if ($score >= 500) $achievements_to_check[] = 'score_500';
            if ($score >= 2000) $achievements_to_check[] = 'score_2000';
            if ($score >= 5000) $achievements_to_check[] = 'score_5000';
            if ($score >= 10000) $achievements_to_check[] = 'score_10000';
            
            if ($speed_tier >= 3) $achievements_to_check[] = 'speed_tier_3';
            if ($speed_tier >= 5) $achievements_to_check[] = 'speed_tier_5';
            if ($speed_tier >= 7) $achievements_to_check[] = 'speed_tier_7';
            
            break;
            
        case 'pixel_buy':
            $total_pixels = $trigger_data['total_pixels'] ?? 0;
            
            if ($total_pixels == 1) $achievements_to_check[] = 'first_pixel';
            if ($total_pixels >= 50) $achievements_to_check[] = 'pixels_50';
            if ($total_pixels >= 250) $achievements_to_check[] = 'pixels_250';
            if ($total_pixels >= 1000) $achievements_to_check[] = 'pixels_1000';
            
            break;
            
        case 'combo':
            $combo = $trigger_data['combo'] ?? 0;
            
            if ($combo >= 15) $achievements_to_check[] = 'combo_15';
            if ($combo >= 35) $achievements_to_check[] = 'combo_35';
            
            break;
            
        case 'login':
            $streak = $trigger_data['streak'] ?? 0;
            $total_earned = $trigger_data['total_earned'] ?? 0;
            
            if ($streak >= 3) $achievements_to_check[] = 'streak_3';
            if ($streak >= 7) $achievements_to_check[] = 'streak_7';
            if ($streak >= 30) $achievements_to_check[] = 'streak_30';
            if ($total_earned >= 100) $achievements_to_check[] = 'total_earned_100';
            
            break;
    }
    
    $granted = [];
    foreach ($achievements_to_check as $achievement_key) {
        if (grant_achievement($user_id, $achievement_key)) {
            $granted[] = $achievement_key;
        }
    }
    
    return $granted;
}

function grant_achievement($user_id, $achievement_key) {
    // Check if already granted
    $existing = Database::fetch(
        'SELECT id FROM user_achievements WHERE user_id = ? AND achievement_key = ?',
        [$user_id, $achievement_key]
    );
    
    if ($existing) {
        return false;
    }
    
    // Get achievement info
    $achievement = Database::fetch(
        'SELECT id, pxl_reward FROM achievements WHERE achievement_key = ?',
        [$achievement_key]
    );
    
    if (!$achievement) {
        return false;
    }
    
    // Grant achievement (user must claim for PXL)
    Database::execute(
        'INSERT INTO user_achievements (user_id, achievement_id, achievement_key, is_claimed, created_at) VALUES (?, ?, ?, 0, NOW())',
        [$user_id, $achievement['id'], $achievement_key]
    );
    
    return true;
}

function get_user_achievements($user_id) {
    return Database::fetchAll(
        'SELECT a.*, COALESCE(ua.is_claimed, 0) as is_claimed FROM achievements a LEFT JOIN user_achievements ua ON a.id = ua.achievement_id AND ua.user_id = ? ORDER BY a.id',
        [$user_id]
    );
}

function claim_achievement($user_id, $achievement_key) {
    // Get achievement
    $achievement = Database::fetch(
        'SELECT id, pxl_reward FROM achievements WHERE achievement_key = ?',
        [$achievement_key]
    );
    
    if (!$achievement) {
        return false;
    }
    
    // Check if already claimed
    $existing = Database::fetch(
        'SELECT id FROM user_achievements WHERE user_id = ? AND achievement_id = ? AND is_claimed = 1',
        [$user_id, $achievement['id']]
    );
    
    if ($existing) {
        return false;
    }
    
    Database::beginTransaction();
    try {
        // Mark as claimed
        Database::execute(
            'UPDATE user_achievements SET is_claimed = 1 WHERE user_id = ? AND achievement_id = ?',
            [$user_id, $achievement['id']]
        );
        
        // Credit PXL
        credit_pxl($user_id, $achievement['pxl_reward'], 'achievement', $achievement['id']);
        
        Database::commit();
        return true;
    } catch (Exception $e) {
        Database::rollback();
        return false;
    }
}

?>
