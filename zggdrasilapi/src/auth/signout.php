<?php

// Signout endpoint
// POST /authserver/signout

$request = getRequestBody();

// Validate request
if (!isset($request['username']) || !isset($request['password'])) {
    sendErrorResponse('ForbiddenOperationException', 'Invalid credentials.');
}

$username = $request['username'];
$password = $request['password'];

$db = Database::getInstance();

// Check if user exists
$stmt = $db->query('SELECT uuid, password FROM users WHERE email = ?', [$username]);
$user = $stmt->fetch();

if (!$user || !verifyPassword($password, $user['password'])) {
    sendErrorResponse('ForbiddenOperationException', 'Invalid credentials.');
}

// Invalidate all tokens for this user
$db->query('UPDATE tokens SET state = ? WHERE user_id = ?', ['invalid', $user['uuid']]);

// Return 204 No Content
sendNoContentResponse();
