<?php
// includes/session.php
// Custom Redis-backed session handler

class RedisSessionHandler implements SessionHandlerInterface {
    private $redis;
    private $prefix = 'sess:';
    private $ttl = 86400; // 24 hours

    public function __construct() {
        $this->redis = RedisClient::getInstance(REDIS_SESSION_DB);
    }

    public function open(string $path, string $name): bool {
        return true;
    }

    public function close(): bool {
        return true;
    }

    public function read(string $id): string|false {
        $data = $this->redis->get($this->prefix . $id);
        return $data ? $data : '';
    }

    public function write(string $id, string $data): bool {
        return $this->redis->setex($this->prefix . $id, $this->ttl, $data);
    }

    public function destroy(string $id): bool {
        $this->redis->del($this->prefix . $id);
        return true;
    }

    public function gc(int $max_lifetime): int|false {
        // Redis handles expiration via TTL
        return 0;
    }
}
