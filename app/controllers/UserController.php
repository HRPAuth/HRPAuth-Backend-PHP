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

            if (empty($token) && !empty($_POST['remember_token'])) {
                $token = $_POST['remember_token'];
            }
            if (empty($uid) && !empty($_POST['uid'])) {
                $uid = $_POST['uid'];
            }
            if (empty($email) && !empty($_POST['email'])) {
                $email = $_POST['email'];
            }

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

            try {
                $stmt = $pdo->prepare("SELECT uid, email, username, avatar, verified FROM users WHERE $whereClause");
                $stmt->execute($params);
                $user = $stmt->fetch();
            } catch (\PDOException $e) {
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