<?php

declare(strict_types=1);

namespace App\Infrastructure\Middleware;

use Psr\Log\LoggerInterface;

/**
 * Authentication Middleware
 * Verifies that user is authenticated before proceeding
 */
class AuthenticationMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {}

    public function handle(array $request, callable $next): array
    {
        // Session is already started in Application::bootstrap()
        // Check if user is authenticated (SessionManager stores in 'user_data')
        if (!isset($_SESSION['user_data']) || !isset($_SESSION['user_data']['id'])) {
            $this->logger->warning('Authentication failed - No user session', [
                'action' => $request['action'] ?? 'unknown',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);

            return [
                'status' => 'error',
                'message' => 'Authentication required. Please log in.',
                'code' => 401
            ];
        }

        // Add user_id to request context for convenience
        $request['user_id'] = $_SESSION['user_data']['id'];

        $this->logger->debug('User authenticated', [
            'user_id' => $_SESSION['user_data']['id'],
            'action' => $request['action'] ?? 'unknown'
        ]);

        // Pass to next middleware
        return $next($request);
    }
}
