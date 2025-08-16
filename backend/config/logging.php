<?php

declare(strict_types=1);

use Monolog\Logger;

/**
 * Configuración del sistema de logging
 */
return [
    // Canal por defecto
    'default_channel' => env('LOG_CHANNEL', 'app'),

    // Nivel mínimo de logging
    'log_level' => match(env('LOG_LEVEL', 'info')) {
        'debug' => Logger::DEBUG,
        'info' => Logger::INFO,
        'notice' => Logger::NOTICE,
        'warning' => Logger::WARNING,
        'error' => Logger::ERROR,
        'critical' => Logger::CRITICAL,
        'alert' => Logger::ALERT,
        'emergency' => Logger::EMERGENCY,
        default => Logger::INFO
    },

    // Directorio donde se almacenan los logs
    'log_path' => env('LOG_PATH', __DIR__ . '/../storage/logs'),

    // Número máximo de archivos de log a mantener (rotación)
    'max_files' => (int) env('LOG_MAX_FILES', 7),

    // Entorno de ejecución
    'environment' => env('APP_ENV', 'production'),

    // Habilitar logging en consola
    'enable_console' => env('LOG_CONSOLE', false),

    // Habilitar formato JSON
    'enable_json_format' => env('LOG_JSON_FORMAT', true),

    // Configuración de canales específicos
    'channels' => [
        'app' => [
            'level' => env('LOG_LEVEL_APP', 'info'),
            'path' => env('LOG_PATH_APP', 'app.log'),
        ],
        'api' => [
            'level' => env('LOG_LEVEL_API', 'info'),
            'path' => env('LOG_PATH_API', 'api.log'),
        ],
        'database' => [
            'level' => env('LOG_LEVEL_DATABASE', 'info'),
            'path' => env('LOG_PATH_DATABASE', 'database.log'),
        ],
        'auth' => [
            'level' => env('LOG_LEVEL_AUTH', 'info'),
            'path' => env('LOG_PATH_AUTH', 'auth.log'),
        ],
        'security' => [
            'level' => env('LOG_LEVEL_SECURITY', 'warning'),
            'path' => env('LOG_PATH_SECURITY', 'security.log'),
        ],
        'application' => [
            'level' => env('LOG_LEVEL_APPLICATION', 'info'),
            'path' => env('LOG_PATH_APPLICATION', 'application.log'),
        ],
    ],

    // Configuración para diferentes entornos
    'environments' => [
        'development' => [
            'log_level' => Logger::DEBUG,
            'enable_console' => true,
            'enable_json_format' => false,
        ],
        'testing' => [
            'log_level' => Logger::WARNING,
            'enable_console' => false,
            'enable_json_format' => true,
        ],
        'staging' => [
            'log_level' => Logger::INFO,
            'enable_console' => false,
            'enable_json_format' => true,
        ],
        'production' => [
            'log_level' => Logger::INFO,
            'enable_console' => false,
            'enable_json_format' => true,
        ],
    ],
];
