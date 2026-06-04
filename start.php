<?php
/**
 * PixelForge - Start & Verify Script
 *
 * Run this script to:
 *   1. Verify system requirements
 *   2. Test database connection
 *   3. Check database tables
 *   4. Verify file permissions
 *   5. Optionally start the WebSocket server
 *
 * Usage:
 *   php start.php              (interactive menu)
 *   php start.php --check      (verification only)
 *   php start.php --ws         (start WebSocket server)
 *   php start.php --ws-port 9090  (custom port)
 */

$isCli = php_sapi_name() === 'cli';

function output($msg, $type = 'info', $cli = true) {
    if (!$cli) return;
    $colors = [
        'info'    => "\033[36m",
        'success' => "\033[32m",
        'warning' => "\033[33m",
        'error'   => "\033[31m",
        'header'  => "\033[1;35m",
        'reset'   => "\033[0m"
    ];
    $prefix = ['info' => '  ', 'success' => '  OK ', 'warning' => '  WARN ', 'error' => '  FAIL '];
    $c = $colors[$type] ?? '';
    $r = $colors['reset'];
    $p = $prefix[$type] ?? '  ';
    echo "{$c}{$p}{$msg}{$r}\n";
}

function separator($cli = true) {
    if ($cli) echo "\033[90m" . str_repeat('-', 60) . "\033[0m\n";
}

// ─── Parse CLI arguments ───
$argv = $argv ?? [];
$mode = 'interactive';
$wsPort = 8080;

for ($i = 1; $i < count($argv); $i++) {
    if ($argv[$i] === '--check') $mode = 'check';
    if ($argv[$i] === '--ws')    $mode = 'ws';
    if ($argv[$i] === '--ws-port' && isset($argv[$i + 1])) {
        $wsPort = (int) $argv[$i + 1];
    }
}

// ─── Header ───
if ($isCli) {
    echo "\n";
    echo "\033[1;35m";
    echo "  ██████╗ ██╗██╗  ██╗████████╗███████╗██████╗ ███╗   ███╗\n";
    echo "  ██╔══██╗██║╚██╗██╔╝╚══██╔══╝██╔════╝██╔══██╗████╗ ████║\n";
    echo "  ██████╔╝██║ ╚███╔╝    ██║   █████╗  ██████╔╝██╔████╔██║\n";
    echo "  ██╔═══╝ ██║ ██╔██╗    ██║   ██╔══╝  ██╔══██╗██║╚██╔╝██║\n";
    echo "  ██║     ██║██╔╝ ██╗   ██║   ███████╗██║  ██║██║ ╚═╝ ██║\n";
    echo "  ╚═╝     ╚═╝╚═╝  ╚═╝   ╚═╝   ╚══════╝╚═╝  ╚═╝╚═╝     ╚═╝\n";
    echo "\033[0m";
    echo "  \033[90mPlay. Earn. Create.\033[0m\n\n";
}

// ─── System Requirements ───
output("SYSTEM REQUIREMENTS", 'header');
separator();

$phpVersionOk = version_compare(PHP_VERSION, '8.0.0', '>=');
output("PHP Version: " . PHP_VERSION, $phpVersionOk ? 'success' : 'error');

$extensions = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'session'];
foreach ($extensions as $ext) {
    $loaded = extension_loaded($ext);
    output("Extension: {$ext}", $loaded ? 'success' : 'error');
}

// ─── Database Check ───
output("\nDATABASE", 'header');
separator();

require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/includes/db.php';

try {
    $pdo = db();
    output("Connection to " . DB_NAME . "@" . DB_HOST, 'success');
} catch (\Exception $e) {
    output("Connection failed: " . $e->getMessage(), 'error');
    output("Make sure MySQL is running in XAMPP.", 'warning');
    if ($mode === 'interactive') { echo "\nPress Enter to exit..."; fgets(STDIN); }
    exit(1);
}

$requiredTables = ['users', 'pixels', 'game_sessions', 'score_log', 'achievements', 'user_achievements', 'login_attempts', 'transactions'];
$existingTables = [];
$stmt = $pdo->query("SHOW TABLES");
while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
    $existingTables[] = $row[0];
}

foreach ($requiredTables as $table) {
    $exists = in_array($table, $existingTables);
    output("Table: {$table}", $exists ? 'success' : 'error');
}

if (!$exists && $mode !== 'check') {
    output("Some tables are missing. Importing schema...", 'warning');
    $schema = file_get_contents(__DIR__ . '/database/schema.sql');
    if ($schema) {
        try {
            $pdo->exec($schema);
            output("Schema imported successfully", 'success');
            // Re-check tables
            $existingTables = [];
            $stmt = $pdo->query("SHOW TABLES");
            while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                $existingTables[] = $row[0];
            }
        } catch (\Exception $e) {
            output("Schema import failed: " . $e->getMessage(), 'error');
        }
    }
}

$achievementCount = $pdo->query("SELECT COUNT(*) FROM achievements")->fetchColumn();
output("Achievements seeded: {$achievementCount}", $achievementCount >= 16 ? 'success' : 'warning');

$userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
output("Registered users: {$userCount}", 'info');

$pixelCount = $pdo->query("SELECT COUNT(*) FROM pixels")->fetchColumn();
output("Pixels placed: {$pixelCount}", 'info');

// ─── File & Directory Check ───
output("\nFILES & DIRECTORIES", 'header');
separator();

$files = [
    'api/config.php',
    'api/auth.php',
    'api/game.php',
    'api/pixels.php',
    'api/canvas.php',
    'api/websocket/server.php',
    'api/websocket/ChatServer.php',
    'includes/db.php',
    'includes/auth.php',
    'includes/csrf.php',
    'assets/css/main.css',
    'assets/css/game.css',
    'assets/css/canvas.css',
    'assets/js/utils.js',
    'assets/js/game.js',
    'assets/js/game-renderer.js',
    'assets/js/game-animations.js',
    'assets/js/game-powerups.js',
    'index.html',
    'game.html',
    'canvas.html',
    'leaderboard.html',
    'profile.html',
    '.htaccess',
    'vendor/autoload.php',
];

foreach ($files as $file) {
    $path = __DIR__ . '/' . $file;
    output($file, file_exists($path) ? 'success' : 'error');
}

$vendorOk = file_exists(__DIR__ . '/vendor/autoload.php') && file_exists(__DIR__ . '/vendor/cboden/ratchet');
output("Ratchet WebSocket library", $vendorOk ? 'success' : 'error');

$logsDir = __DIR__ . '/logs';
if (!is_dir($logsDir)) {
    mkdir($logsDir, 0755, true);
    output("Created logs/ directory", 'success');
} else {
    output("logs/ directory exists", 'success');
}

// ─── Composer Autoload ───
output("\nCOMPOSER AUTOLOAD", 'header');
separator();

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
    output("Autoloader loaded", 'success');

    $ratchetClass = class_exists('Ratchet\Server\IoServer');
    output("Ratchet classes available", $ratchetClass ? 'success' : 'error');
} else {
    output("vendor/autoload.php not found. Run: composer require cboden/ratchet", 'error');
}

// ─── WebSocket Server ───
if ($mode === 'ws' || $mode === 'interactive') {
    output("\nWEBSOCKET SERVER", 'header');
    separator();

    if (!$ratchetClass ?? false) {
        output("Cannot start WebSocket server - Ratchet not available", 'error');
    } else {
        output("Starting WebSocket server on port {$wsPort}...", 'info');
        output("Press Ctrl+C to stop.", 'info');
        echo "\n";

        $serverFile = __DIR__ . '/api/websocket/server.php';
        if ($mode === 'interactive') {
            echo "\033[36m";
            echo "  Command: php {$serverFile} {$wsPort}\n";
            echo "\033[0m\n";

            echo "  Start WebSocket server? [Y/n]: ";
            $handle = fopen("php://stdin", "r");
            $line = trim(fgets($handle));
            fclose($handle);

            if (strtolower($line) !== 'n' && $line !== '') {
                passthru("\"" . PHP_BINARY . "\" \"{$serverFile}\" {$wsPort}");
            } else {
                output("WebSocket server skipped.", 'info');
                echo "\n";
            }
        } else {
            passthru("\"" . PHP_BINARY . "\" \"{$serverFile}\" {$wsPort}");
        }
    }
}

if ($mode === 'check') {
    output("\nAll checks complete!", 'success');
    echo "\n";
}
