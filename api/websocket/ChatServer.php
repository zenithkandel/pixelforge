<?php
require_once __DIR__ . '/../../includes/db.php';

class ChatServer implements \Ratchet\MessageComponentInterface {
    protected $clients;
    protected $users;
    protected $pdo;
    protected $nextId = 1;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->users = [];
        $this->pdo = db();
        echo "WebSocket server started\n";
    }

    public function onOpen(\Ratchet\ConnectionInterface $conn) {
        $conn->resourceId = $this->nextId++;
        $this->clients->attach($conn);
        echo "New connection ({$conn->resourceId})\n";

        $conn->send(json_encode([
            'type' => 'connected',
            'message' => 'Welcome to PixelForge WebSocket',
            'resourceId' => $conn->resourceId
        ]));

        $this->broadcastUserCount();
    }

    public function onMessage(\Ratchet\ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);

        if (!$data || !isset($data['type'])) {
            $from->send(json_encode(['type' => 'error', 'message' => 'Invalid message format']));
            return;
        }

        switch ($data['type']) {
            case 'auth':
                $this->handleAuth($from, $data);
                break;
            case 'place_pixel':
                $this->handlePlacePixel($from, $data);
                break;
            case 'ping':
                $from->send(json_encode(['type' => 'pong', 'timestamp' => time()]));
                break;
            default:
                $from->send(json_encode(['type' => 'error', 'message' => 'Unknown message type']));
        }
    }

    public function onClose(\Ratchet\ConnectionInterface $conn) {
        $this->clients->detach($conn);

        if (isset($this->users[$conn->resourceId])) {
            unset($this->users[$conn->resourceId]);
            echo "User disconnected ({$conn->resourceId})\n";
        } else {
            echo "Connection closed ({$conn->resourceId})\n";
        }

        $this->broadcastUserCount();
    }

    public function onError(\Ratchet\ConnectionInterface $conn, \Exception $e) {
        echo "Error: {$e->getMessage()}\n";
        $conn->close();
    }

    protected function handleAuth(\Ratchet\ConnectionInterface $conn, $data) {
        $token = $data['token'] ?? '';

        if (empty($token)) {
            $conn->send(json_encode(['type' => 'auth_fail', 'message' => 'No token provided']));
            return;
        }

        try {
            $stmt = $this->pdo->prepare("SELECT id, username, balance, level FROM users WHERE id = ?");
            $stmt->execute([$data['user_id'] ?? 0]);
            $user = $stmt->fetch();

            if ($user) {
                $this->users[$conn->resourceId] = [
                    'conn' => $conn,
                    'user_id' => $user['id'],
                    'username' => $user['username']
                ];

                $conn->send(json_encode([
                    'type' => 'auth_ok',
                    'user' => [
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'balance' => $user['balance'],
                        'level' => $user['level']
                    ]
                ]));

                echo "User authenticated: {$user['username']} ({$conn->resourceId})\n";
                $this->sendCanvasState($conn);
                $this->broadcastUserCount();
            } else {
                $conn->send(json_encode(['type' => 'auth_fail', 'message' => 'Invalid user']));
            }
        } catch (\Exception $e) {
            $conn->send(json_encode(['type' => 'error', 'message' => 'Authentication error']));
        }
    }

    protected function handlePlacePixel(\Ratchet\ConnectionInterface $conn, $data) {
        if (!isset($this->users[$conn->resourceId])) {
            $conn->send(json_encode(['type' => 'error', 'message' => 'Not authenticated']));
            return;
        }

        $user = $this->users[$conn->resourceId];
        $x = intval($data['x'] ?? -1);
        $y = intval($data['y'] ?? -1);
        $color = $data['color'] ?? '';

        if ($x < 0 || $x >= 200 || $y < 0 || $y >= 200) {
            $conn->send(json_encode(['type' => 'error', 'message' => 'Invalid coordinates']));
            return;
        }

        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            $conn->send(json_encode(['type' => 'error', 'message' => 'Invalid color']));
            return;
        }

        try {
            $stmt = $this->pdo->prepare("SELECT balance FROM users WHERE id = ?");
            $stmt->execute([$user['user_id']]);
            $balance = $stmt->fetchColumn();

            if ($balance < 1) {
                $conn->send(json_encode(['type' => 'error', 'message' => 'Not enough gems']));
                return;
            }

            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare("
                INSERT INTO pixels (x, y, color, owner_id)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    color = VALUES(color),
                    owner_id = VALUES(owner_id),
                    placed_at = CURRENT_TIMESTAMP
            ");
            $stmt->execute([$x, $y, $color, $user['user_id']]);

            $stmt = $this->pdo->prepare("UPDATE users SET balance = balance - 1, total_pixels_placed = total_pixels_placed + 1 WHERE id = ?");
            $stmt->execute([$user['user_id']]);

            $stmt = $this->pdo->prepare("INSERT INTO transactions (user_id, amount, type, description) VALUES (?, 1, 'spend', ?)");
            $stmt->execute([$user['user_id'], "Pixel placed at ($x, $y)"]);

            $this->pdo->commit();

            $stmt = $this->pdo->prepare("SELECT balance FROM users WHERE id = ?");
            $stmt->execute([$user['user_id']]);
            $newBalance = $stmt->fetchColumn();

            $conn->send(json_encode([
                'type' => 'pixel_confirmed',
                'x' => $x,
                'y' => $y,
                'color' => $color,
                'new_balance' => $newBalance
            ]));

            $this->broadcast([
                'type' => 'pixel_placed',
                'x' => $x,
                'y' => $y,
                'color' => $color,
                'owner_id' => $user['user_id'],
                'username' => $user['username']
            ]);

        } catch (\Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $conn->send(json_encode(['type' => 'error', 'message' => 'Failed to place pixel']));
            echo "Error placing pixel: {$e->getMessage()}\n";
        }
    }

    protected function sendCanvasState(\Ratchet\ConnectionInterface $conn) {
        try {
            $stmt = $this->pdo->query("SELECT x, y, color, owner_id FROM pixels");
            $pixels = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $conn->send(json_encode([
                'type' => 'canvas_sync',
                'pixels' => $pixels,
                'count' => count($pixels)
            ]));
        } catch (\Exception $e) {
            echo "Error sending canvas state: {$e->getMessage()}\n";
        }
    }

    protected function broadcast($data) {
        $msg = json_encode($data);
        foreach ($this->users as $resourceId => $user) {
            try {
                $user['conn']->send($msg);
            } catch (\Exception $e) {
                unset($this->users[$resourceId]);
            }
        }
    }

    protected function broadcastUserCount() {
        $count = count($this->users);
        $msg = json_encode(['type' => 'user_count', 'count' => $count]);

        foreach ($this->clients as $client) {
            try {
                $client->send($msg);
            } catch (\Exception $e) {
                $this->clients->detach($client);
            }
        }
    }
}
