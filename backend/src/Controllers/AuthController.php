<?php
namespace App\Controllers;

use App\Domain\UseCases\Auth\LoginUserUseCase;
use App\Infrastructure\Session\SessionManager;
use App\Infrastructure\Middleware\AuthMiddleware;
use App\Infrastructure\Auth\GoogleOAuthVerifier;

class AuthController extends BaseController implements Contracts\AuthControllerInterface
{
    private LoginUserUseCase $loginUserUseCase;
    private SessionManager $sessionManager;
    private AuthMiddleware $authMiddleware;
    private GoogleOAuthVerifier $googleVerifier;

    public function __construct(
        LoginUserUseCase $loginUserUseCase,
        SessionManager $sessionManager,
        AuthMiddleware $authMiddleware,
        GoogleOAuthVerifier $googleVerifier
    ) {
        $this->loginUserUseCase = $loginUserUseCase;
        $this->sessionManager = $sessionManager;
        $this->authMiddleware = $authMiddleware;
        $this->googleVerifier = $googleVerifier;
    }

    public function login(array $inputData): array
    {
        if (!isset($inputData['google_token']) || !is_string($inputData['google_token'])) {
            throw new \InvalidArgumentException('Google token is required for login.');
        }
        
        // Properly verify Google ID token with cryptographic signature validation
        $payload = $this->googleVerifier->verifyToken($inputData['google_token']);
        
        // Create login command from verified payload
        $command = \App\Domain\DTO\Commands\LoginUserCommand::fromGoogleToken($payload);
        $user = $this->loginUserUseCase->execute($command);
        $this->sessionManager->login($user);
        
        return $this->successResponse('Login successful.', [
            'user' => $user->toArray(),
            'csrf_token' => $this->authMiddleware->getCSRFToken()
        ]);
    }

    public function logout(): array
    {
        $this->sessionManager->logout();
        return $this->successResponse('Logout successful.');
    }

    public function checkAuth(): array
    {
        $authResult = $this->authMiddleware->requireAuth();
        if ($authResult['status'] === 'error') {
            return $authResult;
        } else {
            return $this->successResponse('User is authenticated.', [
                'user' => $authResult['user'],
                'csrf_token' => $this->authMiddleware->getCSRFToken()
            ]);
        }
    }

    public function logFrontend(array $logData): array
    {
        if (empty($logData)) {
            return $this->errorResponse('Invalid log data format.');
        }
        
        try {
            // Preserve complete frontend log data
            $context = [
                'frontend_data' => $logData, // Guardar todo el log original del frontend
                'source' => $logData['source'] ?? 'frontend',
                'url' => $logData['url'] ?? 'unknown',
                'userAgent' => $logData['userAgent'] ?? 'unknown',
                'timestamp' => $logData['timestamp'] ?? date('c'),
                'original_message' => $logData['message'] ?? 'Frontend log entry',
                'frontend_level' => $logData['level'] ?? 'info',
                'args' => $logData['data']['args'] ?? [],
                'additional_data' => $logData['data'] ?? []
            ];
            
            // Add user context if authenticated
            if ($this->sessionManager->isLoggedIn()) {
                $context['user_id'] = $this->sessionManager->getCurrentUserId();
                $context['user_email'] = $this->sessionManager->getCurrentUser()['email'] ?? 'unknown';
            }
            
            $message = $logData['message'] ?? 'Frontend log entry';
            $level = $logData['level'] ?? 'info';
            
            // Log to appropriate channel based on level and content
            switch ($level) {
                case 'error':
                    logger('frontend')->error($message, $context);
                    break;
                case 'warn':
                    logger('frontend')->warning($message, $context);
                    break;
                case 'auth':
                    logger('auth')->info($message, $context);
                    break;
                default:
                    logger('frontend')->info($message, $context);
                    break;
            }
            
            return $this->successResponse('Log entry recorded.');
            
        } catch (\Exception $e) {
            // Don't let logging failures break the app
            // Log this error to backend logs
            $this->application->logException($e, 'frontend_logging_error', [
                'endpoint' => 'frontend_log',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? null
            ]);
            
            return $this->errorResponse('Failed to record log entry.', 500);
        }
    }

    /**
     * Handle HTTP request for auth endpoints
     */
}
