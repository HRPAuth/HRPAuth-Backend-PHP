<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

require_once 'config/db.php';

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
    
    $token = '';
    
    // 1. 优先从 URL 参数获取 token
    if (!empty($_GET['remember_token'])) {
        $token = $_GET['remember_token'];
    }
    // 2. 其次从 Cookie 获取 token
    elseif (!empty($_COOKIE['hrpa_auth'])) {
        $parts = explode('|', $_COOKIE['hrpa_auth'], 2);
        if (count($parts) === 2) {
            $token = $parts[1];
        }
    }
    
    if (empty($token)) {
        sendResponse(false, '未登录或登录已过期');
    }
    
    $stmt = $pdo->prepare('SELECT uid, email, nickname, avatar, verified FROM users WHERE remember_token = ?');
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    
    if (!$user) {
        sendResponse(false, '用户不存在或token无效');
    }
    
    $userData = [
        'email' => $user['email'],
        'nickname' => $user['nickname'],
        'avatar' => $user['avatar'] ?? null,
        'is_verified' => (bool)$user['verified']
    ];
    
    sendResponse(true, '获取用户信息成功', $userData);
    
} catch (PDOException $e) {
    error_log('User info error: ' . $e->getMessage());
    sendResponse(false, '服务器错误');
}