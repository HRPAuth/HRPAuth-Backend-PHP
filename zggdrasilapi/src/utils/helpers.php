<?php

// Helper functions for Yggdrasil API

// Generate a random access token
function generateAccessToken() {
    return bin2hex(random_bytes(32));
}

// Generate a random client token (UUID v4 without hyphens)
function generateClientToken() {
    return str_replace('-', '', uuidv4());
}

// Generate UUID v4
function uuidv4() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

// Generate unsigned UUID (without hyphens)
function generateUnsignedUUID() {
    return str_replace('-', '', uuidv4());
}

// Get current timestamp in milliseconds
function getCurrentTimestamp() {
    return round(microtime(true) * 1000);
}

// Hash password
function hashPassword($password) {
    $config = require __DIR__ . '/../../../config/zggdrasilapi.php';
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => $config['security']['password_cost']]);
}

// Verify password
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Send JSON response
function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Send error response
function sendErrorResponse($error, $errorMessage, $cause = null, $statusCode = 401) {
    $response = [
        'error' => $error,
        'errorMessage' => $errorMessage
    ];
    
    if ($cause) {
        $response['cause'] = $cause;
    }
    
    sendJsonResponse($response, $statusCode);
}

// Send no content response
function sendNoContentResponse() {
    http_response_code(204);
    exit;
}

// Get request body as JSON
function getRequestBody() {
    $rawInput = file_get_contents('php://input');
    return json_decode($rawInput, true);
}

// Get client IP address
function getClientIP() {
    $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
    foreach ($ipKeys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = $_SERVER[$key];
            // Handle multiple IPs in X-Forwarded-For
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            return $ip;
        }
    }
    return '0.0.0.0';
}

// Validate UUID format (unsigned)
function isValidUnsignedUUID($uuid) {
    return preg_match('/^[0-9a-f]{32}$/i', $uuid);
}

// Validate Minecraft username
function isValidUsername($username) {
    return preg_match('/^[a-zA-Z0-9_]{3,16}$/', $username);
}
