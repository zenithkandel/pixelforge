<?php

function validate_game_score($score, $duration_seconds) {
    // Max hard limit: 200 score per second
    if ($score > $duration_seconds * MAX_SCORE_PER_SECOND_HARD) {
        return false;
    }
    
    // Sustained limit: 80 score per second (more lenient over time)
    if ($duration_seconds > 60 && ($score / $duration_seconds) > MAX_SCORE_SUSTAINED) {
        return false;
    }
    
    return true;
}

function validate_checkpoint_chain($checkpoints) {
    // Verify checkpoints are in ascending order and plausible
    $last_score = 0;
    $last_time = 0;
    
    foreach ($checkpoints as $cp) {
        if (!isset($cp['score']) || !isset($cp['timestamp'])) {
            return false;
        }
        
        // Score should only increase
        if ($cp['score'] < $last_score) {
            return false;
        }
        
        // Time should only increase
        if ($cp['timestamp'] <= $last_time) {
            return false;
        }
        
        // Score increase should be plausible in the time elapsed
        $time_diff = $cp['timestamp'] - $last_time;
        $score_diff = $cp['score'] - $last_score;
        
        if ($score_diff > $time_diff * MAX_SCORE_PER_SECOND_HARD) {
            return false;
        }
        
        $last_score = $cp['score'];
        $last_time = $cp['timestamp'];
    }
    
    return true;
}

function validate_game_hmac($data, $signature) {
    $expected = hash_hmac('sha256', json_encode($data), GAME_HMAC_KEY);
    return hash_equals($expected, $signature);
}

function generate_game_session_token() {
    return bin2hex(random_bytes(32));
}

function generate_game_seed() {
    return random_int(1, 2147483647);
}

?>
