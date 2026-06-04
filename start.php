<?php
require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/includes/db.php';

$pdo = null;
$dbOk = false;
$dbError = '';

try {
    $pdo = db();
    $dbOk = true;
} catch (Exception $e) {
    $dbError = $e->getMessage();
}

$tables = [];
$existingTables = [];
if ($dbOk) {
    $stmt = $pdo->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $existingTables[] = $row[0];
    }
}

$requiredTables = ['users', 'pixels', 'game_sessions', 'score_log', 'achievements', 'user_achievements', 'login_attempts', 'transactions'];

$achievementCount = 0;
$userCount = 0;
$pixelCount = 0;

if ($dbOk && in_array('users', $existingTables)) {
    $userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
}
if ($dbOk && in_array('pixels', $existingTables)) {
    $pixelCount = $pdo->query("SELECT COUNT(*) FROM pixels")->fetchColumn();
}
if ($dbOk && in_array('achievements', $existingTables)) {
    $achievementCount = $pdo->query("SELECT COUNT(*) FROM achievements")->fetchColumn();
}

$wsRunning = false;
$wsPid = '';
$fp = @fsockopen('127.0.0.1', 8080, $errno, $errstr, 2);
if ($fp) {
    fclose($fp);
    $wsRunning = true;
}

$phpVersion = PHP_VERSION;
$extensions = [
    'pdo' => extension_loaded('pdo'),
    'pdo_mysql' => extension_loaded('pdo_mysql'),
    'json' => extension_loaded('json'),
    'mbstring' => extension_loaded('mbstring'),
    'session' => extension_loaded('session'),
];

$files = [
    'api/config.php', 'api/auth.php', 'api/game.php', 'api/pixels.php',
    'api/canvas.php', 'api/websocket/server.php', 'api/websocket/ChatServer.php',
    'includes/db.php', 'includes/auth.php', 'includes/csrf.php',
    'assets/css/main.css', 'assets/css/game.css', 'assets/css/canvas.css',
    'assets/css/auth.css',
    'assets/js/utils.js', 'assets/js/game.js', 'assets/js/game-renderer.js',
    'assets/js/game-animations.js', 'assets/js/game-powerups.js',
    'index.html', 'game.html', 'canvas.html', 'leaderboard.html', 'profile.html',
    '.htaccess', 'vendor/autoload.php',
];

$ratchetAvailable = class_exists('Ratchet\Server\IoServer');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PixelForge Dashboard</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: #0a0a0f;
            color: #e2e8f0;
            min-height: 100vh;
        }

        .hero {
            text-align: center;
            padding: 40px 20px 20px;
            background: linear-gradient(180deg, rgba(124,58,237,0.15) 0%, transparent 100%);
            border-bottom: 1px solid rgba(124,58,237,0.15);
        }

        .hero h1 {
            font-size: 2.2rem;
            font-weight: 800;
            letter-spacing: -1px;
            margin-bottom: 4px;
        }

        .hero h1 .px { color: #7c3aed; }
        .hero h1 .fg { color: #f59e0b; }

        .hero p {
            color: #64748b;
            font-size: 0.95rem;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        .grid {
            max-width: 1100px;
            margin: 30px auto;
            padding: 0 20px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .card {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 16px;
            padding: 24px;
            transition: border-color 0.2s;
        }

        .card:hover {
            border-color: rgba(124,58,237,0.3);
        }

        .card h2 {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: #64748b;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .card h2 .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }

        .card h2 .dot.green { background: #22c55e; box-shadow: 0 0 6px #22c55e88; }
        .card h2 .dot.red { background: #ef4444; box-shadow: 0 0 6px #ef444488; }
        .card h2 .dot.yellow { background: #eab308; box-shadow: 0 0 6px #eab30888; }

        .check-list {
            list-style: none;
        }

        .check-list li {
            padding: 6px 0;
            font-size: 0.88rem;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid rgba(255,255,255,0.04);
        }

        .check-list li:last-child { border-bottom: none; }

        .check-list .icon {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: 700;
            flex-shrink: 0;
        }

        .check-list .icon.ok { background: rgba(34,197,94,0.15); color: #22c55e; }
        .check-list .icon.fail { background: rgba(239,68,68,0.15); color: #ef4444; }
        .check-list .icon.info { background: rgba(100,116,139,0.15); color: #94a3b8; }

        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 12px;
        }

        .stat-box {
            background: rgba(124,58,237,0.08);
            border: 1px solid rgba(124,58,237,0.15);
            border-radius: 12px;
            padding: 16px;
            text-align: center;
        }

        .stat-box .num {
            font-size: 1.8rem;
            font-weight: 800;
            color: #7c3aed;
        }

        .stat-box .label {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #64748b;
            margin-top: 4px;
        }

        .pages {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .page-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 14px;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 10px;
            transition: all 0.2s;
        }

        .page-row:hover {
            background: rgba(124,58,237,0.08);
            border-color: rgba(124,58,237,0.3);
        }

        .page-row .name {
            font-weight: 600;
            font-size: 0.9rem;
        }

        .page-row .desc {
            font-size: 0.75rem;
            color: #64748b;
        }

        .page-row a {
            background: #7c3aed;
            color: #fff;
            text-decoration: none;
            padding: 6px 16px;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 600;
            transition: background 0.2s;
        }

        .page-row a:hover {
            background: #6d28d9;
        }

        .ws-status {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
            padding: 14px 16px;
            border-radius: 12px;
            font-weight: 600;
        }

        .ws-status.running {
            background: rgba(34,197,94,0.1);
            border: 1px solid rgba(34,197,94,0.3);
            color: #22c55e;
        }

        .ws-status.stopped {
            background: rgba(239,68,68,0.1);
            border: 1px solid rgba(239,68,68,0.3);
            color: #ef4444;
        }

        .ws-status .pulse {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            animation: pulse 2s ease-in-out infinite;
        }

        .ws-status.running .pulse { background: #22c55e; }
        .ws-status.stopped .pulse { background: #ef4444; animation: none; }

        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(1.3); }
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-primary {
            background: #7c3aed;
            color: #fff;
        }

        .btn-primary:hover {
            background: #6d28d9;
            transform: translateY(-1px);
        }

        .btn-danger {
            background: rgba(239,68,68,0.15);
            color: #ef4444;
            border: 1px solid rgba(239,68,68,0.3);
        }

        .btn-danger:hover {
            background: rgba(239,68,68,0.25);
        }

        .btn-success {
            background: rgba(34,197,94,0.15);
            color: #22c55e;
            border: 1px solid rgba(34,197,94,0.3);
        }

        .btn-success:hover {
            background: rgba(34,197,94,0.25);
        }

        .btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
            transform: none !important;
        }

        .ws-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .log-box {
            margin-top: 16px;
            background: #000;
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 10px;
            padding: 14px;
            font-family: 'Cascadia Code', 'Fira Code', monospace;
            font-size: 0.78rem;
            color: #22c55e;
            max-height: 200px;
            overflow-y: auto;
            line-height: 1.6;
            white-space: pre-wrap;
        }

        .schema-sql {
            grid-column: 1 / -1;
        }

        .schema-box {
            background: #000;
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 10px;
            padding: 14px;
            max-height: 250px;
            overflow-y: auto;
        }

        .schema-box pre {
            font-family: 'Cascadia Code', 'Fira Code', monospace;
            font-size: 0.75rem;
            color: #94a3b8;
            line-height: 1.5;
        }

        .sql-btn {
            margin-top: 12px;
        }

        .php-cmd {
            font-family: 'Cascadia Code', 'Fira Code', monospace;
            font-size: 0.78rem;
            background: rgba(0,0,0,0.4);
            padding: 6px 12px;
            border-radius: 8px;
            margin-top: 10px;
            color: #7c3aed;
            display: block;
            user-select: all;
        }

        .footer {
            text-align: center;
            padding: 30px;
            color: #334155;
            font-size: 0.8rem;
            border-top: 1px solid rgba(255,255,255,0.04);
            margin-top: 30px;
        }

        @media (max-width: 768px) {
            .grid { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: 1fr; }
            .hero h1 { font-size: 1.6rem; }
        }
    </style>
</head>
<body>

<div class="hero">
    <h1><span class="px">Pixel</span><span class="fg">Forge</span></h1>
    <p>Dashboard & System Verification</p>
</div>

<div class="grid">

    <!-- System Requirements -->
    <div class="card">
        <h2><span class="dot green"></span> System Requirements</h2>
        <ul class="check-list">
            <li>
                <span class="icon <?php echo $phpVersion >= '8.0' ? 'ok' : 'fail'; ?>">
                    <?php echo $phpVersion >= '8.0' ? '&#10003;' : '&#10007;'; ?>
                </span>
                PHP <?php echo $phpVersion; ?>
            </li>
            <?php foreach ($extensions as $ext => $loaded): ?>
            <li>
                <span class="icon <?php echo $loaded ? 'ok' : 'fail'; ?>">
                    <?php echo $loaded ? '&#10003;' : '&#10007;'; ?>
                </span>
                ext-<?php echo $ext; ?>
            </li>
            <?php endforeach; ?>
            <li>
                <span class="icon <?php echo $ratchetAvailable ? 'ok' : 'fail'; ?>">
                    <?php echo $ratchetAvailable ? '&#10003;' : '&#10007;'; ?>
                </span>
                Ratchet WebSocket
            </li>
        </ul>
    </div>

    <!-- Database -->
    <div class="card">
        <h2>
            <span class="dot <?php echo $dbOk ? 'green' : 'red'; ?>"></span>
            Database
        </h2>
        <?php if ($dbOk): ?>
        <ul class="check-list">
            <?php foreach ($requiredTables as $table): ?>
            <li>
                <span class="icon <?php echo in_array($table, $existingTables) ? 'ok' : 'fail'; ?>">
                    <?php echo in_array($table, $existingTables) ? '&#10003;' : '&#10007;'; ?>
                </span>
                <?php echo $table; ?>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php else: ?>
        <ul class="check-list">
            <li>
                <span class="icon fail">&#10007;</span>
                <?php echo $dbError ?: 'Connection failed'; ?>
            </li>
        </ul>
        <?php endif; ?>
    </div>

    <!-- Stats -->
    <div class="card">
        <h2><span class="dot green"></span> Live Stats</h2>
        <div class="stats-grid">
            <div class="stat-box">
                <div class="num"><?php echo $userCount; ?></div>
                <div class="label">Users</div>
            </div>
            <div class="stat-box">
                <div class="num"><?php echo $pixelCount; ?></div>
                <div class="label">Pixels</div>
            </div>
            <div class="stat-box">
                <div class="num"><?php echo $achievementCount; ?></div>
                <div class="label">Achievements</div>
            </div>
        </div>
    </div>

    <!-- Files -->
    <div class="card">
        <h2><span class="dot green"></span> File Integrity</h2>
        <ul class="check-list">
            <?php foreach ($files as $file): ?>
            <li>
                <span class="icon <?php echo file_exists(__DIR__.'/'.$file) ? 'ok' : 'fail'; ?>">
                    <?php echo file_exists(__DIR__.'/'.$file) ? '&#10003;' : '&#10007;'; ?>
                </span>
                <?php echo $file; ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <!-- WebSocket Server -->
    <div class="card">
        <h2><span class="dot <?php echo $wsRunning ? 'green' : 'red'; ?>"></span> WebSocket Server</h2>
        <div class="ws-status <?php echo $wsRunning ? 'running' : 'stopped'; ?>">
            <div class="pulse"></div>
            <?php echo $wsRunning ? 'Running on port 8080' : 'Not running'; ?>
        </div>
        <div class="ws-actions">
            <button class="btn btn-primary" onclick="startWS()" id="btnStart" <?php echo $wsRunning ? 'disabled' : ''; ?>>
                &#9654; Start Server
            </button>
            <button class="btn btn-danger" onclick="stopWS()" id="btnStop" <?php echo !$wsRunning ? 'disabled' : ''; ?>>
                &#9632; Stop Server
            </button>
            <button class="btn btn-success" onclick="checkWS()" id="btnCheck">
                &#8635; Refresh Status
            </button>
        </div>
        <div class="log-box" id="wsLog">WebSocket server control panel ready.</div>
        <span class="php-cmd">php api/websocket/server.php</span>
    </div>

    <!-- Quick Links -->
    <div class="card">
        <h2><span class="dot green"></span> Quick Links</h2>
        <div class="pages">
            <div class="page-row">
                <div>
                    <div class="name">Landing Page</div>
                    <div class="desc">Register & login</div>
                </div>
                <a href="index.html">Open</a>
            </div>
            <div class="page-row">
                <div>
                    <div class="name">Gem Forge</div>
                    <div class="desc">Match-3 puzzle game</div>
                </div>
                <a href="game.html">Open</a>
            </div>
            <div class="page-row">
                <div>
                    <div class="name">Pixel Canvas</div>
                    <div class="desc">200x200 collaborative grid</div>
                </div>
                <a href="canvas.html">Open</a>
            </div>
            <div class="page-row">
                <div>
                    <div class="name">Leaderboard</div>
                    <div class="desc">Player rankings</div>
                </div>
                <a href="leaderboard.html">Open</a>
            </div>
            <div class="page-row">
                <div>
                    <div class="name">Profile</div>
                    <div class="desc">Your stats & achievements</div>
                </div>
                <a href="profile.html">Open</a>
            </div>
        </div>
    </div>

    <!-- Database Schema -->
    <div class="card schema-sql">
        <h2><span class="dot yellow"></span> Database Schema</h2>
        <div class="schema-box">
            <pre><?php echo htmlspecialchars(file_get_contents(__DIR__ . '/database/schema.sql')); ?></pre>
        </div>
        <button class="btn btn-danger sql-btn" onclick="resetDB()">
            &#9888; Reset Database
        </button>
        <span class="php-cmd" id="resetOutput" style="display:none;"></span>
    </div>

</div>

<div class="footer">
    PixelForge &mdash; Play. Earn. Create. &mdash; <a href="start.php" style="color:#7c3aed;text-decoration:none;">Refresh Dashboard</a>
</div>

<script>
const logEl = document.getElementById('wsLog');

function log(msg) {
    const time = new Date().toLocaleTimeString();
    logEl.innerHTML += `\n[${time}] ${msg}`;
    logEl.scrollTop = logEl.scrollHeight;
}

function startWS() {
    document.getElementById('btnStart').disabled = true;
    log('Sending start request...');

    fetch('api/ws-control.php?action=start')
        .then(r => r.json())
        .then(data => {
            log(data.message || 'Done');
            setTimeout(checkWS, 1500);
        })
        .catch(e => {
            log('Error: ' + e.message);
            document.getElementById('btnStart').disabled = false;
        });
}

function stopWS() {
    document.getElementById('btnStop').disabled = true;
    log('Sending stop request...');

    fetch('api/ws-control.php?action=stop')
        .then(r => r.json())
        .then(data => {
            log(data.message || 'Done');
            setTimeout(checkWS, 1000);
        })
        .catch(e => {
            log('Error: ' + e.message);
            document.getElementById('btnStop').disabled = false;
        });
}

function checkWS() {
    fetch('api/ws-control.php?action=status')
        .then(r => r.json())
        .then(data => {
            const statusEl = document.querySelector('.ws-status');
            const dotEl = document.querySelector('.ws-status .pulse');
            const btnStart = document.getElementById('btnStart');
            const btnStop = document.getElementById('btnStop');

            if (data.running) {
                statusEl.className = 'ws-status running';
                statusEl.innerHTML = '<div class="pulse"></div> Running on port ' + data.port;
                btnStart.disabled = true;
                btnStop.disabled = false;
                log('Server is running on port ' + data.port);
            } else {
                statusEl.className = 'ws-status stopped';
                statusEl.innerHTML = '<div class="pulse"></div> Not running';
                btnStart.disabled = false;
                btnStop.disabled = true;
                log('Server is not running');
            }
        })
        .catch(e => log('Status check failed: ' + e.message));
}

function resetDB() {
    if (!confirm('This will DROP all tables and re-import the schema. Are you sure?')) return;

    log('Resetting database...');

    fetch('api/ws-control.php?action=reset_db')
        .then(r => r.json())
        .then(data => {
            log(data.message || 'Database reset');
            if (data.success) {
                setTimeout(() => location.reload(), 1000);
            }
        })
        .catch(e => log('Reset failed: ' + e.message));
}

// Auto-check status on load
checkWS();
</script>

</body>
</html>
