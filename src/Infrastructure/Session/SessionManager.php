<?php
declare(strict_types=1);

namespace App\Infrastructure\Session;

use App\Application\Domain\Model\User;

class SessionManager
{
    private const SESSION_NAME = 'LIBRARY_SESSION';
    private const USER_KEY = 'user_data';
    private const CSRF_KEY = 'csrf_token';
    private const SESSION_TIMEOUT = 3600; // 1 hour in seconds
    
    public function __construct()
    {
        $this->startSession();
    }

    private function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            // Configure session settings for security
            ini_set('session.cookie_httponly', '1');
            ini_set('session.cookie_secure', '1'); // Only for HTTPS
            ini_set('session.cookie_samesite', 'Strict');
            ini_set('session.use_strict_mode', '1');
            ini_set('session.gc_maxlifetime', (string)self::SESSION_TIMEOUT);
            
            session_name(self::SESSION_NAME);
            session_start();
            
            // Check session timeout
            if ($this->isSessionExpired()) {
                $this->logout();
                return;
            }
            
            // Regenerate session ID periodically for security
            if (!isset($_SESSION['last_regenerated'])) {
                $this->regenerateSession();
            } elseif (time() - $_SESSION['last_regenerated'] > 300) { // 5 minutes
                $this->regenerateSession();
            }
            
            // Update last activity
            $_SESSION['last_activity'] = time();
        }
    }

    public function login(User $user): void
    {
        $_SESSION[self::USER_KEY] = [
            'id' => $user->getId(),
            'google_id' => $user->getGoogleId(),
            'email' => $user->getEmail(),
            'name' => $user->getName(),
            'picture' => $user->getPicture(),
            'is_active' => $user->isActive()
        ];
        
        $_SESSION['last_activity'] = time();
        $this->generateCSRFToken();
        $this->regenerateSession();
    }

    public function logout(): void
    {
        $_SESSION = [];
        
        // Delete the session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
    }

    public function isLoggedIn(): bool
    {
        return isset($_SESSION[self::USER_KEY]) && 
               !empty($_SESSION[self::USER_KEY]) && 
               !$this->isSessionExpired();
    }

    public function getCurrentUser(): ?array
    {
        if (!$this->isLoggedIn()) {
            return null;
        }
        return $_SESSION[self::USER_KEY] ?? null;
    }

    public function getCurrentUserId(): ?int
    {
        $user = $this->getCurrentUser();
        return $user ? (int)$user['id'] : null;
    }

    public function generateCSRFToken(): string
    {
        if (!isset($_SESSION[self::CSRF_KEY])) {
            $_SESSION[self::CSRF_KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::CSRF_KEY];
    }

    public function validateCSRFToken(string $token): bool
    {
        return isset($_SESSION[self::CSRF_KEY]) && 
               hash_equals($_SESSION[self::CSRF_KEY], $token);
    }

    public function updateUserData(User $user): void
    {
        if ($this->isLoggedIn()) {
            $_SESSION[self::USER_KEY] = [
                'id' => $user->getId(),
                'google_id' => $user->getGoogleId(),
                'email' => $user->getEmail(),
                'name' => $user->getName(),
                'picture' => $user->getPicture(),
                'is_active' => $user->isActive()
            ];
        }
    }

    private function regenerateSession(): void
    {
        session_regenerate_id(true);
        $_SESSION['last_regenerated'] = time();
    }

    private function isSessionExpired(): bool
    {
        if (!isset($_SESSION['last_activity'])) {
            return false;
        }
        
        return (time() - $_SESSION['last_activity']) > self::SESSION_TIMEOUT;
    }

    public function extendSession(): void
    {
        $_SESSION['last_activity'] = time();
    }
}
