<?php

function check_and_award_achievements(int $user_id, string $context, array $data = []): array {
    $pdo = get_db();
    $new_achievements = [];

    $stmt = $pdo->prepare('SELECT id, slug, name, description, icon, reward FROM achievements');
    $stmt->execute();
    $all_achievements = $stmt->fetchAll();

    $stmt = $pdo->prepare('SELECT achievement_id FROM user_achievements WHERE user_id = ?');
    $stmt->execute([$user_id]);
    $earned = array_column($stmt->fetchAll(), 'achievement_id');

    $user_stmt = $pdo->prepare('SELECT streak_days, level, balance FROM users WHERE id = ?');
    $user_stmt->execute([$user_id]);
    $user = $user_stmt->fetch();

    $pixels_stmt = $pdo->prepare('SELECT COUNT(*) as count FROM pixels WHERE owner_id = ?');
    $pixels_stmt->execute([$user_id]);
    $pixel_count = $pixels_stmt->fetch()['count'];

    $total_pixels_stmt = $pdo->prepare('SELECT COUNT(*) as count FROM pixels WHERE owner_id = ?');
    $total_pixels_stmt->execute([$user_id]);
    $total_pixels = $total_pixels_stmt->fetch()['count'];

    $score_stmt = $pdo->prepare('SELECT score, multiplier FROM score_log WHERE user_id = ? ORDER BY score DESC LIMIT 1');
    $score_stmt->execute([$user_id]);
    $best_score = $score_stmt->fetch();

    foreach ($all_achievements as $ach) {
        if (in_array($ach['id'], $earned)) continue;

        $unlock = false;

        switch ($ach['slug']) {
            case 'first_flight':
                $unlock = ($context === 'game_complete');
                break;
            case 'score_10':
                $unlock = ($context === 'game_complete' && ($data['score'] ?? 0) >= 10);
                break;
            case 'score_50':
                $unlock = ($context === 'game_complete' && ($data['score'] ?? 0) >= 50);
                break;
            case 'score_100':
                $unlock = ($context === 'game_complete' && ($data['score'] ?? 0) >= 100);
                break;
            case 'first_pixel':
                $unlock = ($context === 'pixel_placed' && ($data['first_pixel'] ?? false));
                break;
            case 'pixel_5':
                $unlock = ($context === 'pixel_count' && $pixel_count >= 5);
                break;
            case 'pixel_25':
                $unlock = ($context === 'pixel_count' && $pixel_count >= 25);
                break;
            case 'pixel_100':
                $unlock = ($context === 'pixel_count' && $pixel_count >= 100);
                break;
            case 'streak_3':
                $unlock = ($user['streak_days'] ?? 0) >= 3;
                break;
            case 'streak_7':
                $unlock = ($user['streak_days'] ?? 0) >= 7;
                break;
            case 'streak_30':
                $unlock = ($user['streak_days'] ?? 0) >= 30;
                break;
            case 'level_5':
                $unlock = ($user['level'] ?? 1) >= 5;
                break;
            case 'level_10':
                $unlock = ($user['level'] ?? 1) >= 10;
                break;
            case 'level_20':
                $unlock = ($user['level'] ?? 1) >= 20;
                break;
            case 'multiplier_3x':
                $unlock = ($context === 'game_complete' && ($data['multiplier'] ?? 1) >= 3.0);
                break;
            case 'broke_the_bank':
                $unlock = ($user['balance'] ?? 0) >= 500;
                break;
        }

        if ($unlock) {
            $pdo->prepare('INSERT IGNORE INTO user_achievements (user_id, achievement_id) VALUES (?, ?)')
                ->execute([$user_id, $ach['id']]);
            if ($ach['reward'] > 0) {
                $pdo->prepare('UPDATE users SET balance = balance + ? WHERE id = ?')
                    ->execute([$ach['reward'], $user_id]);
            }
            $new_achievements[] = $ach;
            log_info('ACHIEVEMENT', 'Achievement unlocked', ['slug' => $ach['slug'], 'reward' => $ach['reward']]);
        }
    }

    return $new_achievements;
}

function get_user_achievements(int $user_id): array {
    $pdo = get_db();
    $stmt = $pdo->prepare('
        SELECT a.*, ua.earned_at 
        FROM achievements a 
        JOIN user_achievements ua ON a.id = ua.achievement_id 
        WHERE ua.user_id = ?
    ');
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}