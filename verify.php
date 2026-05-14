<?php require_once __DIR__ . '/includes/bootstrap.php';

$token = sanitize_string($_GET['token'] ?? '');

if (empty($token)) {
    die('Invalid verification link');
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email - PixelForge</title>
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body>
    <div class="verify-container">
        <div class="verify-card">
            <h2>Verifying Email...</h2>
            <div class="spinner"></div>
            <p id="status">Please wait...</p>
        </div>
    </div>

    <script type="module">
        import { ApiClient } from '/assets/js/api.js';
        
        const token = new URLSearchParams(window.location.search).get('token');
        const statusEl = document.getElementById('status');
        const api = new ApiClient();

        async function verify() {
            try {
                const response = await fetch(`/api/auth/verify.php?token=${encodeURIComponent(token)}`);
                const data = await response.json();

                if (data.ok) {
                    statusEl.textContent = '✓ Email verified! Redirecting...';
                    setTimeout(() => {
                        window.location.href = '/index.php';
                    }, 2000);
                } else {
                    statusEl.textContent = '✗ ' + (data.message || 'Verification failed');
                }
            } catch (e) {
                statusEl.textContent = '✗ An error occurred';
            }
        }

        verify();
    </script>
</body>
</html>
