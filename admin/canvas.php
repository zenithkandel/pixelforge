<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/logger.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/headers.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$db = get_db();
$messages = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'erase_pixel') {
        $x = (int)($_POST['x'] ?? -1);
        $y = (int)($_POST['y'] ?? -1);
        if ($x >= 0 && $x <= 99 && $y >= 0 && $y <= 99) {
            $db->prepare('DELETE FROM pixels WHERE x = ? AND y = ?')->execute([$x, $y]);
            $db->prepare('INSERT INTO admin_log (admin_id, action, target_type, details) VALUES (?, ?, ?, ?)')
               ->execute([(int)$_SESSION['user_id'], 'erase_pixel', 'pixel', "Erased pixel ($x, $y)"]);
            log_admin('ADMIN', 'Admin erased pixel', ['x' => $x, 'y' => $y]);
            $messages[] = "Pixel ($x, $y) erased.";
        }
    } elseif ($action === 'reset_canvas') {
        $confirm = trim($_POST['confirm_text'] ?? '');
        if ($confirm === 'RESET') {
            $db->exec('DELETE FROM pixels');
            $db->prepare('INSERT INTO admin_log (admin_id, action, target_type, details) VALUES (?, ?, ?, ?)')
               ->execute([(int)$_SESSION['user_id'], 'canvas_reset', 'canvas', 'Full canvas reset']);
            log_admin('ADMIN', 'Canvas fully reset');
            $messages[] = 'Canvas fully reset.';
        } else {
            $messages[] = 'Type RESET exactly to confirm.';
        }
    } elseif ($action === 'fill_unclaimed') {
        $color = trim($_POST['fill_color'] ?? '#333355');
        if (preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            for ($x = 0; $x < 100; $x++) {
                for ($y = 0; $y < 100; $y++) {
                    try {
                        $db->prepare('INSERT IGNORE INTO pixels (x, y, color, owner_id, placed_at, expires_at) VALUES (?, ?, ?, NULL, NOW(), DATE_ADD(NOW(), INTERVAL 14 DAY))')
                           ->execute([$x, $y, $color]);
                    } catch (PDOException $e) {}
                }
            }
            $db->prepare('INSERT INTO admin_log (admin_id, action, target_type, details) VALUES (?, ?, ?, ?)')
               ->execute([(int)$_SESSION['user_id'], 'fill_unclaimed', 'canvas', "Filled unclaimed with $color"]);
            log_admin('ADMIN', 'Admin filled unclaimed pixels', ['color' => $color]);
            $messages[] = "Unclaimed pixels filled with $color.";
        }
    }
}

try {
    $stmt = $db->query('SELECT COUNT(*) FROM pixels');
    $total_pixels = (int)$stmt->fetchColumn();
} catch (PDOException $e) {
    $total_pixels = 0;
}

$page_title = 'Canvas Management';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-content">
    <div class="page-header" style="display:flex;justify-content:space-between;align-items:center;">
        <div>
            <h1>Canvas Management</h1>
            <p><?= number_format($total_pixels) ?> / 10,000 pixels claimed</p>
        </div>
        <div style="display:flex;gap:8px;">
            <button class="btn-secondary btn-sm" id="btn-fill-unclaimed">Fill Unclaimed</button>
            <button class="btn-danger btn-sm" id="btn-reset-canvas">Reset Canvas</button>
        </div>
    </div>

    <?php foreach ($messages as $msg): ?>
        <div style="background:rgba(34,197,94,0.1);color:var(--green);padding:10px 16px;border-radius:8px;margin-bottom:12px;"><?= htmlspecialchars($msg) ?></div>
    <?php endforeach; ?>

    <div style="text-align:center;">
        <div id="admin-canvas-container" style="position:relative;display:inline-block;">
            <canvas id="admin-canvas" width="800" height="800"></canvas>
        </div>
        <p style="color:var(--text-muted);font-size:13px;margin-top:8px;">Click a pixel to erase it.</p>
    </div>
</div>

<div id="reset-modal" class="modal-overlay" style="display:none;">
    <div class="modal">
        <h3>Reset Canvas</h3>
        <p style="color:var(--red);">This deletes ALL pixels. Type RESET to confirm.</p>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="reset_canvas">
            <div class="form-group">
                <input type="text" name="confirm_text" placeholder="Type RESET" autocomplete="off">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary modal-cancel-btn" data-modal="reset-modal">Cancel</button>
                <button type="submit" class="btn-danger">Reset Canvas</button>
            </div>
        </form>
    </div>
</div>

<div id="fill-modal" class="modal-overlay" style="display:none;">
    <div class="modal">
        <h3>Fill Unclaimed</h3>
        <p style="color:var(--text-secondary);">Fill all unclaimed pixels with a color.</p>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="fill_unclaimed">
            <div class="form-group">
                <label>Color</label>
                <input type="color" name="fill_color" value="#333355" style="width:100px;">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary modal-cancel-btn" data-modal="fill-modal">Cancel</button>
                <button type="submit" class="btn-primary">Fill Unclaimed</button>
            </div>
        </form>
    </div>
</div>

<script nonce="<?= $GLOBALS['csp_nonce'] ?>">
(function() {
    var canvas = document.getElementById('admin-canvas');
    if (!canvas) return;
    var ctx = canvas.getContext('2d');
    if (!ctx) return;

    var pixels = [];
    var cellSize = 8;

    function render() {
        ctx.fillStyle = '#1a1a1a';
        ctx.fillRect(0, 0, 800, 800);
        var pixelMap = {};
        pixels.forEach(function(p) { pixelMap[p.x + ',' + p.y] = p; });

        for (var row = 0; row < 100; row++) {
            for (var col = 0; col < 100; col++) {
                var p = pixelMap[col + ',' + row];
                ctx.fillStyle = p ? p.color : '#1a1a1a';
                ctx.fillRect(col * cellSize, row * cellSize, cellSize + 0.5, cellSize + 0.5);
            }
        }
    }

    canvas.addEventListener('click', function(e) {
        var rect = canvas.getBoundingClientRect();
        var mx = (e.clientX - rect.left) * (800 / rect.width);
        var my = (e.clientY - rect.top) * (800 / rect.height);
        var col = Math.floor(mx / cellSize);
        var row = Math.floor(my / cellSize);
        if (col < 0 || col >= 100 || row < 0 || row >= 100) return;

        var form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        form.innerHTML = '<input name="csrf_token" value="<?= csrf_token() ?>"><input name="action" value="erase_pixel"><input name="x" value="' + col + '"><input name="y" value="' + row + '">';
        document.body.appendChild(form);
        form.submit();
    });

    document.getElementById('btn-fill-unclaimed').addEventListener('click', function() {
        document.getElementById('fill-modal').style.display = 'flex';
    });

    document.getElementById('btn-reset-canvas').addEventListener('click', function() {
        document.getElementById('reset-modal').style.display = 'flex';
    });

    document.querySelectorAll('.modal-cancel-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById(this.dataset.modal).style.display = 'none';
        });
    });

    fetch('api/get_canvas.php')
        .then(function(res) { return res.json(); })
        .then(function(data) { pixels = data.pixels; render(); })
        .catch(function() {});
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
