<?php

class Redis
{
    private static $instance = null;
    private $connection;

    private function __construct()
    {
        try {
            $this->connection = new \Redis();
            $password = REDIS_PASS ? REDIS_PASS : null;

            if (!$this->connection->connect(REDIS_HOST, REDIS_PORT)) {
                throw new Exception('Could not connect to Redis');
            }

            if ($password) {
                $this->connection->auth($password);
            }

            $this->connection->select(REDIS_DB);
        } catch (Exception $e) {
            error_log('Redis connection failed: ' . $e->getMessage());
            throw new Exception('Redis connection failed');
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection()
    {
        return $this->connection;
    }

    public static function get($key)
    {
        return self::getInstance()->connection->get($key);
    }

    public static function set($key, $value, $ttl = null)
    {
        $conn = self::getInstance()->connection;
        if ($ttl) {
            return $conn->setex($key, $ttl, $value);
        }
        return $conn->set($key, $value);
    }

    public static function del($key)
    {
        return self::getInstance()->connection->del($key);
    }

    public static function exists($key)
    {
        return self::getInstance()->connection->exists($key);
    }

    public static function incr($key)
    {
        return self::getInstance()->connection->incr($key);
    }

    public static function incrBy($key, $count)
    {
        return self::getInstance()->connection->incrBy($key, $count);
    }

    public static function decr($key)
    {
        return self::getInstance()->connection->decr($key);
    }

    public static function decrBy($key, $count)
    {
        return self::getInstance()->connection->decrBy($key, $count);
    }

    public static function expire($key, $seconds)
    {
        return self::getInstance()->connection->expire($key, $seconds);
    }

    public static function ttl($key)
    {
        return self::getInstance()->connection->ttl($key);
    }

    public static function getRange($key, $start, $end)
    {
        return self::getInstance()->connection->getrange($key, $start, $end);
    }

    public static function setRange($key, $offset, $value)
    {
        return self::getInstance()->connection->setrange($key, $offset, $value);
    }

    public static function hSet($key, $field, $value)
    {
        return self::getInstance()->connection->hSet($key, $field, $value);
    }

    public static function hGet($key, $field)
    {
        return self::getInstance()->connection->hGet($key, $field);
    }

    public static function hGetAll($key)
    {
        return self::getInstance()->connection->hGetAll($key);
    }

    public static function hDel($key, $field)
    {
        return self::getInstance()->connection->hDel($key, $field);
    }

    public static function publish($channel, $message)
    {
        return self::getInstance()->connection->publish($channel, $message);
    }

    public static function subscribe($channels, $callback)
    {
        return self::getInstance()->connection->subscribe($channels, $callback);
    }

}

?>