<?php
declare(strict_types=1);

namespace App\Infrastructure\Database;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Singleton Database Connector using configuration system
 */
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

    /**
     * Get database connection using environment variables directly
     */
    public static function getConnection(): PDO
    {
        if (self::$pdoInstance === null) {
            // Usar variables de entorno directamente como funcionaba antes
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
                
                // Log conexión exitosa
                if (function_exists('logger')) {
                    try {
                        logger('database')->info('Database connection established', [
                            'host' => $host,
                            'port' => $port,
                            'database' => $db,
                            'charset' => $charset
                        ]);
                    } catch (\Throwable $e) {
                        error_log("Logging error in DatabaseConnector: " . $e->getMessage());
                    }
                }
                
            } catch (PDOException $e) {
                // Log error de conexión
                if (function_exists('logger')) {
                    try {
                        logger('database')->error('Database connection failed', [
                            'host' => $host,
                            'port' => $port,
                            'database' => $db,
                            'error' => $e->getMessage(),
                            'code' => $e->getCode()
                        ]);
                    } catch (\Throwable $logError) {
                        error_log("Logging error in DatabaseConnector: " . $logError->getMessage());
                    }
                }
                
                error_log("Database Connection Error: " . $e->getMessage());
                throw new RuntimeException(
                    "Database connection failed. Check server logs. Details: " . $e->getMessage()
                );
            }
        }
        return self::$pdoInstance;
    }

    /**
     * Reset connection (útil para testing)
     */
    public static function resetConnection(): void
    {
        self::$pdoInstance = null;
    }
} 