<?php

function xp_for_level(int $level): int {
    return $level * $level * 50;
}

function level_from_xp(int $xp): int {
    return (int)floor(1 + sqrt($xp / 50));
}

function xp_for_next_level(int $level): int {
    return xp_for_level($level);
}

function xp_progress_in_level(int $xp): float {
    $level = level_from_xp($xp);
    $xp_at_level = xp_for_level($level - 1);
    $xp_needed = xp_for_level($level) - $xp_at_level;
    $xp_current = $xp - $xp_at_level;
    return $xp_needed > 0 ? $xp_current / $xp_needed : 1.0;
}

function add_xp(PDO $db, int $user_id, int $amount): array {
    try {
        $db->beginTransaction();

        $stmt = $db->prepare('SELECT xp, level FROM users WHERE id = ? FOR UPDATE');
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        if (!$user) {
            $db->rollBack();
            return ['leveled_up' => false, 'new_level' => 0, 'new_xp' => 0];
        }

        $new_xp = $user['xp'] + $amount;
        $new_level = level_from_xp($new_xp);
        $old_level = (int)$user['level'];
        $leveled_up = $new_level > $old_level;

        $stmt = $db->prepare('UPDATE users SET xp = ?, level = ? WHERE id = ?');
        $stmt->execute([$new_xp, $new_level, $user_id]);

        $db->commit();

        if ($leveled_up) {
            log_info('XP', 'User levelled up', ['new_level' => $new_level, 'total_xp' => $new_xp]);
        }

        return [
            'leveled_up' => $leveled_up,
            'new_level'  => $new_level,
            'new_xp'     => $new_xp,
        ];
    } catch (PDOException $e) {
        $db->rollBack();
        log_error('DB', 'XP update error: ' . $e->getMessage(), ['code' => $e->getCode(), 'user_id' => $user_id]);
        return ['leveled_up' => false, 'new_level' => 0, 'new_xp' => 0];
    }
}
