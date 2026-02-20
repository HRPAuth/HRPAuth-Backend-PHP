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
$nickname  = trim($input['nickname'] ?? '');
$password  = $input['password'] ?? '';
$password2 = $input['password2'] ?? '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email']);
    exit;
}

if (mb_strlen($nickname) < 3) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Nickname too short']);
    exit;
}

if (strlen($password) < 6) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Password too short']);
    exit;
}

if ($password !== $password2) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Passwords not match']);
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

$insert = $pdo->prepare(
    'INSERT INTO users 
    (email, nickname, realname, username, score, password, ip, last_sign_at, register_at, verified, verification_token) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);

$insert->execute([
    $email,
    $nickname,   // nickname
    $nickname,   // realname
    $nickname,   // username
    $score,
    $hash,
    $ip,
    $now,
    $now,
    $verified,
    $verification_token
]);

$uid = $pdo->lastInsertId();

echo json_encode([
    'success' => true,
    'uid' => $uid,
    'message' => 'Register successful'
]);
