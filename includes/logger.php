<?php

define('LOG_FILE', __DIR__ . '/../logs/event.log');
define('LOG_MAX_BYTES', 10 * 1024 * 1024);

if (!is_dir(__DIR__ . '/../logs')) {
    mkdir(__DIR__ . '/../logs', 0755, true);
}

function app_log(string $level, string $category, string $message, array $context = []): void {
    if (file_exists(LOG_FILE) && filesize(LOG_FILE) > LOG_MAX_BYTES) {
        rename(LOG_FILE, LOG_FILE . '.' . date('Ymd-His') . '.bak');
    }

    $user_id  = $_SESSION['user_id']  ?? 'guest';
    $username = $_SESSION['username'] ?? 'guest';
    $ip       = $_SERVER['REMOTE_ADDR']     ?? 'unknown';
    $uri      = $_SERVER['REQUEST_URI']     ?? 'unknown';
    $method   = $_SERVER['REQUEST_METHOD']  ?? 'unknown';

    $ctx_str = empty($context) ? '' : ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $line = sprintf(
        "[%s] [%s] [%s] [user:%s(%s)] [ip:%s] [%s %s] %s%s\n",
        date('Y-m-d H:i:s'),
        strtoupper($level),
        strtoupper($category),
        $user_id,
        $username,
        $ip,
        $method,
        $uri,
        $message,
        $ctx_str
    );

    file_put_contents(LOG_FILE, $line, FILE_APPEND | LOCK_EX);
}

function log_info(string $cat, string $msg, array $ctx = []): void  { app_log('INFO',     $cat, $msg, $ctx); }
function log_warn(string $cat, string $msg, array $ctx = []): void  { app_log('WARN',     $cat, $msg, $ctx); }
function log_error(string $cat, string $msg, array $ctx = []): void { app_log('ERROR',    $cat, $msg, $ctx); }
function log_debug(string $cat, string $msg, array $ctx = []): void { app_log('DEBUG',    $cat, $msg, $ctx); }
function log_sec(string $cat, string $msg, array $ctx = []): void   { app_log('SECURITY', $cat, $msg, $ctx); }
function log_admin(string $cat, string $msg, array $ctx = []): void { app_log('ADMIN',    $cat, $msg, $ctx); }

log_debug('SYSTEM', 'Page request received');

set_error_handler(function(int $errno, string $errstr, string $errfile, int $errline): bool {
    log_error('PHP', "PHP Error [$errno]: $errstr", ['file' => $errfile, 'line' => $errline]);
    return false;
});

set_exception_handler(function(Throwable $e): void {
    log_error('PHP', 'Uncaught Exception: ' . $e->getMessage(), [
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
        'trace' => substr($e->getTraceAsString(), 0, 500),
    ]);
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
    exit();
});

register_shutdown_function(function(): void {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        log_error('PHP', 'FATAL: ' . $err['message'], ['file' => $err['file'], 'line' => $err['line']]);
    }
});
