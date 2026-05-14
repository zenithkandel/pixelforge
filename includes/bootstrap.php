<?php
/**
 * PixelForge Bootstrap
 * Must be included at the start of every PHP request
 */

if (defined('PIXELFORGE_BOOTSTRAPPED')) {
    return;
}

define('PIXELFORGE_BOOTSTRAPPED', true);

require_once dirname(__DIR__) . '/includes/config.php';

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

function send_security_headers(): void {
    header('Content-Security-Policy: default-src \'self\'; script-src \'self\'; style-src \'self\' \'unsafe-inline\' https://fonts.googleapis.com; img-src \'self\' data: blob:; connect-src \'self\'; font-src \'self\' https://fonts.gstatic.com; media-src \'self\'; object-src \'none\'; base-uri \'self\'; form-action \'self\'; frame-ancestors \'none\';');
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()');
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    }
}

function init_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    if (!isset($_SESSION['created'])) {
        $_SESSION['created'] = time();
    }

    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TTL)) {
        session_unset();
        session_destroy();
        session_start();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['created'] = time();
    }

    $_SESSION['last_activity'] = time();
}

function handle_exception(Throwable $e): void {
    $log_file = APP_ROOT . '/logs/errors.log';
    $message = sprintf(
        "[%s] %s in %s:%d\nStack trace:\n%s\n",
        date('Y-m-d H:i:s'),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    );

    if (defined('APP_ROOT')) {
        @file_put_contents($log_file, $message, FILE_APPEND);
    }

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json');
    }

    echo json_encode([
        'ok' => false,
        'error' => 'server_error',
        'message' => 'An unexpected error occurred'
    ]);
    exit;
}

set_exception_handler('handle_exception');
send_security_headers();
init_session();

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function is_authenticated(): bool {
    return !empty($_SESSION['user_id']);
}

function require_auth(): array {
    if (!is_authenticated()) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'unauthenticated', 'message' => 'Login required']);
        exit;
    }
    return get_current_user();
}

function require_verified(): array {
    $user = require_auth();
    if (empty($user['email_verified'])) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'email_not_verified', 'message' => 'Please verify your email first']);
        exit;
    }
    return $user;
}

function require_method(string $method): void {
    if ($_SERVER['REQUEST_METHOD'] !== strtoupper($method)) {
        http_response_code(405);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'method_not_allowed', 'message' => 'Method not allowed']);
        exit;
    }
}

function get_json_body(): array {
    $body = file_get_contents('php://input');
    $data = json_decode($body, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'invalid_json', 'message' => 'Invalid JSON body']);
        exit;
    }
    return $data ?? [];
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

function get_db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_FOUND_ROWS => true,
            PDO::ATTR_PERSISTENT => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        $pdo->exec("SET time_zone = '+00:00'");
    }
    return $pdo;
}

function get_redis(): ?Redis {
    if (!class_exists('Redis')) {
        return null;
    }
    static $redis = null;
    if ($redis === null) {
        try {
            $redis = new Redis();
            $redis->connect(REDIS_HOST, REDIS_PORT, 2.0);
            if (REDIS_PASS) {
                $redis->auth(REDIS_PASS);
            }
            $redis->select(REDIS_DB);
        } catch (Exception $e) {
            error_log("Redis connection failed: " . $e->getMessage());
            return null;
        }
    }
    return $redis;
}

function get_current_user(): ?array {
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    try {
        $pdo = get_db();
        $stmt = $pdo->prepare("SELECT id, username, email, pxl_balance, total_pxl_earned, total_pxl_spent, login_streak, last_login_date, email_verified, is_banned, created_at FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch() ?: null;
    } catch (Exception $e) {
        return null;
    }
}

function verify_csrf(string $token): bool {
    if (empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function check_rate_limit(string $key, int $max_hits, int $window_seconds): bool {
    $redis = get_redis();
    if (!$redis) {
        return true;
    }

    $now = microtime(true);
    $window_start = $now - $window_seconds;
    $redis_key = "rl:{$key}";

    try {
        $redis->multi();
        $redis->zRemRangeByScore($redis_key, 0, $window_start);
        $redis->zAdd($redis_key, $now, $now . '_' . random_int(0, 999999));
        $redis->zCard($redis_key);
        $redis->expire($redis_key, $window_seconds + 1);
        $results = $redis->exec();

        return ($results[2] ?? 0) <= $max_hits;
    } catch (Exception $e) {
        error_log("Rate limit check failed: " . $e->getMessage());
        return true;
    }
}

function validate_username(string $v): bool {
    return (bool)preg_match('/^[a-zA-Z0-9_]{3,20}$/', $v);
}

function validate_email(string $v): bool {
    return (bool)filter_var($v, FILTER_VALIDATE_EMAIL) && strlen($v) <= 255;
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