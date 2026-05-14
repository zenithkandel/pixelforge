<?php

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond_error('method_not_allowed', 'Only POST requests are allowed', 405);
}

$data = get_request_json();
$username_or_email = sanitize_string($data['username_or_email'] ?? '');
$password = $data['password'] ?? '';

if (empty($username_or_email) || empty($password)) {
    respond_error('missing_fields', 'Username/email and password are required');
}

// Rate limit login attempts
$ip = get_client_ip();
if (!RateLimit::check("login_attempt:$ip", 5, 900)) {
    respond_error('rate_limited', 'Too many login attempts. Please try again in 15 minutes', 429);
}

try {
    // Find user by username or email
    $user = Database::fetch(
        'SELECT id, username, password_hash, is_banned, is_email_verified FROM users WHERE LOWER(username) = LOWER(?) OR LOWER(email) = LOWER(?)',
        [$username_or_email, $username_or_email]
    );

    if (!$user || !password_verify($password, $user['password_hash'])) {
        // Log failed attempt
        Database::execute(
            'INSERT INTO login_attempts (email_or_username, ip_address, is_successful, failed_login_count) VALUES (?, ?, 0, 1)',
            [$username_or_email, $ip]
        );

        respond_error('invalid_credentials', 'Invalid username/email or password', 401);
    }

    if ($user['is_banned']) {
        respond_error('account_banned', 'This account has been banned', 403);
    }

    // Login successful
    // Check for daily first login (for streaks)
    $today = date('Y-m-d');
    $last_login_date = Database::fetch(
        'SELECT last_login_date FROM users WHERE id = ?',
        [$user['id']]
    );

    $is_first_login_today = !$last_login_date || $last_login_date['last_login_date'] !== $today;
    $new_streak = 0;

    if ($is_first_login_today) {
        // Calculate streak
        $last_date = Database::fetch(
            'SELECT last_login_date FROM users WHERE id = ?',
            [$user['id']]
        );

        if ($last_date && $last_date['last_login_date']) {
            $date_diff = (strtotime($today) - strtotime($last_date['last_login_date'])) / 86400;
            if ($date_diff == 1) {
                // Streak continues
                $streak_result = Database::fetch('SELECT login_streak FROM users WHERE id = ?', [$user['id']]);
                $new_streak = ($streak_result['login_streak'] ?? 0) + 1;
            } else {
                // Streak broken
                $new_streak = 1;
            }
        } else {
            $new_streak = 1;
        }

        // Update last login date and streak
        Database::execute(
            'UPDATE users SET last_login_date = ?, login_streak = ?, last_login_at = NOW() WHERE id = ?',
            [$today, $new_streak, $user['id']]
        );

        // Award daily login streak bonus
        $streaks = [1 => 2, 2 => 3, 3 => 5, 5 => 8, 7 => 15, 14 => 25, 30 => 50];
        foreach (array_reverse($streaks, true) as $days => $pxl) {
            if ($new_streak >= $days) {
                credit_pxl($user['id'], $pxl, 'streak_bonus', null, "Streak bonus for {$days} day(s)");
                break;
            }
        }

        // Check and grant streak achievements
        check_and_grant_achievements($user['id'], 'login', ['streak' => $new_streak]);
    } else {
        Database::execute(
            'UPDATE users SET last_login_at = NOW() WHERE id = ?',
            [$user['id']]
        );
    }

    // Log successful login
    Database::execute(
        'INSERT INTO login_attempts (user_id, email_or_username, ip_address, is_successful) VALUES (?, ?, ?, 1)',
        [$user['id'], $username_or_email, $ip]
    );

    // Create session
    login_user($user['id']);

    respond_success(
        [
            'user_id' => $user['id'],
            'username' => $user['username'],
            'is_email_verified' => $user['is_email_verified']
        ],
        'Login successful'
    );

} catch (Exception $e) {
    log_error('Login failed', ['error' => $e->getMessage()]);
    respond_error('login_failed', 'An error occurred during login', 500);
}

?>
