<?php

// Simular datos de entrada para el ping
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/test';
$_POST = [];

// Simular la entrada JSON
$jsonInput = json_encode(['action' => 'ping']);
file_put_contents('php://input', $jsonInput);

// Ejecutar el código de index.php
ob_start();
include 'public/index.php';
$output = ob_get_clean();

echo "Output: " . $output . "\n";
