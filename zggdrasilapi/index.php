<?php

// Main entry point for Zggdrasil API Server

require_once __DIR__ . '/src/utils/helpers.php';
require_once __DIR__ . '/src/utils/database.php';

// Helper function for route handling
function handleRoute($method, $expectedMethod, $handler) {
    if ($method === $expectedMethod) {
        require $handler;
    } else {
        sendErrorResponse('Method Not Allowed', 'The method specified is not allowed.', null, 405);
    }
}

// Get the request URI
$uri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

// Remove query string from URI
$uri = explode('?', $uri)[0];

// Handle base path prefix (if API is deployed in subdirectory)
$basePath = '/zggdrasilapi'; // Can be moved to config
if (strpos($uri, $basePath) === 0) {
    $uri = substr($uri, strlen($basePath));
}

// Convert URI to lowercase for case-insensitive matching
$uri = strtolower($uri);

// Check for texture endpoints first (regex match)
if (preg_match('/^\/api\/user\/profile\/[0-9a-f]{32}\/(skin|cape)$/', $uri)) {
    if ($method === 'PUT') {
        require __DIR__ . '/src/texture/uploadTexture.php';
    } elseif ($method === 'DELETE') {
        require __DIR__ . '/src/texture/deleteTexture.php';
    } else {
        sendErrorResponse('Method Not Allowed', 'The method specified is not allowed.', null, 405);
    }
    exit;
}

// Route the request
switch ($uri) {
    // Meta endpoint
    case '/':
        handleRoute($method, 'GET', __DIR__ . '/src/meta.php');
        break;
    
    // Authentication endpoints
    case '/authserver/authenticate':
        handleRoute($method, 'POST', __DIR__ . '/src/auth/authenticate.php');
        break;
    
    case '/authserver/refresh':
        handleRoute($method, 'POST', __DIR__ . '/src/auth/refresh.php');
        break;
    
    case '/authserver/validate':
        handleRoute($method, 'POST', __DIR__ . '/src/auth/validate.php');
        break;
    
    case '/authserver/invalidate':
        handleRoute($method, 'POST', __DIR__ . '/src/auth/invalidate.php');
        break;
    
    case '/authserver/signout':
        handleRoute($method, 'POST', __DIR__ . '/src/auth/signout.php');
        break;
    
    // Session endpoints
    case '/sessionserver/session/minecraft/join':
        handleRoute($method, 'POST', __DIR__ . '/src/session/join.php');
        break;
    
    case '/sessionserver/session/minecraft/hasjoined':
        handleRoute($method, 'GET', __DIR__ . '/src/session/hasJoined.php');
        break;
    
    // Profile endpoints
    case '/sessionserver/session/minecraft/profile':
        handleRoute($method, 'GET', __DIR__ . '/src/profile/profileQuery.php');
        break;
    
    case '/api/profiles/minecraft':
        handleRoute($method, 'POST', __DIR__ . '/src/profile/batchProfiles.php');
        break;
    
    // Handle 404
    default:
        sendErrorResponse('Not Found', 'The requested endpoint does not exist.', null, 404);
        break;
}
