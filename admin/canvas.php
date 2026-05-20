<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/headers.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Canvas Management - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/canvas.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/admin.css">
</head>
<body>
    <?php include __DIR__ . '/../includes/navbar.php'; ?>

    <main class="admin-page">
        <h1>Canvas Management</h1>

        <div class="admin-nav">
            <a href="<?php echo APP_URL; ?>/admin/index.php" class="btn">← Back to Dashboard</a>
            <a href="<?php echo APP_URL; ?>/admin/users.php" class="btn">User Management</a>
            <a href="<?php echo APP_URL; ?>/admin/logs.php" class="btn">Admin Logs</a>
        </div>

        <div class="admin-controls">
            <button id="multi-select-btn" class="btn">Multi-Select Mode</button>
            <button id="erase-selected-btn" class="btn danger hidden">Erase Selected (0)</button>
            <button id="area-select-btn" class="btn">Area Select</button>
            <button id="reset-canvas-btn" class="btn danger">Reset Canvas</button>
            <button id="fill-canvas-btn" class="btn">Fill Unclaimed</button>
        </div>

        <div class="canvas-container admin">
            <canvas id="canvas" width="800" height="800"></canvas>
            <div class="canvas-controls">
                <span id="zoom-level">1×</span>
            </div>
        </div>

        <div id="confirm-modal" class="modal hidden">
            <div class="modal-content">
                <h3 id="modal-title">Confirm Action</h3>
                <p id="modal-message"></p>
                <div id="modal-input-container" class="hidden">
                    <input type="text" id="modal-input" placeholder="Type confirmation">
                </div>
                <div class="modal-actions">
                    <button id="modal-confirm" class="btn danger">Confirm</button>
                    <button id="modal-cancel" class="btn">Cancel</button>
                </div>
            </div>
        </div>
    </main>

    <input type="hidden" id="csrf-token" value="<?php echo csrf_token(); ?>">
    <script src="<?php echo APP_URL; ?>/assets/js/canvas.js"></script>
    <script src="<?php echo APP_URL; ?>/assets/js/admin-canvas.js"></script>
    <script>
        const APP_URL = '<?php echo APP_URL; ?>';
        const IS_ADMIN = true;
        initCanvas(true, true);
    </script>
</body>
</html>