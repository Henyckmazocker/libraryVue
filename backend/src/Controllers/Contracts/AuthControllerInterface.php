<?php
namespace App\Controllers\Contracts;

interface AuthControllerInterface
{
    public function login(array $inputData);
    public function logout();
    /** Acepta sesión PHP o el `user_id` que deja AuthenticationMiddleware con un Bearer JWT. */
    public function checkAuth(?int $userId = null, ?string $authMethod = null);
    public function updateProfile(array $inputData);
    public function logFrontend(array $logData);
    public function logFrontendBatch(array $logs);
}
