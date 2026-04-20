<?php

// DeleteTexture endpoint
// DELETE /api/user/profile/{uuid}/{textureType}

// Get UUID and texture type from URI
$uri = $_SERVER['REQUEST_URI'];
$pathParts = explode('/', $uri);
$uuid = isset($pathParts[5]) ? $pathParts[5] : null;
$textureType = isset($pathParts[6]) ? $pathParts[6] : null;

// Validate UUID and texture type
if (!$uuid || !isValidUnsignedUUID($uuid)) {
    sendErrorResponse('ForbiddenOperationException', 'Invalid UUID.');
}

if (!$textureType || !in_array($textureType, ['skin', 'cape'])) {
    sendErrorResponse('ForbiddenOperationException', 'Invalid texture type.');
}

// Get authentication token
$authHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : null;
if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
    sendErrorResponse('ForbiddenOperationException', 'Unauthorized.');
}

$accessToken = substr($authHeader, 7);

$db = Database::getInstance();

// Check if token exists and is valid
$stmt = $db->query('SELECT * FROM tokens WHERE access_token = ? AND state = ?', [$accessToken, 'valid']);
$token = $stmt->fetch();

if (!$token) {
    sendErrorResponse('ForbiddenOperationException', 'Invalid token.');
}

// Check if profile belongs to user
$stmt = $db->query('SELECT id FROM profiles WHERE id = ? AND user_id = ?', [$uuid, $token['user_id']]);
if (!$stmt->fetch()) {
    sendErrorResponse('ForbiddenOperationException', 'Profile not found or does not belong to user.');
}

// Get existing texture property
$stmt = $db->query('SELECT id, value FROM profile_properties WHERE profile_id = ? AND name = ?', [$uuid, 'textures']);
$existingProperty = $stmt->fetch();

if ($existingProperty) {
    // Decode the textures payload
    $texturesPayload = json_decode(base64_decode($existingProperty['value']), true);
    
    if (isset($texturesPayload['textures'][strtoupper($textureType)])) {
        // Remove the texture type
        unset($texturesPayload['textures'][strtoupper($textureType)]);
        
        if (empty($texturesPayload['textures'])) {
            // No textures left, delete the property
            $db->query('DELETE FROM profile_properties WHERE id = ?', [$existingProperty['id']]);
        } else {
            // Update the textures payload
            $texturesValue = base64_encode(json_encode($texturesPayload));
            $db->query('UPDATE profile_properties SET value = ? WHERE id = ?', [$texturesValue, $existingProperty['id']]);
        }
    }
}

// Return 204 No Content
sendNoContentResponse();
