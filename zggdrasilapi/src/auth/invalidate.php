<?php

// Invalidate endpoint
// POST /authserver/invalidate

$request = getRequestBody();

// Validate request
if (!isset($request['accessToken'])) {
    sendErrorResponse('ForbiddenOperationException', 'Invalid token.');
}

$accessToken = $request['accessToken'];
$clientToken = isset($request['clientToken']) ? $request['clientToken'] : null;

$db = Database::getInstance();

// Check if token exists
$stmt = $db->query('SELECT * FROM tokens WHERE access_token = ?', [$accessToken]);
$token = $stmt->fetch();

if (!$token) {
    sendErrorResponse('ForbiddenOperationException', 'Invalid token.');
}

// Validate client token if provided
if ($clientToken && $clientToken !== $token['client_token']) {
    sendErrorResponse('ForbiddenOperationException', 'Invalid client token.');
}

// Invalidate the token
$db->query('UPDATE tokens SET state = ? WHERE access_token = ?', ['invalid', $accessToken]);

// Return 204 No Content
sendNoContentResponse();
