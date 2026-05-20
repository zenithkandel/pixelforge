<?php

function check_achievements(PDO $db, int $user_id): array {
    $unlocked = [];

    try {
        $stmt = $db->prepare('SELECT * FROM achievements');
        $stmt->execute();
        $all = $stmt->fetchAll();

        $stmt = $db->prepare('SELECT achievement_id FROM user_achievements WHERE user_id = ?');
        $stmt->execute([$user_id]);
        $earned = array_column($stmt->fetchAll(), 'achievement_id');

        $user = get_user_stats($db, $user_id);
        if (!$user) return [];

        $best_score = $user['best_score'] ?? 0;
        $streak = (int)($user['streak_days'] ?? 0);
        $level = (int)($user['level'] ?? 1);
        $pixels_owned = (int)($user['pixels_owned'] ?? 0);
        $best_multiplier = (float)($user['best_multiplier'] ?? 1.0);

        $checks = [
            'first_flight'   => fn() => $best_score > 0,
            'score_10'       => fn() => $best_score >= 10,
            'score_50'       => fn() => $best_score >= 50,
            'score_100'      => fn() => $best_score >= 100,
            'first_pixel'    => fn() => $pixels_owned >= 1,
            'pixel_5'        => fn() => $pixels_owned >= 5,
            'pixel_25'       => fn() => $pixels_owned >= 25,
            'pixel_100'      => fn() => $pixels_owned >= 100,
            'streak_3'       => fn() => $streak >= 3,
            'streak_7'       => fn() => $streak >= 7,
            'streak_30'      => fn() => $streak >= 30,
            'level_5'        => fn() => $level >= 5,
            'level_10'       => fn() => $level >= 10,
            'level_20'       => fn() => $level >= 20,
            'multiplier_3x'  => fn() => $best_multiplier >= 3.0,
        ];

        foreach ($all as $ach) {
            if (in_array($ach['id'], $earned)) continue;

            if (isset($checks[$ach['slug']]) && $checks[$ach['slug']]()) {
                $db->prepare('INSERT IGNORE INTO user_achievements (user_id, achievement_id) VALUES (?, ?)')
                   ->execute([$user_id, $ach['id']]);

                $db->prepare('UPDATE users SET balance = balance + ? WHERE id = ?')
                   ->execute([(int)$ach['reward'], $user_id]);

                log_info('ACHIEVEMENT', 'Achievement unlocked', ['slug' => $ach['slug'], 'reward' => $ach['reward']]);

                $unlocked[] = [
                    'slug'        => $ach['slug'],
                    'name'        => $ach['name'],
                    'icon'        => $ach['icon'],
                    'reward'      => (int)$ach['reward'],
                    'description' => $ach['description'],
                ];
            }
        }

        if (!empty($unlocked)) {
            $total_xp = count($unlocked) * 20;
            add_xp($db, $user_id, $total_xp, true);
        }
    } catch (PDOException $e) {
        log_error('DB', 'Achievement check error: ' . $e->getMessage(), ['code' => $e->getCode()]);
    }

    return $unlocked;
}

function check_specific_achievements(PDO $db, int $user_id, string $category): array {
    return check_achievements($db, $user_id);
}

function get_user_stats(PDO $db, int $user_id): ?array {
    try {
        $stmt = $db->prepare('
            SELECT u.*,
                   (SELECT MAX(score) FROM score_log WHERE user_id = u.id) AS best_score,
                   (SELECT MAX(multiplier) FROM score_log WHERE user_id = u.id) AS best_multiplier,
                   (SELECT COUNT(*) FROM pixels WHERE owner_id = u.id) AS pixels_owned,
                   (SELECT COUNT(*) FROM user_achievements WHERE user_id = u.id) AS achievement_count
            FROM users u WHERE u.id = ?
        ');
        $stmt->execute([$user_id]);
        return $stmt->fetch() ?: null;
    } catch (PDOException $e) {
        log_error('DB', 'get_user_stats error: ' . $e->getMessage(), ['code' => $e->getCode()]);
        return null;
    }
}

function get_user_achievements(PDO $db, int $user_id): array {
    try {
        $stmt = $db->prepare('
            SELECT a.* FROM achievements a
            JOIN user_achievements ua ON a.id = ua.achievement_id
            WHERE ua.user_id = ?
        ');
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        log_error('DB', 'get_user_achievements error: ' . $e->getMessage(), ['code' => $e->getCode()]);
        return [];
    }
}

function get_all_achievements(PDO $db): array {
    try {
        $stmt = $db->query('SELECT * FROM achievements');
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        log_error('DB', 'get_all_achievements error: ' . $e->getMessage(), ['code' => $e->getCode()]);
        return [];
    }
}
