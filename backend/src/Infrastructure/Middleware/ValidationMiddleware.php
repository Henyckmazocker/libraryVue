<?php

declare(strict_types=1);

namespace App\Infrastructure\Middleware;

use Psr\Log\LoggerInterface;

/**
 * Validation Middleware
 * Validates required fields in request
 */
class ValidationMiddleware implements MiddlewareInterface
{
    private array $requiredFields;

    public function __construct(
        private readonly LoggerInterface $logger,
        array $requiredFields = []
    ) {
        $this->requiredFields = $requiredFields;
    }

    /**
     * Set configuration for this middleware instance
     * 
     * @param array $config Configuration array with 'required' key
     */
    public function setConfig(array $config): void
    {
        if (isset($config['required'])) {
            $this->requiredFields = $config['required'];
        }
    }

    public function handle(array $request, callable $next): array
    {
        // Validate required fields in request data
        $missingFields = [];
        foreach ($this->requiredFields as $field) {
            $data = $request['data'] ?? [];
            if (!isset($data[$field]) || $data[$field] === '') {
                $missingFields[] = $field;
            }
        }

        if (!empty($missingFields)) {
            $this->logger->warning('Validation failed - Missing required fields', [
                'action' => $request['action'] ?? 'unknown',
                'missing_fields' => $missingFields,
                'user_id' => $request['user_id'] ?? 'unknown'
            ]);

            // 'code' simbolico + 'http_code' HTTP: son claves distintas y Application.php:124
            // solo lee la segunda. Aqui el 400 coincide con el valor por defecto, asi que el
            // cambio no altera lo que sale hoy; lo que evita es la trampa del dia que sea un 422.
            return [
                'status' => 'error',
                'message' => 'Missing required fields: ' . implode(', ', $missingFields),
                'code' => 'VALIDATION_FAILED',
                'http_code' => 400
            ];
        }

        // Pass to next middleware
        return $next($request);
    }
}
