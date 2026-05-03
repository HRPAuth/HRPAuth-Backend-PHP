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

class ChangeProfileNameController {
    public function changeProfileName() {
        header('Content-Type: application/json; charset=utf-8');
        session_start();

        require_once dirname(__DIR__, 2) . '/config/db.php';

        try {
            $pdo = getPDO();

            $token = '';
            $newName = '';

            $input = json_decode(file_get_contents('php://input'), true);
            if (!empty($input['remember_token'])) {
                $token = $input['remember_token'];
            }
            if (!empty($input['name'])) {
                $newName = $input['name'];
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

            if (empty($newName)) {
                echo json_encode(['success' => false, 'message' => '请提供新名称']);
                exit;
            }

            if (empty($newName)) {
                echo json_encode(['success' => false, 'message' => '名称不能为空']);
                exit;
            }
            if (strlen($newName) < 3 || strlen($newName) > 16) {
                echo json_encode(['success' => false, 'message' => '名称长度必须在3-16个字符之间']);
                exit;
            }
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $newName)) {
                echo json_encode(['success' => false, 'message' => '名称只能包含字母、数字和下划线']);
                exit;
            }

            $stmt = $pdo->prepare("SELECT uuid FROM users WHERE remember_token = ?");
            $stmt->execute([$token]);
            $user = $stmt->fetch();

            if (!$user) {
                echo json_encode(['success' => false, 'message' => '用户不存在或token无效']);
                exit;
            }

            $userUuid = $user['uuid'];

            $stmt = $pdo->prepare("SELECT id FROM profiles WHERE user_id = ? LIMIT 1");
            $stmt->execute([$userUuid]);
            $profile = $stmt->fetch();

            if (!$profile) {
                echo json_encode(['success' => false, 'message' => '用户个人资料不存在']);
                exit;
            }

            $profileId = $profile['id'];

            $stmt = $pdo->prepare("SELECT id FROM profiles WHERE name = ? AND id != ?");
            $stmt->execute([$newName, $profileId]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => '该名称已被使用']);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE profiles SET name = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$newName, $profileId]);

            echo json_encode(['success' => true, 'message' => '名称修改成功', 'data' => ['name' => $newName]]);

        } catch (\PDOException $e) {
            error_log('Change profile name error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => '服务器错误']);
        }
    }
}