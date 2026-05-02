<?php
require_once __DIR__ . '/config/db.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get token from various sources
$token = '';

// 1. 从 POST 请求的 JSON 数据中获取 token
$input = json_decode(file_get_contents('php://input'), true);
if (!empty($input['remember_token'])) {
    $token = $input['remember_token'];
}
// 2. 从 POST 请求的表单数据中获取 token
elseif (!empty($_POST['remember_token'])) {
    $token = $_POST['remember_token'];
}
// 3. 从 URL 参数获取 token
elseif (!empty($_GET['remember_token'])) {
    $token = $_GET['remember_token'];
}

// Clear remember_token from database if present
if (!empty($token)) {
    try {
        $pdo = getPDO();
        $stmt = $pdo->prepare('UPDATE users SET remember_token = NULL WHERE remember_token = ?');
        $stmt->execute([$token]);
    } catch (Exception $e) {
        // ignore DB errors
    }
}

// Clear session data
$_SESSION = [];
if (session_status() !== PHP_SESSION_NONE) {
    session_destroy();
}

// Redirect to login
header('Location: /login');
exit;
