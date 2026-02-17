<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

require_once 'config.php';

function sendResponse($success, $message, $data = null) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

try {
    $pdo = getPDO();
    
    $token = $_COOKIE['auth_token'] ?? '';
    
    if (empty($token)) {
        sendResponse(false, '未登录或登录已过期');
    }
    
    $stmt = $pdo->prepare('SELECT id, email, nickname, avatar FROM users WHERE token = ?');
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    
    if (!$user) {
        sendResponse(false, '用户不存在或token无效');
    }
    
    $userData = [
        'email' => $user['email'],
        'nickname' => $user['nickname'],
        'avatar' => $user['avatar'] ?? null
    ];
    
    sendResponse(true, '获取用户信息成功', $userData);
    
} catch (PDOException $e) {
    error_log('User info error: ' . $e->getMessage());
    sendResponse(false, '服务器错误');
}