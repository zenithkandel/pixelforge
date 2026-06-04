<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

header('Content-Type: application/json');
start_safe_session();

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'place':
        handlePlacePixel();
        break;
    case 'get_all':
        handleGetAllPixels();
        break;
    case 'get_area':
        handleGetArea();
        break;
    case 'get_user_pixels':
        handleGetUserPixels();
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

function requireAuth() {
    if (!is_logged_in()) {
        jsonResponse(['success' => false, 'message' => 'Authentication required'], 401);
    }
    return current_user_id();
}

function requireCsrf() {
    if (!csrf_header_verify()) {
        jsonResponse(['success' => false, 'message' => 'Invalid CSRF token'], 403);
    }
}

function checkRateLimit($userId) {
    $now = time();
    $window = 60;
    $maxPlacements = 20;

    if (!isset($_SESSION['pixel_timestamps'])) {
        $_SESSION['pixel_timestamps'] = [];
    }

    $_SESSION['pixel_timestamps'] = array_filter(
        $_SESSION['pixel_timestamps'],
        function ($ts) use ($now, $window) {
            return ($now - $ts) < $window;
        }
    );

    if (count($_SESSION['pixel_timestamps']) >= $maxPlacements) {
        $oldest = min($_SESSION['pixel_timestamps']);
        $retryAfter = $window - ($now - $oldest);
        jsonResponse([
            'success' => false,
            'message' => 'Rate limit exceeded. Try again in ' . $retryAfter . ' seconds.',
            'retry_after' => $retryAfter
        ], 429);
    }

    $_SESSION['pixel_timestamps'][] = $now;
}

function handlePlacePixel() {
    $userId = requireAuth();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['success' => false, 'message' => 'POST request required'], 405);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        jsonResponse(['success' => false, 'message' => 'Invalid request body'], 400);
    }

    $x = $input['x'] ?? null;
    $y = $input['y'] ?? null;
    $color = $input['color'] ?? null;

    if ($x === null || $y === null || $color === null) {
        jsonResponse(['success' => false, 'message' => 'x, y, and color are required'], 400);
    }

    $x = (int) $x;
    $y = (int) $y;

    if ($x < 0 || $x >= GRID_SIZE || $y < 0 || $y >= GRID_SIZE) {
        jsonResponse(['success' => false, 'message' => 'Coordinates out of bounds (0-' . (GRID_SIZE - 1) . ')'], 400);
    }

    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
        jsonResponse(['success' => false, 'message' => 'Invalid hex color format (must be #rrggbb)'], 400);
    }

    checkRateLimit($userId);

    $pdo = db();

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            $pdo->rollBack();
            jsonResponse(['success' => false, 'message' => 'User not found'], 404);
        }

        $existingPixel = null;
        $checkStmt = $pdo->prepare("SELECT owner_id FROM pixels WHERE x = ? AND y = ?");
        $checkStmt->execute([$x, $y]);
        $existingPixel = $checkStmt->fetch();

        $isFreeRepaint = $existingPixel && $existingPixel['owner_id'] == $userId;

        if (!$isFreeRepaint && $user['balance'] < PIXEL_COST) {
            $pdo->rollBack();
            jsonResponse(['success' => false, 'message' => 'Insufficient balance'], 400);
        }

        $stmt = $pdo->prepare(
            "INSERT INTO pixels (x, y, color, owner_id) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE color = VALUES(color), owner_id = VALUES(owner_id), placed_at = CURRENT_TIMESTAMP"
        );
        $stmt->execute([$x, $y, $color, $userId]);

        if (!$isFreeRepaint) {
            $stmt = $pdo->prepare("UPDATE users SET balance = balance - 1, total_pixels_placed = total_pixels_placed + 1 WHERE id = ?");
            $stmt->execute([$userId]);

            $description = "Pixel placed at ({$x}, {$y})";
            $stmt = $pdo->prepare("INSERT INTO transactions (user_id, amount, type, description) VALUES (?, 1, 'spend', ?)");
            $stmt->execute([$userId, $description]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET total_pixels_placed = total_pixels_placed + 1 WHERE id = ?");
            $stmt->execute([$userId]);
        }

        $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $updatedUser = $stmt->fetch();

        $pdo->commit();

        jsonResponse([
            'success' => true,
            'pixel' => [
                'x' => $x,
                'y' => $y,
                'color' => $color,
                'owner_id' => (int) $userId
            ],
            'new_balance' => (int) $updatedUser['balance'],
            'free_repaint' => $isFreeRepaint
        ]);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        jsonResponse(['success' => false, 'message' => 'Failed to place pixel'], 500);
    }
}

function handleGetAllPixels() {
    try {
        $stmt = db()->query("SELECT x, y, color, owner_id FROM pixels");
        $pixels = $stmt->fetchAll();

        jsonResponse([
            'success' => true,
            'pixels' => $pixels,
            'count' => count($pixels)
        ]);
    } catch (PDOException $e) {
        jsonResponse(['success' => false, 'message' => 'Failed to load pixels'], 500);
    }
}

function handleGetArea() {
    $x1 = isset($_GET['x1']) ? (int) $_GET['x1'] : null;
    $y1 = isset($_GET['y1']) ? (int) $_GET['y1'] : null;
    $x2 = isset($_GET['x2']) ? (int) $_GET['x2'] : null;
    $y2 = isset($_GET['y2']) ? (int) $_GET['y2'] : null;

    if ($x1 === null || $y1 === null || $x2 === null || $y2 === null) {
        jsonResponse(['success' => false, 'message' => 'x1, y1, x2, and y2 are required'], 400);
    }

    $x1 = max(0, min(GRID_SIZE - 1, $x1));
    $y1 = max(0, min(GRID_SIZE - 1, $y1));
    $x2 = max(0, min(GRID_SIZE - 1, $x2));
    $y2 = max(0, min(GRID_SIZE - 1, $y2));

    $minX = min($x1, $x2);
    $maxX = max($x1, $x2);
    $minY = min($y1, $y2);
    $maxY = max($y1, $y2);

    try {
        $stmt = db()->prepare("SELECT x, y, color, owner_id FROM pixels WHERE x BETWEEN ? AND ? AND y BETWEEN ? AND ?");
        $stmt->execute([$minX, $maxX, $minY, $maxY]);
        $pixels = $stmt->fetchAll();

        jsonResponse([
            'success' => true,
            'pixels' => $pixels,
            'count' => count($pixels)
        ]);
    } catch (PDOException $e) {
        jsonResponse(['success' => false, 'message' => 'Failed to load pixels'], 500);
    }
}

function handleGetUserPixels() {
    $userId = requireAuth();

    try {
        $stmt = db()->prepare("SELECT x, y, color, owner_id FROM pixels WHERE owner_id = ?");
        $stmt->execute([$userId]);
        $pixels = $stmt->fetchAll();

        jsonResponse([
            'success' => true,
            'pixels' => $pixels,
            'count' => count($pixels)
        ]);
    } catch (PDOException $e) {
        jsonResponse(['success' => false, 'message' => 'Failed to load pixels'], 500);
    }
}
