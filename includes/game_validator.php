<?php
// includes/game_validator.php

function validate_game_hmac($session_id, $score, $elapsed_ms, $hmac) {
    $expected = hash_hmac('sha256', $session_id . ':' . $score . ':' . $elapsed_ms, GAME_HMAC_KEY);
    return hash_equals($expected, $hmac);
}

function validate_score_plausibility($score, $elapsed_ms) {
    if ($elapsed_ms <= 0) return false;
    $seconds = $elapsed_ms / 1000;
    
    // MAX_SCORE_PER_SECOND_HARD = 200, SUSTAINED = 80
    // Give some leeway for burst score (e.g. bomb or surge)
    $max_possible = $seconds * 150 + 500; 
    
    return $score <= $max_possible;
}
