<?php
declare(strict_types=1);

namespace App\Infrastructure\Database;

use PDO;
use PDOException;
use RuntimeException;

class DatabaseConnector
{
    private static ?PDO $pdoInstance = null;

    // Private constructor to prevent direct instantiation.
    private function __construct() {}

    // Prevent cloning.
    private function __clone() {}

    // Prevent unserialization.
    public function __wakeup() {
        throw new RuntimeException("Cannot unserialize a singleton.");
    }

    public static function getConnection(): PDO
    {
        if (self::$pdoInstance === null) {
            $host = $_ENV['DB_HOST'] ?? 'mysql';
            $port = $_ENV['DB_PORT'] ?? '3306';
            $db   = $_ENV['DB_DATABASE'] ?? 'library_db';
            $user = $_ENV['DB_USERNAME'] ?? 'library_user';
            $pass = $_ENV['DB_PASSWORD'] ?? 'library_pass';
            $charset = 'utf8mb4';

            $dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                self::$pdoInstance = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                // Log the error instead of echoing it directly in a real application
                // For now, we throw a more generic exception to be caught by api.php's handler
                error_log("Database Connection Error: " . $e->getMessage());
                throw new RuntimeException("Database connection failed. Check server logs. Details: " . $e->getMessage());
            }
        }
        return self::$pdoInstance;
    }
} 