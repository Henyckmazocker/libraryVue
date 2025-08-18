<?php
namespace App\Controllers\Contracts;

interface AuthControllerInterface
{
    public function login(array $inputData);
    public function logout();
    public function checkAuth();
    public function logFrontend(array $logData);
}
