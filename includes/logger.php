<?php

function log_message($level, $message, $context = []) {
    $log_file = LOG_DIR . '/' . $level . '.log';
    
    $timestamp = date('Y-m-d H:i:s');
    $context_str = empty($context) ? '' : ' | ' . json_encode($context);
    $log_entry = "[$timestamp] $message$context_str\n";
    
    error_log($log_entry, 3, $log_file);
}

function log_error($message, $context = []) {
    log_message('error', $message, $context);
}

function log_info($message, $context = []) {
    log_message('info', $message, $context);
}

function log_warning($message, $context = []) {
    log_message('warning', $message, $context);
}

function log_audit($action, $user_id, $details = []) {
    $log_file = LOG_DIR . '/audit.log';
    
    $timestamp = date('Y-m-d H:i:s');
    $ip = get_client_ip();
    $details_str = json_encode($details);
    $log_entry = "[$timestamp] IP: $ip | User: $user_id | Action: $action | Details: $details_str\n";
    
    error_log($log_entry, 3, $log_file);
}

?>
