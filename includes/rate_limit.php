<?php

class RateLimit
{
    private static $redis;

    private static function getRedis()
    {
        if (self::$redis === null) {
            self::$redis = \Redis::getInstance();
        }
        return self::$redis;
    }

    public static function check($key, $limit, $window = 60)
    {
        $redis = self::getRedis();
        $key_full = "ratelimit:$key";
        $current = $redis->get($key_full);

        if ($current === false) {
            $redis->set($key_full, 1, $window);
            return true;
        }

        if ($current >= $limit) {
            return false;
        }

        $redis->incr($key_full);
        return true;
    }

    public static function getRemainingTime($key)
    {
        $redis = self::getRedis();
        $key_full = "ratelimit:$key";
        return $redis->ttl($key_full);
    }

    public static function reset($key)
    {
        $redis = self::getRedis();
        $key_full = "ratelimit:$key";
        $redis->del($key_full);
    }
}

?>