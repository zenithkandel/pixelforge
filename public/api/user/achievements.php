<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

require_method('GET');

$user = require_auth();

try {
    $achievements = get_user_achievements(get_db(), $user['id']);

    $earned_count = count(array_filter($achievements, fn($a) => $a['earned']));
    $total_pxl = array_sum(array_map(fn($a) => $a['earned'] && !$a['pxl_claimed'] ? $a['pxl_reward'] : 0, $achievements));

    respond_success([
        'achievements' => $achievements,
        'earned_count' => $earned_count,
        'total_unclaimed_pxl' => $total_pxl
    ]);

} catch (Exception $e) {
    log_error('Achievements fetch failed', ['exception' => $e->getMessage()]);
    respond_error('server_error', 'Failed to fetch achievements', 500);
}