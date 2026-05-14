<?php
declare(strict_types=1);

function validate_score_plausibility(int $score, int $duration_ms, array $checkpoints): bool {
    $duration_sec = $duration_ms / 1000;
    if ($duration_sec < 1) {
        return false;
    }
    if ($score / $duration_sec > MAX_SCORE_PER_SECOND_HARD) {
        return false;
    }
    if ($duration_sec > 30 && $score / $duration_sec > MAX_SCORE_PER_SECOND_SUSTAINED) {
        return false;
    }
    $prev_score = 0;
    $prev_time = 0;
    foreach ($checkpoints as $cp) {
        if ($cp['score'] < $prev_score) {
            return false;
        }
        if ($cp['elapsed_ms'] <= $prev_time) {
            return false;
        }
        $delta_score = $cp['score'] - $prev_score;
        $delta_time = ($cp['elapsed_ms'] - $prev_time) / 1000;
        if ($delta_score / $delta_time > MAX_SCORE_PER_SECOND_HARD) {
            return false;
        }
        $prev_score = $cp['score'];
        $prev_time = $cp['elapsed_ms'];
    }
    if ($score < $prev_score) {
        return false;
    }
    return true;
}

function verify_game_hmac(string $session_id, string $data, string $expected_hmac): bool {
    $expected = hash_hmac('sha256', $session_id . ':' . $data, GAME_HMAC_KEY);
    return hash_equals($expected, $expected_hmac);
}