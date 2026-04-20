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
    
    // 1. 从 POST 请求的 JSON 数据中获取 token
    $input = json_decode(file_get_contents('php://input'), true);
    if (!empty($input['remember_token'])) {
        $token = $input['remember_token'];
    }
    // 2. 从 POST 请求的表单数据中获取 token
    elseif (!empty($_POST['remember_token'])) {
        $token = $_POST['remember_token'];
    }
    // 3. 从 URL 参数获取 token（保持向后兼容）
    elseif (!empty($_GET['remember_token'])) {
        $token = $_GET['remember_token'];
    }
    
    if (empty($token)) {
        sendResponse(false, '未登录或登录已过期');
    }
    
    // 尝试查询用户信息，某些版本可能缺少 avatar 字段
    try {
        $stmt = $pdo->prepare('SELECT uid, email, nickname, avatar, verified FROM users WHERE remember_token = ?');
        $stmt->execute([$token]);
        $user = $stmt->fetch();
    } catch (PDOException $e) {
        // 回退：尝试不带 avatar 的查询
        error_log('User info query failed, trying without avatar: ' . $e->getMessage());
        $stmt = $pdo->prepare('SELECT uid, email, nickname, verified FROM users WHERE remember_token = ?');
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        if ($user) {
            $user['avatar'] = null;
        }
    }
    
    if (!$user) {
        sendResponse(false, '用户不存在或token无效');
    }
    
    $userData = [
        'email' => $user['email'],
        'nickname' => $user['nickname'],
        'avatar' => $user['avatar'] ?? null,
        'verified' => (bool)$user['verified']
    ];
    
    sendResponse(true, '获取用户信息成功', $userData);
    
} catch (PDOException $e) {
    error_log('User info error: ' . $e->getMessage());
    sendResponse(false, '服务器错误');
}