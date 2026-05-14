<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$token = $_GET['token'] ?? '';

if (empty($token)) {
    $error = 'Invalid verification token';
} else {
    try {
        $pdo = get_db();

        $stmt = $pdo->prepare("SELECT id, username, email_verify_expires FROM users WHERE email_verify_token = ? AND email_verified = 0");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if (!$user) {
            $error = 'Invalid or expired verification token';
        } elseif (strtotime($user['email_verify_expires']) < time()) {
            $error = 'Verification token has expired';
        } else {
            $stmt = $pdo->prepare("UPDATE users SET email_verified = 1, email_verify_token = NULL, email_verify_expires = NULL WHERE id = ?");
            $stmt->execute([$user['id']]);

            $success = true;
            $username = $user['username'];
        }
    } catch (Exception $e) {
        $error = 'An error occurred during verification';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - PixelForge</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <style>
        .verify-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 24px;
            text-align: center;
        }
        .verify-icon {
            font-size: 64px;
            margin-bottom: 24px;
        }
        .verify-icon.success { color: var(--color-success); }
        .verify-icon.error { color: var(--color-error); }
        .verify-title {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 12px;
        }
        .verify-message {
            color: var(--text-secondary);
            margin-bottom: 24px;
            max-width: 400px;
        }
        .verify-btn {
            padding: 12px 24px;
            font-size: 16px;
        }
    </style>
</head>
<body>
    <div class="verify-container">
        <?php if (isset($success)): ?>
            <div class="verify-icon success">&#10003;</div>
            <h1 class="verify-title">Email Verified!</h1>
            <p class="verify-message">Welcome to PixelForge, <?php echo h($username); ?>! Your email has been successfully verified. You can now paint pixels on The Forge and play PIXEL DASH.</p>
            <a href="/canvas.php" class="btn btn-primary verify-btn">Go to The Forge</a>
        <?php else: ?>
            <div class="verify-icon error">&#10007;</div>
            <h1 class="verify-title">Verification Failed</h1>
            <p class="verify-message"><?php echo h($error ?? 'An error occurred'); ?>. Please try again or contact support if the problem persists.</p>
            <a href="/index.php" class="btn btn-primary verify-btn">Back to Home</a>
        <?php endif; ?>
    </div>
</body>
</html>