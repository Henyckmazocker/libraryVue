<?php
namespace App\Controllers;

use App\Domain\UseCases\Auth\LoginUserUseCase;
use App\Infrastructure\Session\SessionManager;
use App\Infrastructure\Middleware\AuthMiddleware;

class AuthController extends BaseController implements Contracts\AuthControllerInterface
{
    private LoginUserUseCase $loginUserUseCase;
    private SessionManager $sessionManager;
    private AuthMiddleware $authMiddleware;

    public function __construct(
        LoginUserUseCase $loginUserUseCase,
        SessionManager $sessionManager,
        AuthMiddleware $authMiddleware
    ) {
        $this->loginUserUseCase = $loginUserUseCase;
        $this->sessionManager = $sessionManager;
        $this->authMiddleware = $authMiddleware;
    }

    public function login(array $inputData): array
    {
        if (!isset($inputData['google_token']) || !is_string($inputData['google_token'])) {
            throw new \InvalidArgumentException('Google token is required for login.');
        }
        
        // TEMPORAL: Simple verification of Google JWT token header
        // This will be replaced with Google Client library verification later
        $tokenParts = explode('.', $inputData['google_token']);
        if (count($tokenParts) !== 3) {
            throw new \InvalidArgumentException('Invalid Google token format.');
        }
        
        $header = json_decode(base64_decode($tokenParts[0]), true);
        $payload = json_decode(base64_decode($tokenParts[1]), true);
        
        if (!$payload || !isset($payload['sub'], $payload['email'], $payload['name'])) {
            throw new \InvalidArgumentException('Invalid Google token payload.');
        }
        
        // For now, we'll accept the payload without cryptographic verification
        // In production, you MUST verify the signature with Google's public keys
        
        $user = $this->loginUserUseCase->execute($payload);
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
    public function handleRequest(string $method, string $path): void
    {
        try {
            $inputData = json_decode(file_get_contents('php://input'), true) ?? [];
            $action = $inputData['action'] ?? $_REQUEST['action'] ?? null;
            
            $response = match ($action) {
                'login' => $this->login($inputData),
                'logout' => $this->logout(),
                'check_auth' => $this->checkAuth(),
                'log_frontend' => $this->logFrontend($inputData['log_data'] ?? []),
                default => $this->errorResponse('Invalid auth action: ' . $action)
            };
            
            $statusCode = $response['status'] === 'success' ? 200 : 400;
            http_response_code($statusCode);
            header('Content-Type: application/json');
            echo json_encode($response, JSON_PRETTY_PRINT);
            exit(); // Asegurar que la respuesta termine aquí
            
        } catch (\Throwable $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'error',
                'message' => 'Internal server error: ' . $e->getMessage()
            ], JSON_PRETTY_PRINT);
            exit(); // Asegurar que la respuesta termine aquí
        }
    }
}
