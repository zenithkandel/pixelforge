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
        $x = (int) ($_POST['x'] ?? -1);
        $y = (int) ($_POST['y'] ?? -1);
        if ($x >= 0 && $x <= 99 && $y >= 0 && $y <= 99) {
            $db->prepare('DELETE FROM pixels WHERE x = ? AND y = ?')->execute([$x, $y]);
            $db->prepare('INSERT INTO admin_log (admin_id, action, target_type, details) VALUES (?, ?, ?, ?)')
                ->execute([(int) $_SESSION['user_id'], 'erase_pixel', 'pixel', "Erased pixel ($x, $y)"]);
            log_admin('ADMIN', 'Admin erased pixel', ['x' => $x, 'y' => $y]);
            $messages[] = "Pixel ($x, $y) erased.";
        }
    } elseif ($action === 'reset_canvas') {
        $confirm = trim($_POST['confirm_text'] ?? '');
        if ($confirm === 'RESET') {
            $db->exec('DELETE FROM pixels');
            $db->prepare('INSERT INTO admin_log (admin_id, action, target_type, details) VALUES (?, ?, ?, ?)')
                ->execute([(int) $_SESSION['user_id'], 'canvas_reset', 'canvas', 'Full canvas reset']);
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
                    } catch (PDOException $e) {
                    }
                }
            }
            $db->prepare('INSERT INTO admin_log (admin_id, action, target_type, details) VALUES (?, ?, ?, ?)')
                ->execute([(int) $_SESSION['user_id'], 'fill_unclaimed', 'canvas', "Filled unclaimed with $color"]);
            log_admin('ADMIN', 'Admin filled unclaimed pixels', ['color' => $color]);
            $messages[] = "Unclaimed pixels filled with $color.";
        }
    }
}

try {
    $stmt = $db->query('SELECT COUNT(*) FROM pixels');
    $total_pixels = (int) $stmt->fetchColumn();

    $stmt = $db->query('SELECT COUNT(DISTINCT owner_id) FROM pixels WHERE owner_id IS NOT NULL');
    $unique_owners = (int) $stmt->fetchColumn();
} catch (PDOException $e) {
    $total_pixels = 0;
    $unique_owners = 0;
}

$page_title = 'Canvas Registry';
require_once __DIR__ . '/header.php';
?>

<div class="stat-grid" style="margin-bottom: var(--space-lg);">
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-grid-4"></i></div>
        <span class="stat-value"><?= number_format($total_pixels) ?></span>
        <span class="stat-label">Registry Load</span>
        <div class="stat-trend"><i class="fas fa-microchip"></i> 10,000 capacity</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-users"></i></div>
        <span class="stat-value"><?= number_format($unique_owners) ?></span>
        <span class="stat-label">Active Architects</span>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-chart-pie"></i></div>
        <span class="stat-value"><?= round(($total_pixels / 10000) * 100, 1) ?>%</span>
        <span class="stat-label">Canvas Occupation</span>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-eraser"></i></div>
        <span class="stat-value"><?= number_format(10000 - $total_pixels) ?></span>
        <span class="stat-label">Void Fragments</span>
    </div>
</div>

<?php foreach ($messages as $msg): ?>
    <div class="glass-panel"
        style="padding: 15px 25px; border-left: 4px solid var(--green); margin-bottom: 30px; display: flex; align-items: center; gap: 15px;">
        <i class="fas fa-check-circle" style="color: var(--green); font-size: 20px;"></i>
        <span style="font-weight: 600; color: var(--text-primary);"><?= htmlspecialchars($msg) ?></span>
    </div>
<?php endforeach; ?>

<div class="section-card">
    <div class="section-header">
        <div>
            <h2 class="section-title">Live Canvas Matrix</h2>
            <p style="color:var(--text-muted); font-size:13px; margin-top:5px;">Real-time oversight of the pixel
                territory.</p>
        </div>
        <div style="display:flex; gap:12px;">
            <button class="btn-pixel" id="btn-fill-unclaimed" style="padding: 10px 20px; font-size: 13px;">
                <i class="fas fa-paint-brush"></i> Matrix Fill
            </button>
            <button class="btn-pixel" id="btn-reset-canvas"
                style="padding: 10px 20px; font-size: 13px; background: var(--red);">
                <i class="fas fa-trash-alt"></i> Purge Matrix
            </button>
        </div>
    </div>

    <div class="glass-panel"
        style="display:flex; justify-content:center; padding: 40px; margin: 20px 0; background: rgba(0,0,0,0.2);">
        <div
            style="position:relative; border: 4px solid var(--border-bright); border-radius: 4px; box-shadow: 0 0 40px rgba(0,0,0,0.5);">
            <canvas id="admin-canvas" width="600" height="600"
                style="display:block; cursor:crosshair; image-rendering: pixelated;"></canvas>
            <div
                style="position:absolute; bottom:-30px; left:0; right:0; text-align:center; font-family:var(--font-game); font-size:11px; letter-spacing:2px; color:var(--text-muted); font-weight:800; text-transform:uppercase;">
                — Sector 001 Registry Visual —
            </div>
        </div>
    </div>

    <div style="padding: 20px; text-align: center; color: var(--text-muted); font-size: 14px;">
        <i class="fas fa-mouse-pointer" style="margin-right: 8px;"></i> Click any data point (pixel) to intercept and
        erase its registry entry.
    </div>
</div>

<!-- Modals with Pro Aesthetic -->
<div id="reset-modal" class="modal-overlay" style="display:none; backdrop-filter: blur(10px);">
    <div class="glass-panel" style="width: 450px; padding: 40px; position:relative;">
        <div style="text-align:center; margin-bottom: 25px;">
            <div
                style="width:60px; height:60px; background:rgba(239, 68, 68, 0.1); border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 15px;">
                <i class="fas fa-exclamation-triangle" style="color:var(--red); font-size:24px;"></i>
            </div>
            <h3 style="font-family:var(--font-game); font-size:24px; color:white;">Total Matrix Purge</h3>
        </div>

        <p
            style="color:var(--text-secondary); text-align:center; margin-bottom: 30px; font-size:14px; line-height:1.6;">
            This operation will <strong style="color:var(--red);">permanently incinerate all 10,000 data nodes</strong>.
            This recovery process is impossible.
        </p>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="reset_canvas">
            <div style="margin-bottom: 25px;">
                <label
                    style="display:block; font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:1px; color:var(--text-muted); margin-bottom:10px;">Confirm
                    Purge (Type "RESET")</label>
                <input type="text" name="confirm_text" placeholder="RESET" autocomplete="off"
                    style="width:100%; padding:12px; background:var(--bg-input); border:1px solid var(--border-default); border-radius:8px; color:white; font-family:monospace; text-align:center; letter-spacing:4px;">
            </div>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <button type="button" class="btn-pixel modal-cancel-btn" data-modal="reset-modal"
                    style="background:var(--bg-active);">Abort</button>
                <button type="submit" class="btn-pixel" style="background:var(--red);">Execute Purge</button>
            </div>
        </form>
    </div>
</div>

<div id="fill-modal" class="modal-overlay" style="display:none; backdrop-filter: blur(10px);">
    <div class="glass-panel" style="width: 450px; padding: 40px; position:relative;">
        <div style="text-align:center; margin-bottom: 25px;">
            <div
                style="width:60px; height:60px; background:rgba(124, 58, 237, 0.1); border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 15px;">
                <i class="fas fa-paint-brush" style="color:var(--purple-bright); font-size:24px;"></i>
            </div>
            <h2 style="font-family:var(--font-game); font-size:24px; color:white;">Matrix Infill</h2>
        </div>

        <p
            style="color:var(--text-secondary); text-align:center; margin-bottom: 30px; font-size:14px; line-height:1.6;">
            Inject a specific color sequence into all unassigned void fragments within the matrix.
        </p>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="fill_unclaimed">
            <div style="margin-bottom: 25px;">
                <label
                    style="display:block; font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:1px; color:var(--text-muted); margin-bottom:10px;">Void
                    Color Signature</label>
                <div style="display:flex; gap:10px;">
                    <div
                        style="position:relative; width:50px; height:50px; border-radius:8px; overflow:hidden; border:1px solid var(--border-default);">
                        <input type="color" name="fill_color" value="#333355"
                            style="position:absolute; top:-10px; left:-10px; width:100px; height:100px; cursor:pointer; border:none;">
                    </div>
                    <input type="text" name="fill_color_text" value="#333355" data-color-input="fill_color"
                        style="flex:1; padding:12px; background:var(--bg-input); border:1px solid var(--border-default); border-radius:8px; color:white; font-family:monospace;">
                </div>
            </div>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <button type="button" class="btn-pixel modal-cancel-btn" data-modal="fill-modal"
                    style="background:var(--bg-active);">Cancel</button>
                <button type="submit" class="btn-pixel">Inject Sequence</button>
            </div>
        </form>
    </div>
</div>

<script nonce="<?= $GLOBALS['csp_nonce'] ?>">
    (function () {
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
            pixels.forEach(function (p) { pixelMap[p.x + ',' + p.y] = p; });

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

        canvas.addEventListener('click', function (e) {
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

        document.getElementById('btn-fill-unclaimed').addEventListener('click', function () {
            document.getElementById('fill-modal').style.display = 'flex';
        });

        document.getElementById('btn-reset-canvas').addEventListener('click', function () {
            document.getElementById('reset-modal').style.display = 'flex';
        });

        document.querySelectorAll('.modal-cancel-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.getElementById(this.dataset.modal).style.display = 'none';
            });
        });

        var colorInput = document.querySelector('input[name="fill_color"]');
        var colorTextInput = document.querySelector('input[name="fill_color_text"]');
        if (colorInput && colorTextInput) {
            colorInput.addEventListener('input', function () {
                colorTextInput.value = this.value;
            });
            colorTextInput.addEventListener('input', function () {
                if (/^#[0-9A-Fa-f]{6}$/.test(this.value)) {
                    colorInput.value = this.value;
                }
            });
        }

        fetch('api/get_canvas.php')
            .then(function (res) { return res.json(); })
            .then(function (data) { pixels = data.pixels; render(); })
            .catch(function () { });
    })();
</script>

<?php require_once __DIR__ . '/footer.php'; ?>