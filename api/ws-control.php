<?php
header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$phpBinary = 'C:\\xampp\\php\\php.exe';
$serverScript = __DIR__ . '\\websocket\\server.php';
$pidFile = __DIR__ . '\\websocket\\server.pid';

function isRunning() {
    global $pidFile;

    // Check by PID file first
    if (file_exists($pidFile)) {
        $pid = trim(file_get_contents($pidFile));
        if ($pid) {
            $output = [];
            exec("tasklist /FI \"PID eq $pid\" /NH 2>nul", $output);
            if (!empty($output) && strpos($output[0], (string)$pid) !== false) {
                return true;
            }
        }
    }

    // Fallback: check if port 8080 is listening
    $netOutput = [];
    exec('netstat -ano | findstr ":8080 " | findstr LISTENING', $netOutput);
    return !empty($netOutput);
}

function getPid() {
    global $pidFile;
    return file_exists($pidFile) ? trim(file_get_contents($pidFile)) : null;
}

switch ($action) {

    case 'status':
        $running = isRunning();
        echo json_encode([
            'running' => $running,
            'port' => 8080,
            'pid' => getPid()
        ]);
        break;

    case 'start':
        if (isRunning()) {
            echo json_encode(['success' => true, 'message' => 'Server already running (PID: ' . getPid() . ')']);
            break;
        }

        // Use popen for non-blocking background launch
        $cmd = "\"{$phpBinary}\" \"{$serverScript}\"";
        $proc = popen("start /min \"PixelForge WS\" cmd /c \"{$cmd}\" 2>&1", 'r');
        if ($proc) {
            pclose($proc);
        }

        sleep(2);

        // Find PID via WMI
        $wmiOutput = [];
        exec('wmic process where "CommandLine like \'%server.php%\'" get ProcessId,CommandLine /value 2>nul', $wmiOutput);
        $pid = null;
        foreach ($wmiOutput as $line) {
            if (preg_match('/ProcessId=(\d+)/', $line, $m)) {
                $pid = $m[1];
                break;
            }
        }

        if ($pid) {
            file_put_contents($pidFile, $pid);
            echo json_encode([
                'success' => true,
                'message' => "WebSocket server started (PID: $pid) on port 8080",
                'pid' => $pid
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'message' => 'WebSocket server started on port 8080 (PID could not be determined)',
                'pid' => null
            ]);
        }
        break;

    case 'stop':
        $pid = getPid();

        if ($pid) {
            exec("taskkill /PID $pid /F > nul 2>&1", $output, $code);
            @unlink($pidFile);

            if ($code === 0) {
                echo json_encode(['success' => true, 'message' => "Server stopped (PID: $pid)"]);
            } else {
                echo json_encode(['success' => true, 'message' => "Stop signal sent to PID $pid"]);
            }
        } else {
            // Try to kill by port
            exec('netstat -ano | findstr :8080 | findstr LISTENING', $netOutput);
            foreach ($netOutput as $line) {
                if (preg_match('/(\d+)\s*$/', trim($line), $m)) {
                    $foundPid = $m[1];
                    exec("taskkill /PID $foundPid /F > nul 2>&1");
                }
            }
            echo json_encode(['success' => true, 'message' => 'Stopped processes on port 8080']);
        }
        break;

    case 'reset_db':
        try {
            require_once __DIR__ . '/../api/config.php';
            require_once __DIR__ . '/../includes/db.php';
            $pdo = db();

            $tables = ['users', 'pixels', 'game_sessions', 'score_log', 'achievements', 'user_achievements', 'login_attempts', 'transactions'];
            foreach ($tables as $table) {
                $pdo->exec("DROP TABLE IF EXISTS `$table`");
            }

            $schema = file_get_contents(__DIR__ . '/../database/schema.sql');
            if ($schema) {
                $pdo->exec($schema);
                echo json_encode(['success' => true, 'message' => 'Database reset: 8 tables recreated, 16 achievements seeded']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Schema file not found']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Reset failed: ' . $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action: ' . $action]);
        break;
}
