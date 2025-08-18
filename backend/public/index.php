<?php
declare(strict_types=1);

/**
 * Entry point for the Library Vue Backend API
 * This file initializes the application with dependency injection
 */

// Bootstrap the application with DI container
$app = require_once __DIR__ . '/../bootstrap.php';

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Set response content type
header('Content-Type: application/json');

// Handle the request using the ApplicationService
$app->handleRequest();