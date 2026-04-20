<?php

// HasJoined endpoint
// GET /sessionserver/session/minecraft/hasJoined?username={username}&serverId={serverId}&ip={ip}

// Get parameters
$username = isset($_GET['username']) ? $_GET['username'] : null;
$serverId = isset($_GET['serverId']) ? $_GET['serverId'] : null;
$ip = isset($_GET['ip']) ? $_GET['ip'] : null;
$unsigned = isset($_GET['unsigned']) ? $_GET['unsigned'] === 'true' : false;

// Validate parameters
if (!$username || !$serverId) {
    sendErrorResponse('ForbiddenOperationException', 'Invalid request.');
}

$db = Database::getInstance();

// Get profile by username
$stmt = $db->query('SELECT id, name, model FROM profiles WHERE name = ?', [$username]);
$profile = $stmt->fetch();

if (!$profile) {
    sendNoContentResponse(); // Profile not found, return 204
}

// Check if session exists and is not expired
$sessionQuery = 'SELECT * FROM sessions WHERE profile_id = ? AND server_id = ? AND expires_at > NOW()';
$sessionParams = [$profile['id'], $serverId];

if ($ip) {
    $sessionQuery .= ' AND ip = ?';
    $sessionParams[] = $ip;
}

$stmt = $db->query($sessionQuery, $sessionParams);
$session = $stmt->fetch();

if (!$session) {
    sendNoContentResponse(); // Session not found or expired, return 204
}

// Get profile properties (textures)
$properties = [];
if (!$unsigned) {
    $stmt = $db->query('SELECT name, value, signature FROM profile_properties WHERE profile_id = ?', [$profile['id']]);
    while ($property = $stmt->fetch()) {
        $prop = ['name' => $property['name'], 'value' => $property['value']];
        if ($property['signature']) {
            $prop['signature'] = $property['signature'];
        }
        $properties[] = $prop;
    }
}

// Prepare response
$response = [
    'id' => $profile['id'],
    'name' => $profile['name']
];

if (!empty($properties)) {
    $response['properties'] = $properties;
}

sendJsonResponse($response);
