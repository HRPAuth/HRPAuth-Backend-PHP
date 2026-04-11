<?php

// Authenticate endpoint
// POST /authserver/authenticate

$request = getRequestBody();

// Validate request
if (!isset($request['username']) || !isset($request['password']) || !isset($request['agent'])) {
    sendErrorResponse('ForbiddenOperationException', 'Invalid credentials.');
}

$username = $request['username'];
$password = $request['password'];
$agent = $request['agent'];
$clientToken = isset($request['clientToken']) ? $request['clientToken'] : generateClientToken();
$requestUser = isset($request['requestUser']) ? $request['requestUser'] : false;

// Validate agent
if (!isset($agent['name']) || !isset($agent['version'])) {
    sendErrorResponse('ForbiddenOperationException', 'Invalid agent information.');
}

$db = Database::getInstance();

// Check if user exists
$config = require __DIR__ . '/../../../config/zggdrasilapi.php';
$nonEmailLogin = $config['feature_flags']['non_email_login'];

if ($nonEmailLogin) {
    // Try to find user by email or username
    $stmt = $db->query('SELECT u.uuid, u.email, u.username, u.password FROM users u JOIN profiles p ON u.uuid = p.user_id WHERE u.email = ? OR p.name = ?', [$username, $username]);
    $user = $stmt->fetch();
} else {
    // Only allow email login
    $stmt = $db->query('SELECT uuid, email, username, password FROM users WHERE email = ?', [$username]);
    $user = $stmt->fetch();
}

if (!$user || !verifyPassword($password, $user['password'])) {
    sendErrorResponse('ForbiddenOperationException', 'Invalid credentials.');
}

// Get user properties if requested
$userProperties = [];
if ($requestUser) {
    $stmt = $db->query('SELECT name, value, signature FROM user_properties WHERE user_id = ?', [$user['uuid']]);
    while ($property = $stmt->fetch()) {
        $prop = ['name' => $property['name'], 'value' => $property['value']];
        if ($property['signature']) {
            $prop['signature'] = $property['signature'];
        }
        $userProperties[] = $prop;
    }
}

// Get user profiles
$stmt = $db->query('SELECT id, name, model FROM profiles WHERE user_id = ?', [$user['uuid']]);
$profiles = [];
$selectedProfile = null;

while ($profile = $stmt->fetch()) {
    $profileData = ['id' => $profile['id'], 'name' => $profile['name']];
    if ($profile['model']) {
        $profileData['model'] = $profile['model'];
    }
    $profiles[] = $profileData;
    
    // Set first profile as selected if none selected
    if (!$selectedProfile) {
        $selectedProfile = $profileData;
    }
}

if (empty($profiles)) {
    sendErrorResponse('ForbiddenOperationException', 'User has no profiles.');
}

// Generate access token
$accessToken = generateAccessToken();
$issuedAt = getCurrentTimestamp();

// Insert token into database
$config = require __DIR__ . '/../../../config/zggdrasilapi.php';
$db->query(
    'INSERT INTO tokens (access_token, client_token, user_id, selected_profile_id, issued_at, expires_in_days, state) VALUES (?, ?, ?, ?, ?, ?, ?)',
    [$accessToken, $clientToken, $user['uuid'], $selectedProfile['id'], $issuedAt, $config['security']['token_expiry_days'], 'valid']
);

// Prepare response
$response = [
    'accessToken' => $accessToken,
    'clientToken' => $clientToken,
    'availableProfiles' => $profiles,
    'selectedProfile' => $selectedProfile
];

// Add user info if requested
if ($requestUser) {
    $response['user'] = [
        'id' => $user['uuid'],
        'email' => $user['email'],
        'username' => $user['username'],
        'properties' => $userProperties
    ];
}

sendJsonResponse($response);
