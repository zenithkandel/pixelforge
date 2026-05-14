<?php
declare(strict_types=1);

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function get_db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_NAME);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_FOUND_ROWS => true,
            PDO::ATTR_PERSISTENT => false,
        ]);
        $pdo->exec("SET time_zone = '+00:00'");
    }
    return $pdo;
}

function get_redis(): Redis {
    static $redis = null;
    if ($redis === null) {
        $redis = new Redis();
        $redis->connect(REDIS_HOST, REDIS_PORT, 2.0);
        if (REDIS_PASS) {
            $redis->auth(REDIS_PASS);
        }
        $redis->select(REDIS_DB);
    }
    return $redis;
}

function respond_success(array $data = [], int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

function respond_error(string $error, string $message = '', int $code = 400, array $extra = []): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['ok' => false, 'error' => $error, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

function require_method(string $method): void {
    if ($_SERVER['REQUEST_METHOD'] !== strtoupper($method)) {
        respond_error('method_not_allowed', 'Method not allowed', 405);
    }
}

function get_json_body(): array {
    $body = file_get_contents('php://input');
    $data = json_decode($body, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        respond_error('invalid_json', 'Invalid JSON body', 400);
    }
    return $data ?? [];
}

function validate_username(string $v): bool {
    return (bool)preg_match('/^[a-zA-Z0-9_]{3,20}$/', $v);
}

function validate_email(string $v): bool {
    return filter_var($v, FILTER_VALIDATE_EMAIL) !== false && strlen($v) <= 255;
}

function validate_password(string $v): bool {
    return strlen($v) >= 8 && strlen($v) <= 128 && preg_match('/[a-zA-Z]/', $v) && preg_match('/[0-9]/', $v);
}

function validate_color(string $v): bool {
    return (bool)preg_match('/^#[0-9A-Fa-f]{6}$/', $v);
}

function validate_coord(mixed $v): bool {
    return is_numeric($v) && (int)$v >= 0 && (int)$v <= 2047;
}

function validate_chunk_coord(mixed $v): bool {
    return is_numeric($v) && (int)$v >= 0 && (int)$v <= 31;
}

function validate_positive_int(mixed $v): bool {
    return filter_var($v, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) !== false;
}

function check_rate_limit(string $key, int $max_hits, int $window_seconds): bool {
    $redis = get_redis();
    $now = microtime(true);
    $window_start = $now - $window_seconds;
    $redis_key = "rl:{$key}";

    $redis->multi();
    $redis->zRemRangeByScore($redis_key, '0', (string)$window_start);
    $redis->zAdd($redis_key, (string)$now, $now . '_' . random_int(0, 999999));
    $redis->zCard($redis_key);
    $redis->expire($redis_key, $window_seconds + 1);
    $results = $redis->exec();

    return (int)$results[2] <= $max_hits;
}

function send_email(string $to, string $subject, string $html_body, string $text_body = ''): bool {
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=utf-8',
        'From: ' . SMTP_FROM_NAME . ' <' . SMTP_FROM . '>',
        'Reply-To: ' . SMTP_FROM,
        'X-Mailer: PHP/' . phpversion(),
    ];

    if ($text_body !== '') {
        $headers[] = 'Content-type: multipart/alternative; boundary="boundary"';
    }

    return mail($to, $subject, $html_body, implode("\r\n", $headers));
}

function send_verification_email(string $email, string $username, string $token): bool {
    $verify_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
        . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
        . '/verify.php?token=' . urlencode($token);

    $subject = 'Verify your PixelForge account';

    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Verify Email</title></head><body style="font-family:Arial,sans-serif;background:#111318;color:#e5e7eb;padding:40px;">'
        . '<div style="max-width:500px;margin:0 auto;background:#1c2029;border-radius:12px;padding:32px;">'
        . '<h1 style="color:#5b4fff;margin:0 0 24px;">PixelForge</h1>'
        . '<p style="font-size:16px;margin:0 0 16px;">Welcome, <strong>' . htmlspecialchars($username) . '</strong>!</p>'
        . '<p style="font-size:14px;color:#9ca3af;margin:0 0 32px;">Click the button below to verify your email address and start your journey.</p>'
        . '<a href="' . htmlspecialchars($verify_url) . '" style="display:inline-block;background:#5b4fff;color:white;padding:14px 28px;border-radius:8px;text-decoration:none;font-weight:600;font-size:16px;">VERIFY EMAIL</a>'
        . '<p style="font-size:12px;color:#6b7280;margin:32px 0 0;">This link expires in 24 hours.</p>'
        . '</div></body></html>';

    $text = "Welcome, {$username}!\n\nVerify your email: {$verify_url}\n\nThis link expires in 24 hours.";

    return send_email($email, $subject, $html, $text);
}

function send_password_reset_email(string $email, string $username, string $token): bool {
    $reset_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
        . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
        . '/reset-password.php?token=' . urlencode($token);

    $subject = 'Reset your PixelForge password';

    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Reset Password</title></head><body style="font-family:Arial,sans-serif;background:#111318;color:#e5e7eb;padding:40px;">'
        . '<div style="max-width:500px;margin:0 auto;background:#1c2029;border-radius:12px;padding:32px;">'
        . '<h1 style="color:#5b4fff;margin:0 0 24px;">PixelForge</h1>'
        . '<p style="font-size:16px;margin:0 0 16px;">Hello, <strong>' . htmlspecialchars($username) . '</strong>!</p>'
        . '<p style="font-size:14px;color:#9ca3af;margin:0 0 32px;">Click the button to reset your password. This link expires in 1 hour.</p>'
        . '<a href="' . htmlspecialchars($reset_url) . '" style="display:inline-block;background:#5b4fff;color:white;padding:14px 28px;border-radius:8px;text-decoration:none;font-weight:600;font-size:16px;">RESET PASSWORD</a>'
        . '<p style="font-size:12px;color:#6b7280;margin:32px 0 0;">If you did not request this, ignore this email.</p>'
        . '</div></body></html>';

    $text = "Hello, {$username}!\n\nReset your password: {$reset_url}\n\nThis link expires in 1 hour.";

    return send_email($email, $subject, $html, $text);
}

function credit_pxl(PDO $pdo, int $user_id, int $amount, string $type, string $ref = '', string $desc = ''): int {
    $stmt = $pdo->prepare("UPDATE users SET pxl_balance = pxl_balance + ?, total_pxl_earned = total_pxl_earned + ? WHERE id = ?");
    $stmt->execute([$amount, $amount, $user_id]);

    $stmt = $pdo->prepare("SELECT pxl_balance FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $new_balance = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("INSERT INTO pxl_transactions (user_id, amount, type, reference_id, balance_after, description) VALUES (?,?,?,?,?,?)");
    $stmt->execute([$user_id, $amount, $type, $ref, $new_balance, $desc]);

    return $new_balance;
}

function debit_pxl(PDO $pdo, int $user_id, int $amount, string $type, string $ref = '', string $desc = ''): int {
    $stmt = $pdo->prepare("UPDATE users SET pxl_balance = pxl_balance - ?, total_pxl_spent = total_pxl_spent + ? WHERE id = ? AND pxl_balance >= ?");
    $stmt->execute([$amount, $amount, $user_id, $amount]);

    if ($stmt->rowCount() === 0) {
        throw new RuntimeException('Insufficient balance');
    }

    $stmt = $pdo->prepare("SELECT pxl_balance FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $new_balance = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("INSERT INTO pxl_transactions (user_id, amount, type, reference_id, balance_after, description) VALUES (?,?,?,?,?,?)");
    $stmt->execute([$user_id, -$amount, $type, $ref, $new_balance, $desc]);

    return $new_balance;
}

function check_and_grant_achievements(PDO $pdo, int $user_id, string $context, array $context_data = []): array {
    $unlocked = [];

    $stmt = $pdo->prepare("
        SELECT u.*,
            COUNT(DISTINCT ph.id) as total_pixels,
            MAX(s.score) as best_score,
            MAX(s.max_speed_tier) as best_speed_tier
        FROM users u
        LEFT JOIN pixel_history ph ON ph.user_id = u.id
        LEFT JOIN scores s ON s.user_id = u.id
        WHERE u.id = ?
        GROUP BY u.id
    ");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch();

    $to_check = match($context) {
        'game_submit' => ['first_game', 'speed_tier_3', 'speed_tier_5', 'speed_tier_7',
                          'score_500', 'score_2000', 'score_5000', 'score_10000',
                          'combo_15', 'combo_35', 'rainbow_5', 'bomb_used', 'total_earned_100'],
        'pixel_buy' => ['first_pixel', 'pixels_50', 'pixels_250', 'pixels_1000'],
        'login' => ['streak_3', 'streak_7', 'streak_30'],
        default => []
    };

    if (empty($to_check)) {
        return $unlocked;
    }

    $stmt = $pdo->prepare("SELECT achievement_id FROM user_achievements WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $earned_ids = array_column($stmt->fetchAll(), 'achievement_id');

    $stmt = $pdo->prepare("SELECT * FROM achievements WHERE key_name IN (" . implode(',', array_fill(0, count($to_check), '?')) . ")");
    $stmt->execute($to_check);
    $achievements = $stmt->fetchAll();

    foreach ($achievements as $ach) {
        if (in_array($ach['id'], $earned_ids)) {
            continue;
        }

        $earned = match($ach['key_name']) {
            'first_game' => true,
            'speed_tier_3' => ($context_data['max_speed_tier'] ?? 0) >= 3,
            'speed_tier_5' => ($context_data['max_speed_tier'] ?? 0) >= 5,
            'speed_tier_7' => ($context_data['max_speed_tier'] ?? 0) >= 7,
            'score_500' => ($context_data['final_score'] ?? 0) >= 500,
            'score_2000' => ($context_data['final_score'] ?? 0) >= 2000,
            'score_5000' => ($context_data['final_score'] ?? 0) >= 5000,
            'score_10000' => ($context_data['final_score'] ?? 0) >= 10000,
            'combo_15' => ($context_data['max_combo'] ?? 0) >= 15,
            'combo_35' => ($context_data['max_combo'] ?? 0) >= 35,
            'rainbow_5' => ($context_data['prisms_collected'] ?? 0) >= 5,
            'bomb_used' => ($context_data['bomb_used'] ?? false) === true,
            'first_pixel' => (int)($stats['total_pixels'] ?? 0) >= 1,
            'pixels_50' => (int)($stats['total_pixels'] ?? 0) >= 50,
            'pixels_250' => (int)($stats['total_pixels'] ?? 0) >= 250,
            'pixels_1000' => (int)($stats['total_pixels'] ?? 0) >= 1000,
            'total_earned_100' => (int)($stats['total_pxl_earned'] ?? 0) >= 100,
            'streak_3' => (int)($stats['login_streak'] ?? 0) >= 3,
            'streak_7' => (int)($stats['login_streak'] ?? 0) >= 7,
            'streak_30' => (int)($stats['login_streak'] ?? 0) >= 30,
            default => false
        };

        if ($earned) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO user_achievements (user_id, achievement_id) VALUES (?, ?)");
            $stmt->execute([$user_id, $ach['id']]);
            $unlocked[] = ['key' => $ach['key_name'], 'title' => $ach['title'], 'pxl' => $ach['pxl_reward']];
        }
    }

    return $unlocked;
}

function build_chunk_cache(int $cx, int $cy): string {
    $pdo = get_db();
    $x_min = $cx * 64;
    $x_max = $x_min + 63;
    $y_min = $cy * 64;
    $y_max = $y_min + 63;

    $stmt = $pdo->prepare("SELECT x, y, color FROM pixels WHERE x BETWEEN ? AND ? AND y BETWEEN ? AND ? AND grid_session_id = (SELECT id FROM grid_sessions WHERE is_current=1)");
    $stmt->execute([$x_min, $x_max, $y_min, $y_max]);
    $rows = $stmt->fetchAll();

    $buffer = str_repeat("\xFF\xFF\xFF", 64 * 64);

    foreach ($rows as $row) {
        $lx = $row['x'] - $x_min;
        $ly = $row['y'] - $y_min;
        $offset = ($ly * 64 + $lx) * 3;
        $color = $row['color'];
        $buffer[$offset] = chr((int)hexdec(substr($color, 1, 2)));
        $buffer[$offset + 1] = chr((int)hexdec(substr($color, 3, 2)));
        $buffer[$offset + 2] = chr((int)hexdec(substr($color, 5, 2)));
    }

    $redis = get_redis();
    $redis->setex("chunk:{$cx}:{$cy}", 300, $buffer);
    return $buffer;
}

function log_error(Exception $e): void {
    error_log('[' . date('Y-m-d H:i:s') . '] ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString());
}

function log_audit(string $action, int $user_id, string $details = ''): void {
    error_log('[' . date('Y-m-d H:i:s') . '] AUDIT user_id=' . $user_id . ' action=' . $action . ' ' . $details);
}