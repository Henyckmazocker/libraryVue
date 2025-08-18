<?php
declare(strict_types=1);

/**
 * Entry point for the Library Vue Backend API
 * This file initializes the application with dependency injection
 */

// Bootstrap the application with DI container
$app = require_once __DIR__ . '/../bootstrap.php';

// Handle the request using the ApplicationService
$app->handleRequest();