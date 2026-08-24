<?php
declare(strict_types=1);

namespace App\Infrastructure\Database;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Database Connector service for dependency injection
 */
class DatabaseConnector
{
    private array $config;
    private array $mirror;

    public function __construct()
    {
        $this->config = [
            'host' => $_ENV['DB_HOST'] ?? 'mysql',
            'port' => $_ENV['DB_PORT'] ?? '3306',
            'database' => $_ENV['DB_DATABASE'] ?? 'library_db',
            'username' => $_ENV['DB_USERNAME'] ?? 'library_user',
            'password' => $_ENV['DB_PASSWORD'] ?? 'library_pass',
            'charset' => 'utf8mb4'
        ];

        // El mirror puede vivir en OTRO servidor. Es un catálogo público y
        // reconstruible que comparten los stacks de dev y de producción: tenerlo
        // duplicado serían 2,2 GB por entorno y dos cuotas de TMDB y MusicBrainz
        // en vez de una. Cada DB_MIRROR_* cae a su equivalente de la app, así que
        // un despliegue que no declare ninguna sigue viendo el comportamiento
        // original: mismo servidor, mismas credenciales, otro esquema.
        $this->mirror = [
            'host'     => $_ENV['DB_MIRROR_HOST']     ?? $this->config['host'],
            'port'     => $_ENV['DB_MIRROR_PORT']     ?? $this->config['port'],
            'database' => $_ENV['DB_MIRROR_DATABASE'] ?? 'library_mirror',
            'username' => $_ENV['DB_MIRROR_USERNAME'] ?? $this->config['username'],
            'password' => $_ENV['DB_MIRROR_PASSWORD'] ?? $this->config['password'],
            'charset'  => 'utf8mb4',
        ];
    }

    /**
     * Get database connection
     * NOTE: Creates a NEW connection each time to avoid transaction conflicts in concurrent requests
     */
    public function getConnection(): PDO
    {
        return $this->connect($this->config);
    }

    /**
     * Get a connection to the catalog mirror schema (library_mirror).
     *
     * Separate schema, and since the mirror became a service shared by the dev
     * and prod stacks, potentially a separate server too: see DB_MIRROR_HOST in
     * the constructor. The mirror is a rebuildable, disposable catalog and must
     * not share backups or the migration chain with the user's library. Created
     * from docker/database/mirror_schema.sql by docker-compose.mirror.yml.
     */
    public function getMirrorConnection(): PDO
    {
        return $this->connect($this->mirror);
    }

    /**
     * Get a mirror connection for the importer CLI only.
     *
     * A separate MySQL user because LOAD DATA INFILE requires the FILE
     * privilege, and FILE cannot be scoped to a schema: it is GRANT ... ON *.*
     * or nothing, and it lets its holder read any file the server can read.
     * Giving that to the web application's user would widen what a SQL
     * injection on any endpoint could reach. Created by
     * `./mirror-sync.sh --bootstrap`.
     */
    public function getMirrorImportConnection(): PDO
    {
        // Union de arrays: lo de la izquierda gana, así que solo cambian las
        // credenciales y el resto (host, puerto, esquema) sigue siendo el del
        // mirror. Importa desde que el mirror puede estar en otro servidor.
        return $this->connect([
            'username' => $_ENV['DB_MIRROR_IMPORT_USER'] ?? 'library_mirror_importer',
            'password' => $_ENV['DB_MIRROR_IMPORT_PASSWORD'] ?? '',
        ] + $this->mirror);
    }

    /**
     * Establish database connection
     *
     * Recibe la config entera, no solo el esquema: desde que el mirror puede
     * estar en otro servidor, host y puerto también varían según a quién se
     * conecte, y leerlos de $this->config mandaba la conexión del mirror al
     * MySQL de la app sin decir nada.
     *
     * @param array{host:string,port:string,database:string,username:string,password:string,charset:string} $cfg
     * @return PDO New database connection
     */
    private function connect(array $cfg): PDO
    {
        $database = $cfg['database'];
        $username = $cfg['username'];
        $password = $cfg['password'];

        $dsn = sprintf(
            "mysql:host=%s;port=%s;dbname=%s;charset=%s",
            $cfg['host'],
            $cfg['port'],
            $database,
            $cfg['charset']
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO(
                $dsn, 
                $username, 
                $password, 
                $options
            );
            
            // Log conexión exitosa
            if (function_exists('logger')) {
                try {
                    logger('database')->info('Database connection established', [
                        'host' => $cfg['host'],
                        'port' => $cfg['port'],
                        'database' => $database,
                        'charset' => $cfg['charset']
                    ]);
                } catch (\Throwable $e) {
                    error_log("Logging error in DatabaseConnector: " . $e->getMessage());
                }
            }
            
            return $pdo;
            
        } catch (PDOException $e) {
            // Log error de conexión
            if (function_exists('logger')) {
                try {
                    logger('database')->error('Database connection failed', [
                        'host' => $cfg['host'],
                        'port' => $cfg['port'],
                        'database' => $database,
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

    /**
     * Reset connection (útil para testing)
     */
    public function resetConnection(): void
    {
        $this->pdoInstance = null;
    }

    /**
     * Get configuration for debugging purposes
     */
    public function getConfig(): array
    {
        // Return config without sensitive data
        return [
            'host' => $this->config['host'],
            'port' => $this->config['port'],
            'database' => $this->config['database'],
            'charset' => $this->config['charset']
        ];
    }
} 