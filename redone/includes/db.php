<?php
if (!class_exists('Database')) {
    class Database
    {
        private static $pdo = null;
        public static function getInstance()
        {
            if (self::$pdo === null) {
                $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_NAME);
                self::$pdo = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            }
            return self::$pdo;
        }
        public static function query($sql, $params = [])
        {
            $stmt = self::getInstance()->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        }
        public static function fetch($sql, $params = [])
        {
            return self::query($sql, $params)->fetch();
        }
        public static function fetchAll($sql, $params = [])
        {
            return self::query($sql, $params)->fetchAll();
        }
    }
}
?>