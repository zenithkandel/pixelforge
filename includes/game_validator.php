<?php
/**
 * Game Anti-Cheat Validator
 */

function validate_game_session(PDO $pdo, string $session_id, int $user_id): ?array {
    $stmt = $pdo->prepare("SELECT * FROM game_sessions WHERE id = ? AND user_id = ? AND ended_at IS NULL");
    $stmt->execute([$session_id, $user_id]);
    $session = $stmt->fetch();

    if (!$session) {
        return null;
    }

    return $session;
}

function verify_game_hmac(string $session_id, int $score, int $elapsed_ms, string $provided_hmac): bool {
    $expected = hash_hmac('sha256', "{$session_id}:{$score}:{$elapsed_ms}", GAME_HMAC_KEY);
    return hash_equals($expected, $provided_hmac);
}

function verify_checkpoint_hmac(string $session_id, int $score, int $elapsed_ms, string $provided_hmac): bool {
    $expected = hash_hmac('sha256', "{$session_id}:{$score}:{$elapsed_ms}", GAME_HMAC_KEY);
    return hash_equals($expected, $provided_hmac);
}

function validate_score_plausibility(int $score, int $duration_ms, array $checkpoints = []): bool {
    if ($duration_ms < 1000) {
        return false;
    }

    $duration_sec = $duration_ms / 1000;

    if ($score / $duration_sec > MAX_SCORE_PER_SECOND_HARD) {
        return false;
    }

    if ($duration_sec > 30 && $score / $duration_sec > MAX_SCORE_PER_SECOND_SUSTAINED) {
        return false;
    }

    if (!empty($checkpoints)) {
        $prev_score = 0;
        $prev_time = 0;

        foreach ($checkpoints as $cp) {
            $cp_score = (int)($cp['score'] ?? 0);
            $cp_time = (int)($cp['elapsed_ms'] ?? 0);

            if ($cp_score < $prev_score) {
                return false;
            }

            if ($cp_time <= $prev_time) {
                return false;
            }

            $delta_score = $cp_score - $prev_score;
            $delta_time = ($cp_time - $prev_time) / 1000;

            if ($delta_time > 0 && $delta_score / $delta_time > MAX_SCORE_PER_SECOND_HARD) {
                return false;
            }

            $prev_score = $cp_score;
            $prev_time = $cp_time;
        }

        if ($score < $prev_score) {
            return false;
        }
    }

    return true;
}

function calculate_pxl_earned(int $final_score, bool $is_first_game_today, bool $is_daily_highscore, int $max_combo): int {
    $base_pxl = calculate_pxl_from_score($final_score);

    $multiplier = 1;
    if ($is_first_game_today) {
        $multiplier *= 2;
    }

    $pxl_earned = $base_pxl * $multiplier;

    if ($is_daily_highscore) {
        $pxl_earned += 5;
    }

    if ($max_combo >= 35) {
        $pxl_earned += 10;
    } elseif ($max_combo >= 20) {
        $pxl_earned += 5;
    } elseif ($max_combo >= 10) {
        $pxl_earned += 2;
    } elseif ($max_combo >= 5) {
        $pxl_earned += 1;
    }

    return $pxl_earned;
}

function get_daily_highscore(PDO $pdo, int $user_id): int {
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT MAX(score) as highscore FROM scores WHERE user_id = ? AND DATE(created_at) = ?");
    $stmt->execute([$user_id, $today]);
    $result = $stmt->fetch();
    return (int)($result['highscore'] ?? 0);
}

function is_daily_highscore(PDO $pdo, int $user_id, int $score): bool {
    $current_best = get_daily_highscore($pdo, $user_id);
    return $score > $current_best;
}

function get_user_daily_rank(PDO $pdo, int $user_id): int {
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("
        SELECT COUNT(*) + 1 as rank FROM (
            SELECT user_id, MAX(score) as max_score
            FROM scores
            WHERE DATE(created_at) = ?
            GROUP BY user_id
            HAVING max_score > (
                SELECT COALESCE(MAX(score), 0) FROM scores WHERE user_id = ? AND DATE(created_at) = ?
            )
        ) sub
    ");
    $stmt->execute([$today, $user_id, $today]);
    $result = $stmt->fetch();
    return (int)($result['rank'] ?? 1);
}

function generate_game_session_key(): string {
    return bin2hex(random_bytes(32));
}

function generate_prng_seed(): int {
    return random_int(0, PHP_INT_MAX);
}

function derive_client_key(string $session_id): string {
    return hash_hmac('sha256', $session_id, GAME_HMAC_KEY);
}