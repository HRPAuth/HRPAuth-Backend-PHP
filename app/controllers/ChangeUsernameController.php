<?php

namespace App\controllers;

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