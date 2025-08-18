<?php
declare(strict_types=1);

/**
 * Bootstrap file for the Library Vue Backend Application
 * This file initializes the dependency injection container and application services
 */

// Load Composer autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Load environment variables
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue; // Skip comments
        }
        
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        
        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
        }
    }
}

// Set default environment variables
$_ENV['APP_ENV'] = $_ENV['APP_ENV'] ?? 'development';
$_ENV['DB_HOST'] = $_ENV['DB_HOST'] ?? 'mysql';
$_ENV['DB_PORT'] = $_ENV['DB_PORT'] ?? '3306';
$_ENV['DB_DATABASE'] = $_ENV['DB_DATABASE'] ?? 'library_db';
$_ENV['DB_USERNAME'] = $_ENV['DB_USERNAME'] ?? 'library_user';
$_ENV['DB_PASSWORD'] = $_ENV['DB_PASSWORD'] ?? 'library_pass';

// Error reporting configuration
if ($_ENV['APP_ENV'] === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ERROR | E_WARNING | E_PARSE);
    ini_set('display_errors', '0');
}

// Set timezone
date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'UTC');

// Import helper functions
if (file_exists(__DIR__ . '/config/helpers.php')) {
    require_once __DIR__ . '/config/helpers.php';
}

// Initialize logging if available
if (file_exists(__DIR__ . '/config/logging.php')) {
    require_once __DIR__ . '/config/logging.php';
}

// Initialize Application Service
use App\Services\ApplicationService;

try {
    $app = new ApplicationService();
    
    // Store the application instance globally for backward compatibility
    // This will be removed once all code is migrated to use DI
    $GLOBALS['app'] = $app;
    
    return $app;
    
} catch (\Throwable $e) {
    // Handle bootstrap errors
    http_response_code(500);
    header('Content-Type: application/json');
    
    $response = [
        'error' => true,
        'message' => 'Application initialization failed'
    ];
    
    if ($_ENV['APP_ENV'] === 'development') {
        $response['debug'] = [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ];
    }
    
    error_log("Bootstrap Error: " . $e->getMessage());
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit(1);
}
