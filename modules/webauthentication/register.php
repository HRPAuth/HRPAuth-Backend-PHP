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

$input = json_decode(file_get_contents('php://input'), true);

$email     = trim($input['email'] ?? '');
$username  = trim($input['username'] ?? '');
$password  = $input['password'] ?? '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email']);
    exit;
}

if (mb_strlen($username) < 3) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Username too short']);
    exit;
}

if (strlen($password) < 6) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Password too short']);
    exit;
}



try {
    $pdo = getPDO();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

// 检查邮箱是否存在
$stmt = $pdo->prepare('SELECT uid FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);

if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Email already registered']);
    exit;
}

// 创建用户
$hash = password_hash($password, PASSWORD_BCRYPT);
$ip   = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$now  = date('Y-m-d H:i:s');
$score = 1000;
$verification_token = bin2hex(random_bytes(16));
$verified = 0;
$uuid = str_replace('-', '', sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
    mt_rand(0, 0xffff), mt_rand(0, 0xffff),
    mt_rand(0, 0xffff),
    mt_rand(0, 0x0fff) | 0x4000,
    mt_rand(0, 0x3fff) | 0x8000,
    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
));

$insert = $pdo->prepare(
    'INSERT INTO users 
    (uuid, email, username, score, password, ip, last_sign_at, register_at, verified, verification_token) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);

$insert->execute([
    $uuid,
    $email,
    $username, 
    $score,
    $hash,
    $ip,
    $now,
    $now,
    $verified,
    $verification_token
]);

// Create default profile for the user
$profileId = str_replace('-', '', sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
    mt_rand(0, 0xffff), mt_rand(0, 0xffff),
    mt_rand(0, 0xffff),
    mt_rand(0, 0x0fff) | 0x4000,
    mt_rand(0, 0x3fff) | 0x8000,
    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
));
$insertProfile = $pdo->prepare(
    'INSERT INTO profiles (id, user_id, name, model) VALUES (?, ?, ?, ?)'
);
$insertProfile->execute([$profileId, $uuid, $username, 'default']);

$uid = $pdo->lastInsertId();

echo json_encode([
    'success' => true,
    'uid' => $uid,
    'message' => 'Register successful'
]);
