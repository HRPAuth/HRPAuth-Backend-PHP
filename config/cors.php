<?php

// CORS Handler - Include this file at the top of any PHP page to enable CORS

// Define APP_ROOT if not already defined
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

// Load preference configuration
$preference = include APP_ROOT . '/config/preference.php';
$cors = isset($preference['cors']) ? $preference['cors'] : [];
$corsEnabled = isset($cors['enabled']) ? $cors['enabled'] : false;

if ($corsEnabled) {
    $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
    $allowedOrigins = isset($cors['allowed_origins']) ? $cors['allowed_origins'] : [];

    if (in_array('*', $allowedOrigins) || in_array($origin, $allowedOrigins)) {
        header('Access-Control-Allow-Origin: ' . ($origin ?: '*'));
    }

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        $allowedMethods = isset($cors['allowed_methods']) ? implode(', ', $cors['allowed_methods']) : 'GET, POST, PUT, DELETE, OPTIONS';
        $allowedHeaders = isset($cors['allowed_headers']) ? implode(', ', $cors['allowed_headers']) : 'Content-Type, Authorization, X-Requested-With';

        header('Access-Control-Allow-Methods: ' . $allowedMethods);
        header('Access-Control-Allow-Headers: ' . $allowedHeaders);
        header('Access-Control-Max-Age: 86400');
        exit(0);
    }
}