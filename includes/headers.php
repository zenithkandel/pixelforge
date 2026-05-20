<?php

if (empty($GLOBALS['csp_nonce'])) {
    $GLOBALS['csp_nonce'] = bin2hex(random_bytes(16));
}

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$GLOBALS['csp_nonce']}'; style-src 'self' 'unsafe-inline'; img-src 'self' data:;");
