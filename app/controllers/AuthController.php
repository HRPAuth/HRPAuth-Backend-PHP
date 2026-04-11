<?php

namespace App\controllers;

class AuthController {
    public function login() {
        header('Content-Type: application/json; charset=utf-8');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'message' => 'Method Not Allowed'
            ]);
            exit;
        }
        
        // 支持 application/json
        $input = json_decode(file_get_contents('php://input'), true);
        
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid email'
            ]);
            exit;
        }
        
        $pdo = getPDO();
        $stmt = $pdo->prepare('SELECT uid, password FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($password, $user['password'])) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Email or password incorrect'
            ]);
            exit;
        }
        
        // 生成 token
        $uid = $user['uid'];
        $token = bin2hex(random_bytes(32));
        
        $update = $pdo->prepare('UPDATE users SET remember_token = ? WHERE uid = ?');
        $update->execute([$token, $uid]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'token' => $token,
            'uid' => $uid
        ]);
    }
    
    public function register() {
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
    }
    
    public function logout() {
        // Start session and attempt to remove server-side remember token if present
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!empty($_COOKIE['hrpa_auth'])) {
            $parts = explode('|', $_COOKIE['hrpa_auth'], 2);
            if (count($parts) === 2) {
                [$uid, $token] = $parts;
                try {
                    $pdo = getPDO();
                    $stmt = $pdo->prepare('SELECT remember_token FROM users WHERE uid = ? LIMIT 1');
                    $stmt->execute([$uid]);
                    $row = $stmt->fetch();
                    if ($row && hash_equals($row['remember_token'] ?? '', $token)) {
                        $update = $pdo->prepare('UPDATE users SET remember_token = NULL WHERE uid = ?');
                        $update->execute([$uid]);
                    }
                } catch (Exception $e) {
                    // ignore DB errors while clearing cookies
                }
            }
        }
        
        // Clear all cookies available in the request
        foreach ($_COOKIE as $name => $value) {
            // Clear without domain
            setcookie($name, '', time() - 3600, '/');
            
            // Attempt clearing with host as domain (best-effort)
            if (!empty($_SERVER['HTTP_HOST'])) {
                $host = $_SERVER['HTTP_HOST'];
                // strip port if present
                $host = preg_replace('/:\d+$/', '', $host);
                setcookie($name, '', time() - 3600, '/', $host);
            }
        }
        
        // Clear PHP session cookie as well
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 3600, $params['path'] ?? '/', $params['domain'] ?? '', $params['secure'] ?? false, $params['httponly'] ?? true);
        }
        
        // Clear session data
        $_SESSION = [];
        if (session_status() !== PHP_SESSION_NONE) {
            session_destroy();
        }
        
        // Redirect to login
        header('Location: /login');
        exit;
    }
}