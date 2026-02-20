<?php
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

// 支持 application/json
$input = json_decode(file_get_contents('php://input'), true);

$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid email'
    ]);
    exit;
}

$pdo = getPDO();
$stmt = $pdo->prepare('SELECT uid, password FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Email or password incorrect'
    ]);
    exit;
}

// 生成 token
$uid = $user['uid'];
$token = bin2hex(random_bytes(32));

$update = $pdo->prepare('UPDATE users SET remember_token = ? WHERE uid = ?');
$update->execute([$token, $uid]);

echo json_encode([
    'success' => true,
    'message' => 'Login successful',
    'token' => $token,
    'uid' => $uid
]);
