<?php
// includes/redis.php
class RedisClient {
    private static $instance = null;
    
    public static function getInstance($db = null) {
        if ($db === null) {
            $db = REDIS_DB;
        }

        // Return a mocked Redis client if the extension is missing
        if (!class_exists('Redis')) {
            return new MockRedis();
        }

        try {
            $redis = new Redis();
            $redis->connect(REDIS_HOST, REDIS_PORT);
            if (!empty(REDIS_PASS)) {
                $redis->auth(REDIS_PASS);
            }
            $redis->select($db);
            return $redis;
        } catch (Exception $e) {
            error_log("Redis Connection Error: " . $e->getMessage());
            // Fallback to MockRedis so the app doesn't crash entirely when redis is down
            return new MockRedis();
        }
    }
}

class MockRedis {
    private $data = [];
    public function get($key) { return $this->data[$key] ?? false; }
    public function set($key, $val, $ttl=null) { $this->data[$key] = $val; return true; }
    public function setex($key, $ttl, $val) { $this->data[$key] = $val; return true; }
    public function del($key) { unset($this->data[$key]); return 1; }
    public function incr($key) { $this->data[$key] = ($this->data[$key]??0) + 1; return $this->data[$key]; }
    public function expire($key, $ttl) { return true; }
    public function rPush($key, $val) { return true; }
    public function blPop($key, $timeout) { return null; }
    public function publish($channel, $msg) { return true; }
    public function setnx($key, $val) { if(isset($this->data[$key])) return false; $this->data[$key]=$val; return true; }
}
