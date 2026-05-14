<?php
// includes/db.php
class DB {
    private static $instance = null;

    public static function getInstance() {
        if (self::$instance === null) {
            try {
                $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } catch (PDOException $e) {
                error_log("Database Connection Error: " . $e->getMessage());
                die(json_encode(["ok" => false, "error" => "db_error", "message" => "Database connection failed"]));
            }
        }
        return self::$instance;
    }
}
