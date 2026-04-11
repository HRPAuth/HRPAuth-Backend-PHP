<?php

// BatchProfiles endpoint
// POST /api/profiles/minecraft

$request = getRequestBody();

// Validate request
if (!is_array($request)) {
    sendErrorResponse('ForbiddenOperationException', 'Invalid request.');
}

$usernames = $request;

// Validate usernames
foreach ($usernames as $username) {
    if (!is_string($username) || !isValidUsername($username)) {
        sendErrorResponse('ForbiddenOperationException', 'Invalid username.');
    }
}

// Limit to 100 usernames (Mojang's limit)
if (count($usernames) > 100) {
    sendErrorResponse('ForbiddenOperationException', 'Too many usernames.');
}

$db = Database::getInstance();

// Get profiles by usernames
$placeholders = str_repeat('?,', count($usernames) - 1) . '?';
$stmt = $db->query(
    "SELECT id, name FROM profiles WHERE name IN ($placeholders)",
    $usernames
);

$profiles = [];
while ($profile = $stmt->fetch()) {
    $profiles[] = [
        'id' => $profile['id'],
        'name' => $profile['name']
    ];
}

sendJsonResponse($profiles);
