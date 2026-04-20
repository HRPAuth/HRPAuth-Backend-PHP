<?php

// UploadTexture endpoint
// PUT /api/user/profile/{uuid}/{textureType}

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

// Handle file upload
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    sendErrorResponse('ForbiddenOperationException', 'Invalid file upload.');
}

$file = $_FILES['file'];

// Validate file type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if ($mimeType !== 'image/png') {
    sendErrorResponse('ForbiddenOperationException', 'File must be a PNG image.');
}

// Validate file size (100KB limit)
if ($file['size'] > 102400) {
    sendErrorResponse('ForbiddenOperationException', 'File too large.');
}

// Get model for skin
$model = 'default';
if ($textureType === 'skin' && isset($_POST['model']) && $_POST['model'] === 'slim') {
    $model = 'slim';
}

// Generate texture URL (in a real implementation, you would upload to a storage service)
$textureUrl = 'http://auth.samuelchest.com/textures/' . $uuid . '/' . $textureType . '.png';

// Create textures payload
$texturesPayload = [
    'timestamp' => getCurrentTimestamp(),
    'profileId' => $uuid,
    'profileName' => '', // We'll get this from the database
    'textures' => [
        strtoupper($textureType) => [
            'url' => $textureUrl
        ]
    ]
];

if ($textureType === 'skin') {
    $texturesPayload['textures'][strtoupper($textureType)]['metadata'] = [
        'model' => $model
    ];
}

// Get profile name
$stmt = $db->query('SELECT name FROM profiles WHERE id = ?', [$uuid]);
$profile = $stmt->fetch();
if ($profile) {
    $texturesPayload['profileName'] = $profile['name'];
}

// Encode textures payload as base64
$texturesValue = base64_encode(json_encode($texturesPayload));

// Check if texture property already exists
$stmt = $db->query('SELECT id FROM profile_properties WHERE profile_id = ? AND name = ?', [$uuid, 'textures']);
$existingProperty = $stmt->fetch();

if ($existingProperty) {
    // Update existing property
    $db->query('UPDATE profile_properties SET value = ? WHERE id = ?', [$texturesValue, $existingProperty['id']]);
} else {
    // Insert new property
    $db->query('INSERT INTO profile_properties (profile_id, name, value) VALUES (?, ?, ?)', [$uuid, 'textures', $texturesValue]);
}

// Return 204 No Content
sendNoContentResponse();
