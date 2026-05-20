<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/headers.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/xp.php';

require_login();
$user = get_logged_in_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Canvas - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/canvas.css">
</head>
<body>
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <main>
        <div class="canvas-page">
            <div class="canvas-container">
                <canvas id="canvas" width="800" height="800"></canvas>
                <div class="canvas-controls">
                    <button id="territory-toggle">Territory View</button>
                    <button id="my-pixels-btn">My Pixels</button>
                    <span id="zoom-level">1×</span>
                </div>
                <div class="live-indicator">
                    <span class="live-dot"></span> Live
                </div>
                <div id="minimap-container">
                    <canvas id="minimap" width="100" height="100"></canvas>
                    <div id="minimap-viewport"></div>
                </div>
            </div>

            <div id="pixel-panel" class="pixel-panel hidden">
                <div class="panel-header">
                    <h3>Pixel Details</h3>
                    <button id="close-panel" class="close-btn">×</button>
                </div>
                <div class="panel-content">
                    <div class="coord-display">
                        <span class="label">Coordinates:</span>
                        <span id="pixel-coord">(0, 0)</span>
                    </div>
                    <div class="owner-display">
                        <span class="label">Owner:</span>
                        <span id="pixel-owner">Unclaimed</span>
                    </div>
                    <div id="decay-warning" class="decay-warning hidden">
                        <span class="warning-icon">⚠️</span>
                        <span id="decay-text">Expires in X days</span>
                    </div>
                    <div class="color-picker-group">
                        <label for="pixel-color">Color</label>
                        <input type="color" id="pixel-color" value="#7c3aed">
                        <input type="text" id="pixel-color-hex" value="#7c3aed" maxlength="7" pattern="^#[0-9A-Fa-f]{6}$">
                    </div>
                    <div class="cost-display">
                        <span class="label">Cost:</span>
                        <span id="pixel-cost">5 currency</span>
                    </div>
                    <div class="balance-display">
                        <span class="label">Your Balance:</span>
                        <span id="user-balance">💰<?php echo $user['balance']; ?></span>
                    </div>
                    <div id="pixel-error" class="error-message hidden"></div>
                    <button id="place-pixel-btn" class="btn primary">Place Pixel</button>
                </div>
            </div>
        </div>
    </main>

    <input type="hidden" id="csrf-token" value="<?php echo csrf_token(); ?>">
    <input type="hidden" id="user-balance" value="<?php echo $user['balance']; ?>">
    <input type="hidden" id="user-id" value="<?php echo $user['id']; ?>">
    <input type="hidden" id="user-level" value="<?php echo $user['level']; ?>">

    <script src="<?php echo APP_URL; ?>/assets/js/canvas.js"></script>
    <script src="<?php echo APP_URL; ?>/assets/js/territory.js"></script>
    <script src="<?php echo APP_URL; ?>/assets/js/achievements.js"></script>
    <script>
        const APP_URL = '<?php echo APP_URL; ?>';
        const USER_LOGGED_IN = true;
        const USER_AVATAR_COLOR = '<?php echo $user['avatar_color']; ?>';
        initCanvas(true);
    </script>
</body>
</html>