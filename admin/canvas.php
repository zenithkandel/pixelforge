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

    $stmt = $db->query('SELECT COUNT(DISTINCT owner_id) FROM pixels WHERE owner_id IS NOT NULL');
    $unique_owners = (int)$stmt->fetchColumn();
} catch (PDOException $e) {
    $total_pixels = 0;
    $unique_owners = 0;
}

$page_title = 'Canvas';
require_once __DIR__ . '/header.php';
?>

<div class="admin-section">
    <div class="admin-section-header">
        <div>
            <h2 class="admin-section-title">Canvas Management</h2>
            <p style="color:var(--text-muted);margin:var(--space-xs) 0 0;font-size:14px;">
                <?= number_format($total_pixels) ?> / 10,000 pixels (<?= number_format($unique_owners) ?> owners)
            </p>
        </div>
        <div style="display:flex;gap:var(--space-sm);">
            <button class="btn-secondary" id="btn-fill-unclaimed">
                <span style="margin-right:6px;">🎨</span> Fill Unclaimed
            </button>
            <button class="btn-danger" id="btn-reset-canvas">
                <span style="margin-right:6px;">🗑️</span> Reset Canvas
            </button>
        </div>
    </div>
</div>

<?php foreach ($messages as $msg): ?>
<div style="background:rgba(34,197,94,0.1);border:1px solid rgba(34,197,94,0.3);color:var(--green);padding:12px 16px;border-radius:var(--radius-md);margin-bottom:var(--space-md);">
    <?= htmlspecialchars($msg) ?>
</div>
<?php endforeach; ?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Pixel Canvas</h3>
        <span style="font-size:13px;color:var(--text-muted);">Click any pixel to erase it</span>
    </div>

    <div style="display:flex;justify-content:center;padding:var(--space-md) 0;">
        <div style="position:relative;border-radius:var(--radius-lg);overflow:hidden;box-shadow:var(--shadow-lg);">
            <canvas id="admin-canvas" width="600" height="600" style="display:block;cursor:crosshair;"></canvas>
            <div style="position:absolute;bottom:12px;right:12px;background:rgba(0,0,0,0.7);padding:6px 12px;border-radius:var(--radius-pill);font-size:12px;color:#fff;">
                100 × 100 grid
            </div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:var(--space-md);margin-top:var(--space-lg);padding-top:var(--space-lg);border-top:1px solid var(--border-subtle);">
        <div style="text-align:center;padding:var(--space-md);background:var(--bg-elevated);border-radius:var(--radius-md);">
            <div style="font-size:24px;font-weight:700;color:var(--text-primary);"><?= number_format($total_pixels) ?></div>
            <div style="font-size:13px;color:var(--text-muted);">Placed Pixels</div>
        </div>
        <div style="text-align:center;padding:var(--space-md);background:var(--bg-elevated);border-radius:var(--radius-md);">
            <div style="font-size:24px;font-weight:700;color:var(--text-primary);"><?= round($total_pixels / 100, 1) ?>%</div>
            <div style="font-size:13px;color:var(--text-muted);">Canvas Coverage</div>
        </div>
        <div style="text-align:center;padding:var(--space-md);background:var(--bg-elevated);border-radius:var(--radius-md);">
            <div style="font-size:24px;font-weight:700;color:var(--text-primary);"><?= number_format(10000 - $total_pixels) ?></div>
            <div style="font-size:13px;color:var(--text-muted);">Empty Cells</div>
        </div>
    </div>
</div>

<div id="reset-modal" class="modal-overlay" style="display:none;">
    <div class="modal">
        <div style="display:flex;align-items:center;gap:var(--space-sm);margin-bottom:var(--space-md);">
            <span style="font-size:24px;">⚠️</span>
            <h3 style="margin:0;color:var(--red);">Reset Canvas</h3>
        </div>
        <p style="color:var(--text-secondary);margin-bottom:var(--space-lg);">
            This will <strong style="color:var(--red);">permanently delete ALL pixels</strong> from the canvas. This action cannot be undone.
        </p>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="reset_canvas">
            <div class="form-group">
                <label style="font-weight:600;">Type <code style="background:var(--bg-elevated);padding:2px 6px;border-radius:4px;">RESET</code> to confirm</label>
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
        <h3 style="margin-bottom:var(--space-sm);">Fill Unclaimed Pixels</h3>
        <p style="color:var(--text-secondary);margin-bottom:var(--space-lg);">
            Fill all empty cells with a single color. This affects only unclaimed (ownerless) pixels.
        </p>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="fill_unclaimed">
            <div class="form-group">
                <label style="font-weight:600;">Fill Color</label>
                <div style="display:flex;gap:var(--space-md);align-items:center;">
                    <input type="color" name="fill_color" value="#333355" style="width:60px;height:40px;padding:2px;border-radius:var(--radius-sm);cursor:pointer;">
                    <input type="text" name="fill_color_text" value="#333355" style="width:120px;font-family:var(--font-mono);" data-color-input="fill_color">
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary modal-cancel-btn" data-modal="fill-modal">Cancel</button>
                <button type="submit" class="btn-primary">Fill Canvas</button>
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
    var cellSize = 6;

    function render() {
        ctx.fillStyle = '#1a1a1a';
        ctx.fillRect(0, 0, 600, 600);

        var pixelMap = {};
        pixels.forEach(function(p) { pixelMap[p.x + ',' + p.y] = p; });

        for (var row = 0; row < 100; row++) {
            for (var col = 0; col < 100; col++) {
                var p = pixelMap[col + ',' + row];
                if (p) {
                    ctx.fillStyle = p.color;
                    ctx.fillRect(col * cellSize, row * cellSize, cellSize, cellSize);
                }
            }
        }

        ctx.strokeStyle = 'rgba(255,255,255,0.03)';
        ctx.lineWidth = 0.5;
        for (var i = 0; i <= 100; i += 10) {
            ctx.beginPath();
            ctx.moveTo(i * cellSize, 0);
            ctx.lineTo(i * cellSize, 600);
            ctx.stroke();
            ctx.beginPath();
            ctx.moveTo(0, i * cellSize);
            ctx.lineTo(600, i * cellSize);
            ctx.stroke();
        }
    }

    canvas.addEventListener('click', function(e) {
        var rect = canvas.getBoundingClientRect();
        var scale = 600 / rect.width;
        var mx = (e.clientX - rect.left) * scale;
        var my = (e.clientY - rect.top) * scale;
        var col = Math.floor(mx / cellSize);
        var row = Math.floor(my / cellSize);
        if (col < 0 || col >= 100 || row < 0 || row >= 100) return;

        if (confirm('Erase pixel at (' + col + ', ' + row + ')?')) {
            var form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            form.innerHTML = '<input name="csrf_token" value="<?= csrf_token() ?>"><input name="action" value="erase_pixel"><input name="x" value="' + col + '"><input name="y" value="' + row + '">';
            document.body.appendChild(form);
            form.submit();
        }
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

    var colorInput = document.querySelector('input[name="fill_color"]');
    var colorTextInput = document.querySelector('input[name="fill_color_text"]');
    if (colorInput && colorTextInput) {
        colorInput.addEventListener('input', function() {
            colorTextInput.value = this.value;
        });
        colorTextInput.addEventListener('input', function() {
            if (/^#[0-9A-Fa-f]{6}$/.test(this.value)) {
                colorInput.value = this.value;
            }
        });
    }

    fetch('api/get_canvas.php')
        .then(function(res) { return res.json(); })
        .then(function(data) { pixels = data.pixels; render(); })
        .catch(function() {});
})();
</script>

<?php require_once __DIR__ . '/footer.php'; ?>