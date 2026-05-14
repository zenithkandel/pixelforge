<?php
// includes/logger.php

function log_error($message, $context = []) {
    $date = date('Y-m-d H:i:s');
    $ctx = empty($context) ? '' : json_encode($context);
    $log = "[$date] ERROR: $message $ctx\n";
    file_put_contents(dirname(__DIR__) . '/logs/error.log', $log, FILE_APPEND);
}

function log_audit($action, $user_id, $details = []) {
    $date = date('Y-m-d H:i:s');
    $ctx = empty($details) ? '' : json_encode($details);
    $log = "[$date] AUDIT ($user_id): $action $ctx\n";
    file_put_contents(dirname(__DIR__) . '/logs/audit.log', $log, FILE_APPEND);
}
