<?php
/**
 * Achievement System
 */

function check_and_grant_achievements(PDO $pdo, int $user_id, string $context, array $context_data = []): array {
    $unlocked = [];

    $stmt = $pdo->prepare("
        SELECT u.*,
            COALESCE(ph.total_pixels, 0) as total_pixels,
            COALESCE(s.best_score, 0) as best_score,
            COALESCE(s.best_speed_tier, 0) as best_speed_tier
        FROM users u
        LEFT JOIN (
            SELECT user_id, COUNT(*) as total_pixels FROM pixel_history GROUP BY user_id
        ) ph ON ph.user_id = u.id
        LEFT JOIN (
            SELECT user_id, MAX(score) as best_score, MAX(max_speed_tier) as best_speed_tier FROM scores GROUP BY user_id
        ) s ON s.user_id = u.id
        WHERE u.id = ?
    ");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch();

    $to_check = match($context) {
        'game_submit' => ['first_game', 'speed_tier_3', 'speed_tier_5', 'speed_tier_7',
            'score_500', 'score_2000', 'score_5000', 'score_10000',
            'combo_15', 'combo_35', 'rainbow_5', 'bomb_used', 'total_earned_100'],
        'pixel_buy' => ['first_pixel', 'pixels_50', 'pixels_250', 'pixels_1000'],
        'login' => ['streak_3', 'streak_7', 'streak_30'],
        default => []
    };

    if (empty($to_check)) {
        return $unlocked;
    }

    $stmt = $pdo->prepare("SELECT achievement_id FROM user_achievements WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $earned_ids = array_column($stmt->fetchAll(), 'achievement_id');

    $placeholders = implode(',', array_fill(0, count($to_check), '?'));
    $stmt = $pdo->prepare("SELECT * FROM achievements WHERE key_name IN ({$placeholders})");
    $stmt->execute($to_check);
    $achievements = $stmt->fetchAll();

    foreach ($achievements as $ach) {
        if (in_array($ach['id'], $earned_ids)) {
            continue;
        }

        $earned = match($ach['key_name']) {
            'first_game' => ($context === 'game_submit'),
            'speed_tier_3' => ($context_data['max_speed_tier'] ?? 0) >= 3,
            'speed_tier_5' => ($context_data['max_speed_tier'] ?? 0) >= 5,
            'speed_tier_7' => ($context_data['max_speed_tier'] ?? 0) >= 7,
            'score_500' => ($context_data['final_score'] ?? 0) >= 500,
            'score_2000' => ($context_data['final_score'] ?? 0) >= 2000,
            'score_5000' => ($context_data['final_score'] ?? 0) >= 5000,
            'score_10000' => ($context_data['final_score'] ?? 0) >= 10000,
            'combo_15' => ($context_data['max_combo'] ?? 0) >= 15,
            'combo_35' => ($context_data['max_combo'] ?? 0) >= 35,
            'rainbow_5' => ($context_data['prisms_collected'] ?? 0) >= 5,
            'bomb_used' => ($context_data['bomb_used'] ?? false) === true,
            'first_pixel' => (int)($stats['total_pixels'] ?? 0) >= 1,
            'pixels_50' => (int)($stats['total_pixels'] ?? 0) >= 50,
            'pixels_250' => (int)($stats['total_pixels'] ?? 0) >= 250,
            'pixels_1000' => (int)($stats['total_pixels'] ?? 0) >= 1000,
            'total_earned_100' => (int)($stats['total_pxl_earned'] ?? 0) >= 100,
            'streak_3' => (int)($stats['login_streak'] ?? 0) >= 3,
            'streak_7' => (int)($stats['login_streak'] ?? 0) >= 7,
            'streak_30' => (int)($stats['login_streak'] ?? 0) >= 30,
            default => false
        };

        if ($earned) {
            $pdo->prepare("INSERT IGNORE INTO user_achievements (user_id, achievement_id) VALUES (?, ?)")
                ->execute([$user_id, $ach['id']]);
            $unlocked[] = [
                'key' => $ach['key_name'],
                'title' => $ach['title'],
                'pxl' => $ach['pxl_reward']
            ];
        }
    }

    return $unlocked;
}

function claim_achievement(PDO $pdo, int $user_id, string $key_name): array {
    $stmt = $pdo->prepare("SELECT a.* FROM achievements a WHERE a.key_name = ?");
    $stmt->execute([$key_name]);
    $achievement = $stmt->fetch();

    if (!$achievement) {
        throw new RuntimeException('Achievement not found');
    }

    $stmt = $pdo->prepare("SELECT * FROM user_achievements WHERE user_id = ? AND achievement_id = ? AND pxl_claimed = 0");
    $stmt->execute([$user_id, $achievement['id']]);
    $user_ach = $stmt->fetch();

    if (!$user_ach) {
        throw new RuntimeException('Achievement not earned or already claimed');
    }

    $pdo->prepare("UPDATE user_achievements SET pxl_claimed = 1 WHERE user_id = ? AND achievement_id = ?")
        ->execute([$user_id, $achievement['id']]);

    credit_pxl($pdo, $user_id, $achievement['pxl_reward'], 'achievement', $key_name, "Achievement: {$achievement['title']}");

    return [
        'pxl_awarded' => $achievement['pxl_reward'],
        'new_balance' => get_user_balance($pdo, $user_id)
    ];
}

function get_user_balance(PDO $pdo, int $user_id): int {
    $stmt = $pdo->prepare("SELECT pxl_balance FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    return (int)$stmt->fetchColumn();
}

function get_user_achievements(PDO $pdo, int $user_id): array {
    $stmt = $pdo->query("SELECT * FROM achievements");
    $all_achievements = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT achievement_id, earned_at, pxl_claimed FROM user_achievements WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user_achievements = $stmt->fetchAll();
    $user_ach_map = [];
    foreach ($user_achievements as $ua) {
        $user_ach_map[$ua['achievement_id']] = $ua;
    }

    $result = [];
    foreach ($all_achievements as $ach) {
        $earned = isset($user_ach_map[$ach['id']]);
        $result[] = [
            'id' => $ach['id'],
            'key_name' => $ach['key_name'],
            'title' => $ach['title'],
            'description' => $ach['description'],
            'pxl_reward' => $ach['pxl_reward'],
            'earned' => $earned,
            'earned_at' => $earned ? $user_ach_map[$ach['id']]['earned_at'] : null,
            'pxl_claimed' => $earned ? (bool)$user_ach_map[$ach['id']]['pxl_claimed'] : false
        ];
    }

    return $result;
}