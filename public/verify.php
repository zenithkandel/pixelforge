<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

$token = $_GET['token'] ?? '';
$status = $_GET['status'] ?? null;

if ($token) {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT id, email FROM users WHERE email_verify_token = ? AND email_verify_expires > NOW() AND email_verified = 0");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        $pdo->prepare("UPDATE users SET email_verified = 1, email_verify_token = NULL, email_verify_expires = NULL WHERE id = ?")->execute([$user['id']]);
        $status = 'success';
    } else {
        $status = 'invalid';
    }
} else {
    $status = 'missing';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Email Verification — PixelForge</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="/assets/css/main.css" />
</head>
<body class="landing-body">
    <div class="landing-wrapper">
        <header class="landing-header">
            <div class="landing-logo">
                <div class="brand-logo">PF</div>
                <span class="brand-name">PixelForge</span>
            </div>
        </header>

        <main class="landing-main" style="display:flex;align-items:center;justify-content:center;min-height:60vh">
            <div class="auth-card" style="max-width:400px">
                <?php if ($status === 'success'): ?>
                    <div style="text-align:center">
                        <div style="font-size:48px;margin-bottom:16px">&#10003;</div>
                        <h2>Email Verified!</h2>
                        <p style="color:var(--text-secondary);margin:16px 0 24px">Your email has been confirmed. You can now play PIXEL DASH and paint the canvas.</p>
                        <a href="/game.php" class="btn btn-primary btn-full">Play Pixel Dash</a>
                    </div>
                <?php elseif ($status === 'invalid'): ?>
                    <div style="text-align:center">
                        <div style="font-size:48px;margin-bottom:16px">&#10007;</div>
                        <h2>Invalid Link</h2>
                        <p style="color:var(--text-secondary);margin:16px 0 24px">This verification link is invalid or has expired. Please request a new one.</p>
                        <a href="/" class="btn btn-secondary btn-full">Go to Login</a>
                    </div>
                <?php else: ?>
                    <div style="text-align:center">
                        <h2>Missing Token</h2>
                        <p style="color:var(--text-secondary);margin:16px 0 24px">No verification token provided.</p>
                        <a href="/" class="btn btn-secondary btn-full">Go to Login</a>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>