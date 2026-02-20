<?php
require_once __DIR__ . '/config/memcache.php';
require_once __DIR__ . '/config/smtp.php';

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

function sendSMTPMail($to, $subject, $message, $smtp) {
    $host = $smtp['host'];
    $port = $smtp['port'];
    $from = $smtp['from']['email'];
    $fromName = $smtp['from']['name'];
    
    $socket = @fsockopen($host, $port, $errno, $errstr, 10);
    
    if (!$socket) {
        throw new Exception("无法连接到 SMTP 服务器: $errstr ($errno)");
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
        throw new Exception("SMTP 服务器未响应: $response");
    }
    
    fputs($socket, "EHLO " . gethostname() . "\r\n");
    $response = readSMTPResponse($socket);
    
    fputs($socket, "MAIL FROM: <$from>\r\n");
    $response = readSMTPResponse($socket);
    if (substr($response, 0, 3) != '250') {
        fclose($socket);
        throw new Exception("MAIL FROM 失败: $response");
    }
    
    fputs($socket, "RCPT TO: <$to>\r\n");
    $response = readSMTPResponse($socket);
    if (substr($response, 0, 3) != '250') {
        fclose($socket);
        throw new Exception("RCPT TO 失败: $response");
    }
    
    fputs($socket, "DATA\r\n");
    $response = readSMTPResponse($socket);
    if (substr($response, 0, 3) != '354') {
        fclose($socket);
        throw new Exception("DATA 失败: $response");
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
        throw new Exception("邮件发送失败: $response");
    }
    
    fputs($socket, "QUIT\r\n");
    fclose($socket);
    
    return true;
}

try {
    $to = $email;
    $subject = 'HRPAuth - 邮箱验证码';
    $message = "您的验证码是: {$code}\n\n验证码有效期为10分钟，请尽快完成验证。\n\n如果您没有请求此验证码，请忽略此邮件。";
    
    sendSMTPMail($to, $subject, $message, $SMTP);
    
    echo json_encode([
        'success' => true,
        'message' => 'Verification code sent successfully'
    ]);
} catch (Exception $e) {
    deleteVerificationCode($email);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
