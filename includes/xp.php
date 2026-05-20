<?php

function xp_for_level(int $level): int {
    return $level * $level * 50;
}

function level_from_xp(int $xp): int {
    $level = (int)floor(1 + sqrt($xp / 50));
    return min($level, 100);
}

function xp_progress(int $xp): array {
    $level = level_from_xp($xp);
    $current_level_xp = xp_for_level($level - 1);
    $next_level_xp = xp_for_level($level);
    $progress = $level >= 100 ? 100 : (int)((($xp - $current_level_xp) / ($next_level_xp - $current_level_xp)) * 100);
    return [
        'level' => $level,
        'xp' => $xp,
        'xp_for_current' => $current_level_xp,
        'xp_for_next' => $next_level_xp,
        'progress' => min($progress, 100),
    ];
}

function add_xp(int $user_id, int $amount): array {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT xp, level FROM users WHERE id = ?');
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    if (!$user) return ['xp_added' => 0, 'leveled_up' => false, 'new_level' => 1];

    $new_xp = $user['xp'] + $amount;
    $new_level = level_from_xp($new_xp);
    $old_level = level_from_xp($user['xp']);

    $pdo->prepare('UPDATE users SET xp = ?, level = ? WHERE id = ?')
        ->execute([$new_xp, $new_level, $user_id]);

    $leveled_up = $new_level > $old_level;

    if ($leveled_up) {
        log_info('XP', 'User levelled up', ['new_level' => $new_level, 'total_xp' => $new_xp]);
    }

    return [
        'xp_added' => $amount,
        'leveled_up' => $leveled_up,
        'new_level' => $new_level,
        'new_xp' => $new_xp,
    ];
}