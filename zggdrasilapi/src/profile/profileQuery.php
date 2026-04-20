<?php

// ProfileQuery endpoint
// GET /sessionserver/session/minecraft/profile/{uuid}?unsigned={unsigned}

// Get UUID from URI
$uri = $_SERVER['REQUEST_URI'];
$pathParts = explode('/', $uri);
$uuid = isset($pathParts[5]) ? $pathParts[5] : null;
$unsigned = isset($_GET['unsigned']) ? $_GET['unsigned'] === 'true' : false;

// Validate UUID
if (!$uuid || !isValidUnsignedUUID($uuid)) {
    sendErrorResponse('ForbiddenOperationException', 'Invalid UUID.');
}

$db = Database::getInstance();

// Get profile by UUID
$stmt = $db->query('SELECT id, name, model FROM profiles WHERE id = ?', [$uuid]);
$profile = $stmt->fetch();

if (!$profile) {
    sendErrorResponse('ForbiddenOperationException', 'Profile not found.');
}

// Get profile properties (textures)
$properties = [];
if (!$unsigned) {
    $stmt = $db->query('SELECT name, value, signature FROM profile_properties WHERE profile_id = ?', [$uuid]);
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
