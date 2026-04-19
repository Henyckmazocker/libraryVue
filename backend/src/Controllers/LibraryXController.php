<?php

namespace App\Controllers;

use App\Controllers\Contracts\LibraryXControllerInterface;
use App\Infrastructure\Middleware\AuthMiddleware;

class LibraryXController extends BaseController implements LibraryXControllerInterface
{
    private const AUTHORIZED_EMAIL = 'david.carvajal.abellan@gmail.com';
    private const URLS_FILE_PATH = __DIR__ . '/../../storage/libraryx-urls.json';
    
    private AuthMiddleware $authMiddleware;

    public function __construct(AuthMiddleware $authMiddleware)
    {
        $this->authMiddleware = $authMiddleware;
    }

    /**
     * Get URLs data for LibraryX
     */
    public function getUrls(array $user): array
    {
        try {
            // Verificar email autorizado
            if ($user['email'] !== self::AUTHORIZED_EMAIL) {
                if (function_exists('logger')) {
                    logger('api')->warning('LibraryX access denied', [
                        'user_email' => $user['email'],
                        'authorized_email' => self::AUTHORIZED_EMAIL
                    ]);
                }
                
                return [
                    'status' => 'error',
                    'message' => 'Access denied',
                    'http_code' => 403
                ];
            }

            // Verificar que existe el archivo
            if (!file_exists(self::URLS_FILE_PATH)) {
                if (function_exists('logger')) {
                    logger('api')->error('LibraryX URLs file not found', [
                        'file_path' => self::URLS_FILE_PATH
                    ]);
                }
                
                return [
                    'status' => 'error',
                    'message' => 'URLs data file not found',
                    'http_code' => 404
                ];
            }

            // Leer y decodificar el archivo JSON
            $jsonContent = file_get_contents(self::URLS_FILE_PATH);
            if ($jsonContent === false) {
                if (function_exists('logger')) {
                    logger('api')->error('Failed to read LibraryX URLs file');
                }
                
                return [
                    'status' => 'error',
                    'message' => 'Failed to read URLs data',
                    'http_code' => 500
                ];
            }

            $urlsData = json_decode($jsonContent, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                if (function_exists('logger')) {
                    logger('api')->error('Invalid JSON in LibraryX URLs file', [
                        'json_error' => json_last_error_msg()
                    ]);
                }
                
                return [
                    'status' => 'error',
                    'message' => 'Invalid URLs data format',
                    'http_code' => 500
                ];
            }

            if (function_exists('logger')) {
                logger('api')->info('LibraryX URLs data retrieved successfully', [
                    'user_email' => $user['email'],
                    'domains_count' => count($urlsData),
                    'total_urls' => array_sum(array_map('count', $urlsData))
                ]);
            }

            return [
                'status' => 'success',
                'data' => $urlsData,
                'http_code' => 200
            ];

        } catch (\Exception $e) {
            if (function_exists('logger')) {
                logger('api')->error('Error retrieving LibraryX URLs data', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }

            return [
                'status' => 'error',
                'message' => 'Internal server error',
                'http_code' => 500
            ];
        }
    }

    /**
     * Update URLs data for LibraryX (for future use)
     */
    public function updateUrls(array $inputData, array $user): array
    {
        try {
            // Verificar email autorizado
            if ($user['email'] !== self::AUTHORIZED_EMAIL) {
                return [
                    'status' => 'error',
                    'message' => 'Access denied',
                    'http_code' => 403
                ];
            }

            $newUrlsData = $inputData['urls_data'] ?? [];

            // Validar estructura de datos
            if (!is_array($newUrlsData)) {
                return [
                    'status' => 'error',
                    'message' => 'URLs data must be an object',
                    'http_code' => 400
                ];
            }

            foreach ($newUrlsData as $domain => $urls) {
                if (!is_string($domain) || !is_array($urls)) {
                    return [
                        'status' => 'error',
                        'message' => 'Invalid data structure',
                        'http_code' => 400
                    ];
                }
            }

            // Crear backup del archivo actual
            if (file_exists(self::URLS_FILE_PATH)) {
                $backupPath = self::URLS_FILE_PATH . '.backup.' . date('Y-m-d-H-i-s');
                copy(self::URLS_FILE_PATH, $backupPath);
            }

            // Guardar nuevos datos
            $jsonData = json_encode($newUrlsData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if (file_put_contents(self::URLS_FILE_PATH, $jsonData) === false) {
                return [
                    'status' => 'error',
                    'message' => 'Failed to save URLs data',
                    'http_code' => 500
                ];
            }

            if (function_exists('logger')) {
                logger('api')->info('LibraryX URLs data updated successfully', [
                    'user_email' => $user['email'],
                    'domains_count' => count($newUrlsData)
                ]);
            }

            return [
                'status' => 'success',
                'message' => 'URLs data updated successfully',
                'http_code' => 200
            ];

        } catch (\Exception $e) {
            if (function_exists('logger')) {
                logger('api')->error('Error updating LibraryX URLs data', [
                    'error' => $e->getMessage()
                ]);
            }

            return [
                'status' => 'error',
                'message' => 'Internal server error',
                'http_code' => 500
            ];
        }
    }

}
