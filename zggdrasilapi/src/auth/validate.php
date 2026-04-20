<?php

// Validate endpoint
// POST /authserver/validate

$request = getRequestBody();

// Validate request
if (!isset($request['accessToken'])) {
    sendErrorResponse('ForbiddenOperationException', 'Invalid token.');
}

$accessToken = $request['accessToken'];
$clientToken = isset($request['clientToken']) ? $request['clientToken'] : null;

$db = Database::getInstance();

// Check if token exists and is valid
$stmt = $db->query('SELECT * FROM tokens WHERE access_token = ? AND state = ?', [$accessToken, 'valid']);
$token = $stmt->fetch();

if (!$token) {
    sendErrorResponse('ForbiddenOperationException', 'Invalid token.');
}

// Validate client token if provided
if ($clientToken && $clientToken !== $token['client_token']) {
    sendErrorResponse('ForbiddenOperationException', 'Invalid client token.');
}

// Token is valid, return 204 No Content
sendNoContentResponse();
