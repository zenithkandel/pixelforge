<?php

class SessionHandler implements SessionHandlerInterface
{
    private $redis;
    private $prefix = 'sess:';
    private $ttl = SESSION_TIMEOUT;

    public function open($path, $name)
    {
        $this->redis = \Redis::getInstance()->getConnection();
        return true;
    }

    public function close()
    {
        return true;
    }

    public function read($session_id)
    {
        $data = $this->redis->get($this->prefix . $session_id);
        return $data ?: '';
    }

    public function write($session_id, $data)
    {
        $this->redis->setex($this->prefix . $session_id, $this->ttl, $data);
        return true;
    }

    public function destroy($session_id)
    {
        $this->redis->del($this->prefix . $session_id);
        return true;
    }

    public function gc($maxlifetime)
    {
        return true; // Redis handles expiry automatically with SETEX
    }
}

// Initialize session handling
session_set_save_handler(new SessionHandler());

// Session configuration
ini_set('session.name', 'PIXELFORGE_SESSION');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.use_trans_sid', '0');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', APP_ENV === 'production' ? '1' : '0');
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);

// Start session
session_start();

// Session helper functions
function regenerate_session()
{
    session_regenerate_id(true);
}

function destroy_session()
{
    $_SESSION = [];
    session_destroy();
}

function get_session_value($key, $default = null)
{
    return $_SESSION[$key] ?? $default;
}

function set_session_value($key, $value)
{
    $_SESSION[$key] = $value;
}

?>