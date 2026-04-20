<?php

namespace App\controllers;

class EmailVerificationController {
    public function handle() {
        require_once APP_ROOT . '/config/memcache.php';
        require_once APP_ROOT . '/config/smtp.php';
        require_once APP_ROOT . '/config/db.php';
        
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
        $action = trim($input['action'] ?? '');
        
        switch ($action) {
            case 'send-test-email':
                $this->sendTestEmail($input);
                break;
            
            case 'send-verification-code':
                $this->sendVerificationCode($input);
                break;
            
            case 'verify-code':
                $this->verifyCode($input);
                break;
            
            default:
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid action'
                ]);
                exit;
        }
    }
    
    private function sendSMTPMail($to, $subject, $message, $smtp) {
        $host = $smtp['host'];
        $port = $smtp['port'];
        $from = $smtp['from']['email'];
        $fromName = $smtp['from']['name'];
        
        $socket = @fsockopen($host, $port, $errno, $errstr, 10);
        
        if (!$socket) {
            throw new \Exception("无法连接到 SMTP 服务器: $errstr ($errno)");
        }
        
        function readSMTPResponse($socket) {
            $response = '';
            while ($line = fgets($socket, 515)) {
                $response .= $line;
                if (strlen($line) >= 4 && $line[3] == ' ') {
                    break;
                }
            }
            return $response;
        }
        
        $response = readSMTPResponse($socket);
        if (substr($response, 0, 3) != '220') {
            fclose($socket);
            throw new \Exception("SMTP 服务器未响应: $response");
        }
        
        fputs($socket, "EHLO " . gethostname() . "\r\n");
        $response = readSMTPResponse($socket);
        
        fputs($socket, "MAIL FROM: <$from>\r\n");
        $response = readSMTPResponse($socket);
        if (substr($response, 0, 3) != '250') {
            fclose($socket);
            throw new \Exception("MAIL FROM 失败: $response");
        }
        
        fputs($socket, "RCPT TO: <$to>\r\n");
        $response = readSMTPResponse($socket);
        if (substr($response, 0, 3) != '250') {
            fclose($socket);
            throw new \Exception("RCPT TO 失败: $response");
        }
        
        fputs($socket, "DATA\r\n");
        $response = readSMTPResponse($socket);
        if (substr($response, 0, 3) != '354') {
            fclose($socket);
            throw new \Exception("DATA 失败: $response");
        }
        
        $headers = "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <$from>\r\n";
        $headers .= "To: <$to>\r\n";
        $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= "Content-Transfer-Encoding: base64\r\n";
        
        $body = chunk_split(base64_encode($message));
        
        fputs($socket, $headers . "\r\n" . $body . "\r\n.\r\n");
        $response = readSMTPResponse($socket);
        if (substr($response, 0, 3) != '250') {
            fclose($socket);
            throw new \Exception("邮件发送失败: $response");
        }
        
        fputs($socket, "QUIT\r\n");
        fclose($socket);
        
        return true;
    }
    
    private function sendTestEmail($input) {
        $to = trim($input['to'] ?? '');
        $subject = trim($input['subject'] ?? 'Test email');
        $message = trim($input['message'] ?? '');

        if (empty($to)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Recipient email cannot be empty']);
            exit;
        }

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid recipient email format']);
            exit;
        }

        if (empty($subject)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Email subject cannot be empty']);
            exit;
        }

        if (empty($message)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Email content cannot be empty']);
            exit;
        }

        try {
            $SMTP = $GLOBALS['SMTP'];
            $this->sendSMTPMail($to, $subject, $message, $SMTP);
            
            echo json_encode([
                'success' => true,
                'message' => 'Email sent successfully',
                'data' => [
                    'to' => $to,
                    'subject' => $subject
                ]
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
    
    private function sendVerificationCode($input) {
        $email = trim($input['email'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid email']);
            exit;
        }

        $existingCode = getVerificationCode($email);
        if ($existingCode !== false) {
            http_response_code(429);
            echo json_encode(['success' => false, 'message' => 'Verification code already sent, please wait']);
            exit;
        }

        $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        if (!storeVerificationCode($email, $code)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to store verification code']);
            exit;
        }

        try {
            $SMTP = $GLOBALS['SMTP'];
            $to = $email;
            $subject = 'HRPAuth - Email Verification Code';
            $message = "Your verification code is: {$code}\n\nThe code is valid for 10 minutes. Please complete the verification as soon as possible.\n\nIf you did not request this code, please ignore this email.";
            
            $this->sendSMTPMail($to, $subject, $message, $SMTP);
            
            echo json_encode([
                'success' => true,
                'message' => 'Verification code sent successfully'
            ]);
        } catch (\Exception $e) {
            deleteVerificationCode($email);
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
    
    private function verifyCode($input) {
        $email = trim($input['email'] ?? '');
        $code = trim($input['code'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid email']);
            exit;
        }

        if (empty($code)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Verification code is required']);
            exit;
        }

        $storedCode = getVerificationCode($email);

        if ($storedCode === false) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Verification code expired or not found']);
            exit;
        }

        if ($code !== $storedCode) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid verification code']);
            exit;
        }

        deleteVerificationCode($email);

        try {
            $pdo = getPDO();
            $stmt = $pdo->prepare('UPDATE users SET verified = 1 WHERE email = ?');
            $stmt->execute([$email]);
            
            $affectedRows = $stmt->rowCount();
            
            if ($affectedRows === 0) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'User not found or already verified']);
                exit;
            }

            echo json_encode([
                'success' => true,
                'message' => 'Verification successful'
            ]);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to update verification status']);
        }
    }
}