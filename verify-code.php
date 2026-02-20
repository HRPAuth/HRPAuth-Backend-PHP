<?php
require_once __DIR__ . '/config/memcache.php';
require_once __DIR__ . '/config/db.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method Not Allowed'
    ]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');
$code = trim($input['code'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email']);
    exit;
}

if (empty($code)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Verification code is required']);
    exit;
}

$storedCode = getVerificationCode($email);

if ($storedCode === false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Verification code expired or not found']);
    exit;
}

if ($code !== $storedCode) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid verification code']);
    exit;
}

deleteVerificationCode($email);

try {
    $pdo = getPDO();
    $stmt = $pdo->prepare('UPDATE users SET verified = 1 WHERE email = ?');
    $stmt->execute([$email]);
    
    $affectedRows = $stmt->rowCount();
    error_log("Verification update: email=$email, affected_rows=$affectedRows");
    
    if ($affectedRows === 0) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'User not found or already verified'
        ]);
        exit;
    }
} catch (Exception $e) {
    http_response_code(500);
    error_log('Failed to update verified status: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update verification status'
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'Verification successful'
]);
