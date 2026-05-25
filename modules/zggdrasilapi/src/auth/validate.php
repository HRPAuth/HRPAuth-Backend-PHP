<?php

require_once __DIR__ . '/../../../../app/services/AuthService.php';

use App\services\AuthService;

$request = getRequestBody();

if (!isset($request['accessToken'])) {
    sendErrorResponse('ForbiddenOperationException', 'Invalid token.');
}

$accessToken = $request['accessToken'];
$clientToken = isset($request['clientToken']) ? $request['clientToken'] : null;

$authService = new AuthService();

$token = $authService->validateToken($accessToken, $clientToken);

if (!$token) {
    sendErrorResponse('ForbiddenOperationException', 'Invalid token.');
}

sendNoContentResponse();