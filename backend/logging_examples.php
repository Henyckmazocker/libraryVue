<?php

declare(strict_types=1);

/**
 * Ejemplos de uso del sistema de logging estructurado
 * 
 * Este archivo contiene ejemplos de cómo usar el sistema de logging
 * en diferentes partes de la aplicación.
 */

require_once __DIR__ . '/public/bootstrap.php';
require_once __DIR__ . '/vendor/autoload.php';

// Configurar autoloader para las clases de la aplicación
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

echo "=== Ejemplos de Uso del Sistema de Logging ===\n\n";

// 1. Logging básico con funciones de conveniencia
echo "1. Logging básico:\n";
log_info('Aplicación iniciada correctamente');
log_debug('Variables de configuración cargadas', ['env' => 'development']);
log_warning('Memoria alta detectada', ['memory_usage' => '128MB']);
log_error('Error al conectar con servicio externo', ['service' => 'google_books_api']);

// 2. Logging estructurado con contexto
echo "2. Logging con contexto estructurado:\n";
logger('api')->info('Nueva búsqueda de libros realizada', [
    'user_id' => 123,
    'search_term' => 'Harry Potter',
    'results_count' => 15,
    'search_filters' => [
        'author' => 'J.K. Rowling',
        'genre' => 'Fantasy'
    ]
]);

// 3. Logging de requests HTTP
echo "3. Logging de HTTP requests:\n";
logger('api')->httpRequest('POST', '/api/books', [
    'content_length' => 1024,
    'auth_type' => 'google_oauth'
]);

// 4. Logging de respuestas HTTP
echo "4. Logging de HTTP responses:\n";
logger('api')->httpResponse(200, 0.142, [
    'action' => 'add_book',
    'response_size' => 512
]);

// 5. Logging de base de datos
echo "5. Logging de operaciones de base de datos:\n";
logger('database')->database('SELECT', 'books', 0.025, [
    'query' => 'SELECT * FROM books WHERE user_id = ?',
    'params' => [123],
    'result_count' => 42
]);

// 6. Logging de autenticación
echo "6. Logging de autenticación:\n";
logger('auth')->auth('login', '123', true, [
    'provider' => 'google',
    'first_login' => false
]);

logger('auth')->auth('logout', '123', true);

// 7. Logging de eventos de seguridad
echo "7. Logging de seguridad:\n";
logger('security')->security('multiple_failed_login_attempts', 'medium', [
    'attempts_count' => 5,
    'time_window' => '5 minutes',
    'ip_address' => '192.168.1.100'
]);

logger('security')->security('suspicious_file_upload', 'high', [
    'filename' => 'script.php',
    'file_type' => 'text/php',
    'user_id' => 456
]);

// 8. Logging de performance
echo "8. Logging de performance:\n";
logger('application')->performance('book_search', 1.234, [
    'database_queries' => 3,
    'external_api_calls' => 1,
    'cache_hits' => 5,
    'cache_misses' => 2
]);

// 9. Logging de excepciones
echo "9. Logging de excepciones:\n";
try {
    throw new \RuntimeException('Error simulado para demostración');
} catch (\Throwable $e) {
    logger('application')->exception($e, 'Error en procesamiento de datos', [
        'operation' => 'data_import',
        'file' => 'books.csv',
        'line_number' => 42
    ]);
}

// 10. Logging con diferentes canales
echo "10. Logging con diferentes canales:\n";
logger('api')->info('API request processed');
logger('database')->info('Database query executed');
logger('auth')->info('User authenticated');
logger('security')->warning('Rate limit exceeded');
logger('application')->error('Business logic error');

// 11. Logging con helpers específicos
echo "11. Helpers específicos:\n";
$apiLogger = logger('api');
$apiLogger->debug('Request validation started');
$apiLogger->info('Request validated successfully');
$apiLogger->warning('Deprecated API endpoint used');

// 12. Ejemplo de uso en un controlador simulado
echo "12. Ejemplo en controlador:\n";
function simulateBookController() {
    $startTime = microtime(true);
    $logger = logger('api');
    
    $logger->httpRequest('POST', '/api/books/search');
    
    try {
        // Simular lógica del controlador
        $logger->info('Starting book search', ['search_term' => 'PHP']);
        
        // Simular consulta a base de datos
        $dbStart = microtime(true);
        usleep(25000); // Simular 25ms de consulta
        $dbDuration = microtime(true) - $dbStart;
        
        logger('database')->database('SELECT', 'books', $dbDuration, [
            'filters' => ['title' => 'PHP'],
            'limit' => 20
        ]);
        
        $logger->info('Book search completed', [
            'results_found' => 12,
            'search_term' => 'PHP'
        ]);
        
        $duration = microtime(true) - $startTime;
        $logger->httpResponse(200, $duration, [
            'action' => 'book_search',
            'results_count' => 12
        ]);
        
    } catch (\Exception $e) {
        $logger->exception($e, 'Error in book search');
        $logger->httpResponse(500, microtime(true) - $startTime);
    }
}

simulateBookController();

echo "\n=== Logging Examples Completed ===\n";
echo "Revisa los archivos de log en storage/logs/ para ver los resultados.\n";
echo "Los logs se organizan por canal (app.log, api.log, database.log, etc.)\n";
