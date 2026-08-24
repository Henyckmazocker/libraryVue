<?php
declare(strict_types=1);

/**
 * Entry point for the Library Vue Backend API
 * This file initializes the application with ActionRouter and Clean Architecture
 */

// Portadas locales: la única excepción al endpoint único, y va ANTES de
// bootstrap.php porque Application emite `Content-Type: application/json` en su
// constructor. Es un GET con cuerpo binario; todo lo demás sigue siendo POST
// con la acción en el body. Ver public/cover.php.
if (isset($_GET['cover'])) {
    require __DIR__ . '/cover.php';
    serveCover((string) $_GET['cover']);
}

// Bootstrap the application
$app = require_once __DIR__ . '/../bootstrap.php';

// Run the application with ActionRouter
$app->run();