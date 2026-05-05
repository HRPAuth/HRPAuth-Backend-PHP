<?php

namespace App\controllers;

class TOTPController {
    public function generate() {
        header('Content-Type: text/plain; charset=utf-8');
        
        // Get secret from GET
        if (!isset($_GET['secret']) || empty($_GET['secret'])) {
            http_response_code(400);
            echo "Missing secret";
            exit;
        }
        
        $secret = $_GET['secret'];
        
        echo $this->generate_totp($secret);
    }
    
    private function base32_decode($encoded) {
        $base32_chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $encoded = strtoupper(trim($encoded));
        $encoded = str_replace(['=', ' ', '-'], '', $encoded);
        
        $decoded = '';
        $buffer = 0;
        $bits_left = 0;
        
        for ($i = 0; $i < strlen($encoded); $i++) {
            $char = $encoded[$i];
            $value = strpos($base32_chars, $char);
            if ($value === false) continue;
            
            $buffer = ($buffer << 5) | $value;
            $bits_left += 5;
            
            if ($bits_left >= 8) {
                $bits_left -= 8;
                $decoded .= chr(($buffer >> $bits_left) & 0xFF);
            }
        }
        
        return $decoded;
    }

    private function generate_totp($secret, $digits = 6, $period = 30)
    {
        $counter = floor(time() / $period);
        $binary_counter = pack('N*', 0) . pack('N*', $counter);
        $secret_bytes = $this->base32_decode($secret);

        $hash = hash_hmac('sha1', $binary_counter, $secret_bytes, true);

        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $truncated_hash = substr($hash, $offset, 4);

        $value = unpack('N', $truncated_hash)[1];
        $value = $value & 0x7FFFFFFF;

        $otp = $value % pow(10, $digits);

        return str_pad($otp, $digits, '0', STR_PAD_LEFT);
    }

    public function setupTOTP() {
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

        $email = trim($input['email'] ?? '');
        $remtoken = $input['remtoken'] ?? '';

        if (empty($email) || empty($remtoken)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Missing email or remtoken'
            ]);
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid email'
            ]);
            exit;
        }

        try {
            $pdo = getPDO();
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Database connection error'
            ]);
            exit;
        }

        $stmt = $pdo->prepare('SELECT uid FROM users WHERE email = ? AND remember_token = ? LIMIT 1');
        $stmt->execute([$email, $remtoken]);
        $user = $stmt->fetch();

        if (!$user) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid email or remtoken'
            ]);
            exit;
        }

        $secret = $this->generate_secret(32);
        $update = $pdo->prepare('UPDATE users SET totp = ? WHERE uid = ?');
        $update->execute([$secret, $user['uid']]);

        echo json_encode([
            'success' => true,
            'totpkey' => $secret
        ]);
    }

    private function generate_secret($length = 20) {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= $characters[random_int(0, strlen($characters) - 1)];
        }
        return $secret;
    }

    public function verifyTOTP() {
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

        $email = trim($input['email'] ?? '');
        $passcode = $input['passcode'] ?? '';

        if (empty($email) || empty($passcode)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Missing email or passcode'
            ]);
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid email'
            ]);
            exit;
        }

        try {
            $pdo = getPDO();
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Database connection error'
            ]);
            exit;
        }

        $stmt = $pdo->prepare('SELECT uid, totp, remember_token FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || empty($user['totp'])) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'User not found or TOTP not configured'
            ]);
            exit;
        }

        $secret = $user['totp'];
        $expected = $this->generate_totp($secret);

        if ($passcode !== $expected) {
            $period = 30;
            $counterPrev = floor(time() / $period) - 1;
            $binary_counter_prev = pack('N*', 0) . pack('N*', $counterPrev);
            $secret_bytes = $this->base32_decode($secret);
            $hash_prev = hash_hmac('sha1', $binary_counter_prev, $secret_bytes, true);
            $offset = ord(substr($hash_prev, -1)) & 0x0F;
            $truncated_hash_prev = substr($hash_prev, $offset, 4);
            $value_prev = unpack('N', $truncated_hash_prev)[1];
            $value_prev = $value_prev & 0x7FFFFFFF;
            $otp_prev = str_pad($value_prev % pow(10, 6), 6, '0', STR_PAD_LEFT);

            if ($passcode !== $otp_prev) {
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid passcode'
                ]);
                exit;
            }
        }

        $rt = $user['remember_token'];
        if (empty($rt)) {
            $rt = bin2hex(random_bytes(32));
            $update = $pdo->prepare('UPDATE users SET remember_token = ? WHERE uid = ?');
            $update->execute([$rt, $user['uid']]);
        }

        echo json_encode([
            'success' => true,
            'email' => $email,
            'rt' => $rt
        ]);
    }
}