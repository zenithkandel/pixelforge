<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/headers.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request.';
    } else {
        if (!validate_username($username)) {
            $errors[] = 'Username must be 3-30 characters (letters, numbers, underscore only).';
        }
        if (!validate_email($email)) {
            $errors[] = 'Invalid email format.';
        }
        if (!validate_password($password)) {
            $errors[] = 'Password must be at least 8 characters with at least one number and one letter.';
        }
        if ($password !== $confirm) {
            $errors[] = 'Passwords do not match.';
        }

        if (empty($errors)) {
            $result = register_user($username, $email, $password);
            if (isset($result['error'])) {
                $errors[] = $result['error'];
            } else {
                $success = true;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
</head>
<body>
    <main class="auth-page">
        <div class="auth-card">
            <h1>Register</h1>
            <?php if ($success): ?>
                <div class="success-message">
                    Registration successful! <a href="<?php echo APP_URL; ?>/login.php">Login</a>
                </div>
            <?php else: ?>
            <form method="post" id="register-form">
                <?php echo csrf_field(); ?>
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required minlength="3" maxlength="30" pattern="[a-zA-Z0-9_]+">
                    <span class="hint">3-30 characters, letters, numbers, underscore</span>
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required minlength="8">
                    <span class="hint">At least 8 characters, one number, one letter</span>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                <?php if (!empty($errors)): ?>
                <div class="error-message">
                    <?php foreach ($errors as $e): ?>
                        <div><?php echo htmlspecialchars($e); ?></div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <button type="submit" class="btn primary">Register</button>
            </form>
            <p class="auth-link">Already have an account? <a href="<?php echo APP_URL; ?>/login.php">Login</a></p>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>