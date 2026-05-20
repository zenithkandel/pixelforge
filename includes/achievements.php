<?php

function get_achievements() {
    return [
        ['slug' => 'first_flight', 'name' => 'First Flight', 'description' => 'Complete your first game', 'icon' => '🎮', 'reward' => 10],
        ['slug' => 'score_10', 'name' => 'Getting Somewhere', 'description' => 'Score 10 in one run', 'icon' => '📈', 'reward' => 20],
        ['slug' => 'score_50', 'name' => 'Flap Master', 'description' => 'Score 50 in one run', 'icon' => '🏆', 'reward' => 75],
        ['slug' => 'score_100', 'name' => 'Sky Ruler', 'description' => 'Score 100 in one run', 'icon' => '👑', 'reward' => 200],
        ['slug' => 'first_pixel', 'name' => 'Mark Your Territory', 'description' => 'Place your first pixel', 'icon' => '🎨', 'reward' => 15],
        ['slug' => 'pixel_5', 'name' => 'Growing Empire', 'description' => 'Own 5 pixels', 'icon' => '🏡', 'reward' => 30],
        ['slug' => 'pixel_25', 'name' => 'Canvas Veteran', 'description' => 'Own 25 pixels', 'icon' => '🛡️', 'reward' => 100],
        ['slug' => 'pixel_100', 'name' => 'Canvas Legend', 'description' => 'Own 100 pixels', 'icon' => '🌟', 'reward' => 500],
        ['slug' => 'streak_3', 'name' => 'On a Roll', 'description' => '3-day login streak', 'icon' => '🔥', 'reward' => 50],
        ['slug' => 'streak_7', 'name' => 'Dedicated', 'description' => '7-day login streak', 'icon' => '💪', 'reward' => 150],
        ['slug' => 'streak_30', 'name' => 'Obsessed', 'description' => '30-day login streak', 'icon' => '💎', 'reward' => 1000],
        ['slug' => 'level_5', 'name' => 'Rising Star', 'description' => 'Reach level 5', 'icon' => '⭐', 'reward' => 50],
        ['slug' => 'level_10', 'name' => 'Veteran', 'description' => 'Reach level 10', 'icon' => '🎖️', 'reward' => 150],
        ['slug' => 'level_20', 'name' => 'Elite', 'description' => 'Reach level 20', 'icon' => '🔱', 'reward' => 500],
        ['slug' => 'multiplier_3x', 'name' => 'Combo King', 'description' => 'Reach 3x multiplier in one run', 'icon' => '⚡', 'reward' => 100],
    ];
}

function check_achievement($user_id, $slug) {
    $existing = Database::fetch(
        "SELECT ua.id FROM user_achievements ua
         JOIN achievements a ON ua.achievement_id = a.id
         WHERE ua.user_id = ? AND a.slug = ?",
        [$user_id, $slug]
    );
    if ($existing) return null;

    $achievement = Database::fetch("SELECT * FROM achievements WHERE slug = ?", [$slug]);
    if (!$achievement) return null;

    Database::query("INSERT IGNORE INTO user_achievements (user_id, achievement_id) VALUES (?, ?)", [$user_id, $achievement['id']]);
    add_xp($user_id, XP_ACHIEVEMENT);
    add_balance($user_id, $achievement['reward']);

    return $achievement;
}

function check_score_achievements($user_id, $score, $multiplier) {
    $new_achievements = [];

    if ($score >= 1) {
        $a = check_achievement($user_id, 'first_flight');
        if ($a) $new_achievements[] = $a;
    }
    if ($score >= 10) {
        $a = check_achievement($user_id, 'score_10');
        if ($a) $new_achievements[] = $a;
    }
    if ($score >= 50) {
        $a = check_achievement($user_id, 'score_50');
        if ($a) $new_achievements[] = $a;
    }
    if ($score >= 100) {
        $a = check_achievement($user_id, 'score_100');
        if ($a) $new_achievements[] = $a;
    }
    if ($multiplier >= 3.0) {
        $a = check_achievement($user_id, 'multiplier_3x');
        if ($a) $new_achievements[] = $a;
    }

    return $new_achievements;
}

function check_streak_achievements($user_id, $streak_days) {
    $new_achievements = [];

    if ($streak_days >= 3) {
        $a = check_achievement($user_id, 'streak_3');
        if ($a) $new_achievements[] = $a;
    }
    if ($streak_days >= 7) {
        $a = check_achievement($user_id, 'streak_7');
        if ($a) $new_achievements[] = $a;
    }
    if ($streak_days >= 30) {
        $a = check_achievement($user_id, 'streak_30');
        if ($a) $new_achievements[] = $a;
    }

    return $new_achievements;
}

function check_level_achievements($user_id, $level) {
    $new_achievements = [];

    if ($level >= 5) {
        $a = check_achievement($user_id, 'level_5');
        if ($a) $new_achievements[] = $a;
    }
    if ($level >= 10) {
        $a = check_achievement($user_id, 'level_10');
        if ($a) $new_achievements[] = $a;
    }
    if ($level >= 20) {
        $a = check_achievement($user_id, 'level_20');
        if ($a) $new_achievements[] = $a;
    }

    return $new_achievements;
}

function check_pixel_achievements($user_id) {
    $new_achievements = [];

    $count = Database::fetch("SELECT COUNT(*) as cnt FROM pixels WHERE owner_id = ?", [$user_id]);

    if ($count['cnt'] >= 1) {
        $a = check_achievement($user_id, 'first_pixel');
        if ($a) $new_achievements[] = $a;
    }
    if ($count['cnt'] >= 5) {
        $a = check_achievement($user_id, 'pixel_5');
        if ($a) $new_achievements[] = $a;
    }
    if ($count['cnt'] >= 25) {
        $a = check_achievement($user_id, 'pixel_25');
        if ($a) $new_achievements[] = $a;
    }
    if ($count['cnt'] >= 100) {
        $a = check_achievement($user_id, 'pixel_100');
        if ($a) $new_achievements[] = $a;
    }

    return $new_achievements;
}

function get_user_achievements($user_id) {
    return Database::fetchAll(
        "SELECT a.*, ua.earned_at FROM achievements a
         LEFT JOIN user_achievements ua ON a.id = ua.achievement_id AND ua.user_id = ?
         ORDER BY ua.earned_at DESC",
        [$user_id]
    );
}