<?php

// Refresh endpoint
// POST /authserver/refresh

$request = getRequestBody();

// Validate request
if (!isset($request['accessToken'])) {
    sendErrorResponse('ForbiddenOperationException', 'Invalid token.');
}

$accessToken = $request['accessToken'];
$clientToken = isset($request['clientToken']) ? $request['clientToken'] : null;
$requestUser = isset($request['requestUser']) ? $request['requestUser'] : false;
$selectedProfile = isset($request['selectedProfile']) ? $request['selectedProfile'] : null;

$db = Database::getInstance();

// Check if token exists and is valid
$stmt = $db->query('SELECT t.*, u.uuid as user_id, u.email FROM tokens t JOIN users u ON t.user_id = u.uuid WHERE t.access_token = ? AND t.state = ?', [$accessToken, 'valid']);
$token = $stmt->fetch();

if (!$token) {
    sendErrorResponse('ForbiddenOperationException', 'Invalid token.');
}

// Validate client token if provided
if ($clientToken && $clientToken !== $token['client_token']) {
    sendErrorResponse('ForbiddenOperationException', 'Invalid client token.');
}

// Use existing client token if none provided
if (!$clientToken) {
    $clientToken = $token['client_token'];
}

// Validate selected profile if provided
$profileId = $token['selected_profile_id'];
if ($selectedProfile) {
    if (!isset($selectedProfile['id'])) {
        sendErrorResponse('ForbiddenOperationException', 'Invalid selected profile.');
    }
    
    // Check if profile belongs to user
    $stmt = $db->query('SELECT id FROM profiles WHERE id = ? AND user_id = ?', [$selectedProfile['id'], $token['user_id']]);
    if (!$stmt->fetch()) {
        sendErrorResponse('ForbiddenOperationException', 'Invalid selected profile.');
    }
    
    $profileId = $selectedProfile['id'];
}

// Get selected profile details
$stmt = $db->query('SELECT id, name, model FROM profiles WHERE id = ?', [$profileId]);
$profile = $stmt->fetch();

if (!$profile) {
    sendErrorResponse('ForbiddenOperationException', 'Selected profile not found.');
}

$profileData = ['id' => $profile['id'], 'name' => $profile['name']];
if ($profile['model']) {
    $profileData['model'] = $profile['model'];
}

// Get user properties if requested
$userProperties = [];
if ($requestUser) {
    $stmt = $db->query('SELECT name, value, signature FROM user_properties WHERE user_id = ?', [$token['user_id']]);
    while ($property = $stmt->fetch()) {
        $prop = ['name' => $property['name'], 'value' => $property['value']];
        if ($property['signature']) {
            $prop['signature'] = $property['signature'];
        }
        $userProperties[] = $prop;
    }
}

// Generate new access token
$newAccessToken = generateAccessToken();
$issuedAt = getCurrentTimestamp();

// Invalidate old token
$db->query('UPDATE tokens SET state = ? WHERE access_token = ?', ['invalid', $accessToken]);

// Insert new token
$config = require __DIR__ . '/../../../config/zggdrasilapi.php';
$db->query(
    'INSERT INTO tokens (access_token, client_token, user_id, selected_profile_id, issued_at, expires_in_days, state) VALUES (?, ?, ?, ?, ?, ?, ?)',
    [$newAccessToken, $clientToken, $token['user_id'], $profileId, $issuedAt, $config['security']['token_expiry_days'], 'valid']
);

// Prepare response
$response = [
    'accessToken' => $newAccessToken,
    'clientToken' => $clientToken,
    'selectedProfile' => $profileData
];

// Add user info if requested
if ($requestUser) {
    $response['user'] = [
        'id' => $token['user_id'],
        'email' => $token['email'],
        'properties' => $userProperties
    ];
}

sendJsonResponse($response);
