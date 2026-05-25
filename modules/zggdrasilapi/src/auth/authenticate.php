<?php

require_once __DIR__ . '/../../../../app/services/AuthService.php';

use App\services\AuthService;

$request = getRequestBody();

if (!isset($request['username']) || !isset($request['password']) || !isset($request['agent'])) {
    sendErrorResponse('ForbiddenOperationException', 'Invalid credentials.');
}

$username = $request['username'];
$password = $request['password'];
$agent = $request['agent'];
$clientToken = isset($request['clientToken']) ? $request['clientToken'] : generateClientToken();
$requestUser = isset($request['requestUser']) ? $request['requestUser'] : false;

if (!isset($agent['name']) || !isset($agent['version'])) {
    sendErrorResponse('ForbiddenOperationException', 'Invalid agent information.');
}

$authService = new AuthService();

$user = $authService->verifyCredentials($username, $password);

if (!$user) {
    sendErrorResponse('ForbiddenOperationException', 'Invalid credentials.');
}

$userProperties = [];
if ($requestUser && $user['locale']) {
    $userProperties[] = ['name' => 'locale', 'value' => $user['locale']];
}

$profiles = $authService->getUserProfiles($user['uuid']);

if (empty($profiles)) {
    sendErrorResponse('ForbiddenOperationException', 'User has no profiles.');
}

$selectedProfile = $profiles[0];

$accessToken = generateAccessToken();

$config = require __DIR__ . '/../../../../config/zggdrasilapi.php';

if (!$authService->createToken($accessToken, $clientToken, $user['uuid'], $selectedProfile['id'], $config['security']['token_expiry_days'])) {
    sendErrorResponse('ForbiddenOperationException', 'Failed to create session. Please try again.');
}

$response = [
    'accessToken' => $accessToken,
    'clientToken' => $clientToken,
    'availableProfiles' => $profiles,
    'selectedProfile' => $selectedProfile
];

if ($requestUser) {
    $response['user'] = [
        'id' => $user['uuid'],
        'email' => $user['email'],
        'username' => $user['username'],
        'properties' => $userProperties
    ];
}

sendJsonResponse($response);