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
        
        // Support application/json
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
        $stmt = $pdo->prepare('SELECT uid, password, totp FROM users WHERE email = ? LIMIT 1');
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
        
        // Generate token
        $uid = $user['uid'];
        $token = bin2hex(random_bytes(32));
        
        $update = $pdo->prepare('UPDATE users SET remember_token = ? WHERE uid = ?');
        $update->execute([$token, $uid]);
        
        $totp = !empty($user['totp']) ? 1 : 0;
        
        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'token' => $token,
            'uid' => $uid,
            'totp' => $totp
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
        $username  = trim($input['username'] ?? '');
        $password  = $input['password'] ?? '';
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid email']);
            exit;
        }
        
        if (mb_strlen($username) < 3) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Username too short']);
            exit;
        }
        
        if (strlen($password) < 6) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Password too short']);
            exit;
        }
        
        
        
        try {
            $pdo = getPDO();
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error']);
            exit;
        }
        
        // Check if email already exists
        $stmt = $pdo->prepare('SELECT uid FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Email already registered']);
            exit;
        }

        // Check if username already exists
        $stmt = $pdo->prepare('SELECT uid FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);

        if ($stmt->fetch()) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Username already taken']);
            exit;
        }

        // Create user
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $ip   = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $now  = date('Y-m-d H:i:s');
        $score = 1000;
        $verification_token = bin2hex(random_bytes(16));
        $verified = 0;
        $uuid = generateUnsignedUUID();

        $stmt = $pdo->prepare('SELECT MAX(uid) as max_uid FROM users');
        $stmt->execute();
        $result = $stmt->fetch();
        $new_uid = ($result['max_uid'] ?? 0) + 1;

        $insert = $pdo->prepare(
            'INSERT INTO users
            (uid, uuid, email, username, password, ip, last_sign_at, register_at, verified, verification_token)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $insert->execute([
            $new_uid,
            $uuid,
            $email,
            $username,
            $hash,
            $ip,
            $now,
            $now,
            $verified,
            $verification_token
        ]);

        // Create default profile for the user
        $profileId = generateUnsignedUUID();
        $insertProfile = $pdo->prepare(
            'INSERT INTO profiles (id, user_id, name, model) VALUES (?, ?, ?, ?)'
        );
        $insertProfile->execute([$profileId, $uuid, $username, 'default']);

        $uid = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'uid' => $uid,
            'message' => 'Register successful'
        ]);
    }
    
    public function logout() {
        // Start session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Get token from various sources
        $token = '';
        
        // 1. Get token from POST JSON data
        $input = json_decode(file_get_contents('php://input'), true);
        if (!empty($input['remember_token'])) {
            $token = $input['remember_token'];
        }
        // 2. Get token from POST form data
        elseif (!empty($_POST['remember_token'])) {
            $token = $_POST['remember_token'];
        }
        // 3. Get token from URL query params
        elseif (!empty($_GET['remember_token'])) {
            $token = $_GET['remember_token'];
        }
        
        // Clear remember_token from database if present
        if (!empty($token)) {
            try {
                $pdo = getPDO();
                $stmt = $pdo->prepare('UPDATE users SET remember_token = NULL WHERE remember_token = ?');
                $stmt->execute([$token]);
            } catch (\Exception $e) {
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
    }
}