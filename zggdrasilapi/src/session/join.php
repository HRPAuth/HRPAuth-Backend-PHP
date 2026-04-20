<?php

// Join endpoint
// POST /sessionserver/session/minecraft/join

$request = getRequestBody();

// Validate request
if (!isset($request['accessToken']) || !isset($request['selectedProfile']) || !isset($request['serverId'])) {
    sendErrorResponse('ForbiddenOperationException', 'Invalid request.');
}

$accessToken = $request['accessToken'];
$selectedProfile = $request['selectedProfile'];
$serverId = $request['serverId'];

$db = Database::getInstance();

// Check if token exists and is valid
$stmt = $db->query('SELECT * FROM tokens WHERE access_token = ? AND state = ?', [$accessToken, 'valid']);
$token = $stmt->fetch();

if (!$token) {
    sendErrorResponse('ForbiddenOperationException', 'Invalid token.');
}

// Validate profile
if ($selectedProfile !== $token['selected_profile_id']) {
    // Check if profile belongs to user
    $stmt = $db->query('SELECT id FROM profiles WHERE id = ? AND user_id = ?', [$selectedProfile, $token['user_id']]);
    if (!$stmt->fetch()) {
        sendErrorResponse('ForbiddenOperationException', 'Invalid profile.');
    }
}

// Get client IP
$ip = getClientIP();

// Insert session
$config = require __DIR__ . '/../../../config/zggdrasilapi.php';
$db->query(
    'INSERT INTO sessions (profile_id, server_id, ip, expires_at) VALUES (?, ?, ?, NOW() + INTERVAL ? SECOND)',
    [$selectedProfile, $serverId, $ip, $config['security']['session_expiry_seconds']]
);

// Return 204 No Content
sendNoContentResponse();
