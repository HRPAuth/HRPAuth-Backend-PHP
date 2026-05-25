<?php

require_once __DIR__ . '/../../../../app/services/AuthService.php';

use App\services\AuthService;

$request = getRequestBody();

if (!isset($request['accessToken'])) {
    sendErrorResponse('ForbiddenOperationException', 'Invalid token.');
}

$accessToken = $request['accessToken'];
$clientToken = isset($request['clientToken']) ? $request['clientToken'] : null;
$requestUser = isset($request['requestUser']) ? $request['requestUser'] : false;
$selectedProfile = isset($request['selectedProfile']) ? $request['selectedProfile'] : null;

$authService = new AuthService();

$token = $authService->validateToken($accessToken, $clientToken);

if (!$token) {
    sendErrorResponse('ForbiddenOperationException', 'Invalid token.');
}

if (!$clientToken) {
    $clientToken = $token['client_token'];
}

$profileId = $token['selected_profile_id'];
if ($selectedProfile) {
    if (!isset($selectedProfile['id'])) {
        sendErrorResponse('ForbiddenOperationException', 'Invalid selected profile.');
    }
    
    if (!$authService->isProfileOwnedByUser($selectedProfile['id'], $token['user_id'])) {
        sendErrorResponse('ForbiddenOperationException', 'Invalid selected profile.');
    }
    
    $profileId = $selectedProfile['id'];
}

$profile = $authService->getProfileById($profileId);

if (!$profile) {
    sendErrorResponse('ForbiddenOperationException', 'Selected profile not found.');
}

$profileData = ['id' => $profile['id'], 'name' => $profile['username']];
if ($profile['model']) {
    $profileData['model'] = $profile['model'];
}

$userProperties = [];
if ($requestUser && $token['locale']) {
    $userProperties[] = ['name' => 'locale', 'value' => $token['locale']];
}

$newAccessToken = generateAccessToken();

$authService->invalidateToken($accessToken);

$config = require __DIR__ . '/../../../../config/zggdrasilapi.php';

if (!$authService->createToken($newAccessToken, $clientToken, $token['user_id'], $profileId, $config['security']['token_expiry_days'])) {
    sendErrorResponse('ForbiddenOperationException', 'Failed to refresh session. Please try again.');
}

$response = [
    'accessToken' => $newAccessToken,
    'clientToken' => $clientToken,
    'selectedProfile' => $profileData
];

if ($requestUser) {
    $response['user'] = [
        'id' => $token['user_id'],
        'email' => $token['email'],
        'properties' => $userProperties
    ];
}

sendJsonResponse($response);