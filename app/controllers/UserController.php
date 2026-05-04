<?php

namespace App\controllers;

class UserController {
    public function getUser() {
        header('Content-Type: application/json; charset=utf-8');
        session_start();

        require_once dirname(__DIR__, 2) . '/config/db.php';

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
            $uid = '';
            $email = '';

            // 1. 从 POST 请求的 JSON 数据中获取参数
            $input = json_decode(file_get_contents('php://input'), true);
            if (!empty($input['remember_token'])) {
                $token = $input['remember_token'];
            }
            if (!empty($input['uid'])) {
                $uid = $input['uid'];
            }
            if (!empty($input['email'])) {
                $email = $input['email'];
            }

            // 2. 从 POST 请求的表单数据中获取参数
            if (empty($token) && !empty($_POST['remember_token'])) {
                $token = $_POST['remember_token'];
            }
            if (empty($uid) && !empty($_POST['uid'])) {
                $uid = $_POST['uid'];
            }
            if (empty($email) && !empty($_POST['email'])) {
                $email = $_POST['email'];
            }

            // 3. 从 URL 参数获取参数（保持向后兼容）
            if (empty($token) && !empty($_GET['remember_token'])) {
                $token = $_GET['remember_token'];
            }
            if (empty($uid) && !empty($_GET['uid'])) {
                $uid = $_GET['uid'];
            }
            if (empty($email) && !empty($_GET['email'])) {
                $email = $_GET['email'];
            }

            if (empty($token)) {
                sendResponse(false, '未登录或登录已过期');
            }

            // 构建查询条件
            $whereClause = 'remember_token = ?';
            $params = [$token];

            if (!empty($uid)) {
                $whereClause .= ' AND uid = ?';
                $params[] = $uid;
            }

            if (!empty($email)) {
                $whereClause .= ' AND email = ?';
                $params[] = $email;
            }

            // 尝试查询用户信息，某些版本可能缺少 avatar 字段
            try {
                $stmt = $pdo->prepare("SELECT uid, email, username, avatar, verified FROM users WHERE $whereClause");
                $stmt->execute($params);
                $user = $stmt->fetch();
            } catch (\PDOException $e) {
                // 回退：尝试不带 avatar 的查询
                error_log('User info query failed, trying without avatar: ' . $e->getMessage());
                $stmt = $pdo->prepare("SELECT uid, email, username, verified FROM users WHERE $whereClause");
                $stmt->execute($params);
                $user = $stmt->fetch();
                if ($user) {
                    $user['avatar'] = null;
                }
            }

            if (!$user) {
                sendResponse(false, '用户不存在或token无效');
            }

            $userData = [
                'uid' => $user['uid'],
                'email' => $user['email'],
                'username' => $user['username'],
                'avatar' => $user['avatar'] ?? null,
                'verified' => (bool)$user['verified']
            ];

            sendResponse(true, '获取用户信息成功', $userData);

        } catch (\PDOException $e) {
            error_log('User info error: ' . $e->getMessage());
            sendResponse(false, '服务器错误');
        }
    }
}

class ChangeUsernameController {
    public function changeUsername() {
        header('Content-Type: application/json; charset=utf-8');
        session_start();

        require_once dirname(__DIR__, 2) . '/config/db.php';

        try {
            $pdo = getPDO();

            $token = '';
            $newUsername = '';

            $input = json_decode(file_get_contents('php://input'), true);
            if (!empty($input['remember_token'])) {
                $token = $input['remember_token'];
            }
            if (!empty($input['username'])) {
                $newUsername = $input['username'];
            }

            if (empty($_POST['remember_token']) && empty($_GET['remember_token'])) {
            } else {
                if (empty($token) && !empty($_POST['remember_token'])) {
                    $token = $_POST['remember_token'];
                }
                if (empty($token) && !empty($_GET['remember_token'])) {
                    $token = $_GET['remember_token'];
                }
            }

            if (empty($token)) {
                echo json_encode(['success' => false, 'message' => '未登录或登录已过期']);
                exit;
            }

            if (empty($newUsername)) {
                echo json_encode(['success' => false, 'message' => '请提供新用户名']);
                exit;
            }

            if (empty($newUsername)) {
                echo json_encode(['success' => false, 'message' => '用户名不能为空']);
                exit;
            }
            if (strlen($newUsername) < 3 || strlen($newUsername) > 16) {
                echo json_encode(['success' => false, 'message' => '用户名长度必须在3-16个字符之间']);
                exit;
            }
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $newUsername)) {
                echo json_encode(['success' => false, 'message' => '用户名只能包含字母、数字和下划线']);
                exit;
            }

            $stmt = $pdo->prepare("SELECT uid, uuid FROM users WHERE remember_token = ?");
            $stmt->execute([$token]);
            $user = $stmt->fetch();

            if (!$user) {
                echo json_encode(['success' => false, 'message' => '用户不存在或token无效']);
                exit;
            }

            $userUid = $user['uid'];

            $stmt = $pdo->prepare("SELECT uid FROM users WHERE username = ? AND uid != ?");
            $stmt->execute([$newUsername, $userUid]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => '该用户名已被使用']);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE users SET username = ? WHERE uid = ?");
            $stmt->execute([$newUsername, $userUid]);

            echo json_encode(['success' => true, 'message' => '用户名修改成功', 'data' => ['username' => $newUsername]]);

        } catch (\PDOException $e) {
            error_log('Change username error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => '服务器错误']);
        }
    }
}