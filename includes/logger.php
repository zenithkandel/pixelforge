<?php
/**
 * Logging Functions
 */

function log_error(string $message, array $context = []): void {
    $log_file = APP_ROOT . '/logs/errors.log';
    $timestamp = date('Y-m-d H:i:s');
    $context_str = !empty($context) ? ' ' . json_encode($context) : '';
    $log_entry = "[{$timestamp}] {$message}{$context_str}\n";

    @file_put_contents($log_file, $log_entry, FILE_APPEND);
}

function log_audit(string $action, int $user_id = null, array $details = []): void {
    $log_file = APP_ROOT . '/logs/audit.log';
    $timestamp = date('Y-m-d H:i:s');
    $user_str = $user_id ? "user_id={$user_id}" : 'anonymous';
    $details_str = !empty($details) ? ' ' . json_encode($details) : '';
    $log_entry = "[{$timestamp}] {$action} {$user_str}{$details_str}\n";

    @file_put_contents($log_file, $log_entry, FILE_APPEND);
}

function log_security(string $event, string $ip_address, array $details = []): void {
    $log_file = APP_ROOT . '/logs/security.log';
    $timestamp = date('Y-m-d H:i:s');
    $details_str = !empty($details) ? ' ' . json_encode($details) : '';
    $log_entry = "[{$timestamp}] {$event} ip={$ip_address}{$details_str}\n";

    @file_put_contents($log_file, $log_entry, FILE_APPEND);
}

function log_game_event(string $event, int $user_id, array $details = []): void {
    $log_file = APP_ROOT . '/logs/game.log';
    $timestamp = date('Y-m-d H:i:s');
    $details_str = !empty($details) ? ' ' . json_encode($details) : '';
    $log_entry = "[{$timestamp}] {$event} user_id={$user_id}{$details_str}\n";

    @file_put_contents($log_file, $log_entry, FILE_APPEND);
}

function get_client_ip(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'];
        if (str_contains($forwarded, ',')) {
            $ips = explode(',', $forwarded);
            $ip = trim($ips[0]);
        }
    }

    if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        $ip = $_SERVER['HTTP_X_REAL_IP'];
    }

    return $ip;
}