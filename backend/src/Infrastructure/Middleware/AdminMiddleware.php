<?php

declare(strict_types=1);

namespace App\Infrastructure\Middleware;

use App\Domain\Repository\User\UserRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Admin Authorization Middleware
 * Verifies that the authenticated user holds the administrator flag (users.is_admin).
 * Must run *after* AuthenticationMiddleware, which is what sets $request['user_id'].
 */
class AdminMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly UserRepositoryInterface $userRepository
    ) {}

    public function handle(array $request, callable $next): array
    {
        $userId = $request['user_id'] ?? null;

        if ($userId === null) {
            // Ruta mal declarada: AdminMiddleware sin AuthenticationMiddleware delante.
            $this->logger->warning('Admin check without authenticated user', [
                'action' => $request['action'] ?? 'unknown',
            ]);

            return $this->denied();
        }

        $user = $this->userRepository->findById((int) $userId);

        if ($user === null || !$user->isAdmin()) {
            $this->logger->warning('Admin access denied', [
                'user_id' => $userId,
                'email'   => $user?->getEmail()->toString(),
                'action'  => $request['action'] ?? 'unknown',
                'ip'      => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            ]);

            return $this->denied();
        }

        // Los controllers reciben el usuario por $request['user'] (ActionRouter.php:503-504) y
        // hasta ahora nadie escribia esa clave: AuthenticationMiddleware solo pone 'user_id' y
        // 'auth_method'. Rellenarla aqui mantiene intactas la LibraryXControllerInterface y el
        // match() del router.
        $request['user'] = $user->toArray();

        return $next($request);
    }

    /**
     * El 403 va en 'http_code' y no en 'code': Application.php:124 solo lee 'http_code', asi que
     * un 'code' saldria por HTTP como 400.
     */
    private function denied(): array
    {
        return [
            'status'    => 'error',
            'message'   => 'Access denied',
            'http_code' => 403,
        ];
    }
}
